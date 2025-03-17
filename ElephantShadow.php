<?php
class ElephantShadow {
    // Default directories for resources
    private $templateDir;
    private $cssDir;
    private $jsDir;

    // Cache for already loaded files (filepath => content)
    private static $fileCache = [];

    /**
     * Constructor to set default directories.
     *
     * @param string|null $templateDir Directory for HTML templates.
     * @param string|null $cssDir      Directory for CSS files.
     * @param string|null $jsDir       Directory for JavaScript files.
     */
    public function __construct($templateDir = null, $cssDir = null, $jsDir = null) {
        $this->templateDir = $templateDir ?: __DIR__ . '/templates/';
        $this->cssDir      = $cssDir      ?: __DIR__ . '/css/';
        $this->jsDir       = $jsDir       ?: __DIR__ . '/js/';
    }

    /**
     * Loads a file, converts its content to HTML entities (UTF-8) and caches the result.
     *
     * @param string $filePath
     * @return string
     * @throws Exception if the file cannot be loaded.
     */
    private static function loadFile($filePath) {
        if (isset(self::$fileCache[$filePath])) {
            return self::$fileCache[$filePath];
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Could not load file: $filePath");
        }
        // Convert the content to HTML entities (UTF-8)
        $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
        self::$fileCache[$filePath] = $content;
        return $content;
    }

    /**
     * Transforms a single custom element into a complete code block with SSR.
     *
     * Resources are determined via data attributes, parameters, or naming conventions.
     *
     * @param string      $elementHtml The HTML of the custom element (with slots and attributes)
     * @param string|null $templatePath Optional explicit path to the HTML template file.
     * @param string|null $jsPath       Optional explicit path to the JavaScript file.
     * @param string|null $cssPath      Optional explicit path to the CSS file.
     * @param bool        $embedCss     If true, CSS is embedded inline; otherwise a <link> is used.
     *
     * @return string The generated HTML code block for the custom element.
     * @throws Exception if a required file cannot be loaded.
     */
    public function renderWebComponent($elementHtml, $templatePath = null, $jsPath = null, $cssPath = null, $embedCss = true) {
        // Ensure the input is treated as UTF-8
        $elementHtml = mb_convert_encoding($elementHtml, 'HTML-ENTITIES', 'UTF-8');

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($elementHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $customElement = $doc->documentElement;
        $tagName = strtolower($customElement->tagName);

        // Determine resource paths from data attributes, parameters, or naming conventions
        if ($customElement->hasAttribute("data-template")) {
            $templatePath = $this->templateDir . $customElement->getAttribute("data-template");
        } elseif (!$templatePath) {
            $templatePath = $this->templateDir . $tagName . '.html';
        }
        if ($customElement->hasAttribute("data-css")) {
            $cssPath = $this->cssDir . $customElement->getAttribute("data-css");
        } elseif (!$cssPath) {
            $cssPath = $this->cssDir . $tagName . '.css';
        }
        if ($customElement->hasAttribute("data-js")) {
            $jsPath = $this->jsDir . $customElement->getAttribute("data-js");
        } elseif (!$jsPath) {
            $jsPath = $this->jsDir . $tagName . '.js';
        }

        // Load template, CSS, and JS using the cache
        $templateContent = self::loadFile($templatePath);
        if ($embedCss) {
            $cssContent = self::loadFile($cssPath);
        }
        $jsContent = self::loadFile($jsPath);

        // Create the <template> element with shadowrootmode="open"
        $templateElement = $doc->createElement("template");
        $templateElement->setAttribute("shadowrootmode", "open");

        // Create a document fragment that contains the CSS (as <style> or <link>) and the template content
        $fragment = $doc->createDocumentFragment();
        if ($embedCss) {
            $styleElement = $doc->createElement("style", $cssContent);
            $fragment->appendChild($styleElement);
        } else {
            $linkElement = $doc->createElement("link");
            $linkElement->setAttribute("rel", "stylesheet");
            $linkElement->setAttribute("href", $cssPath);
            $fragment->appendChild($linkElement);
        }
        $tempDoc = new DOMDocument();
        libxml_use_internal_errors(true);
        // Ensure the template content is treated as UTF-8
        $templateContent = mb_convert_encoding($templateContent, 'HTML-ENTITIES', 'UTF-8');
        $tempDoc->loadHTML($templateContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        foreach ($tempDoc->documentElement->childNodes as $node) {
            $importedNode = $doc->importNode($node, true);
            $fragment->appendChild($importedNode);
        }
        $templateElement->appendChild($fragment);

        if ($customElement->hasChildNodes()) {
            $customElement->insertBefore($templateElement, $customElement->firstChild);
        } else {
            $customElement->appendChild($templateElement);
        }

        $componentHtml = $doc->saveHTML($customElement);

        $wrappedJs = <<<EOD
<script type="module">
if (!customElements.get('$tagName')) {
  $jsContent
}
</script>
EOD;
        return $componentHtml . "\n" . $wrappedJs;
    }

    /**
     * Transforms an entire HTML page by processing all custom elements (elements whose tag name contains a dash).
     * This version supports nested web components by processing the deepest nodes first.
     *
     * @param string $pageHtml The full HTML code of the page.
     * @param bool   $embedCss Controls whether CSS is embedded inline or referenced externally.
     *
     * @return string The full HTML of the page with transformed web components.
     * @throws Exception on resource loading issues.
     */
    public function renderFullPage($pageHtml, $embedCss = true) {
        // Ensure the page output is treated as UTF-8
        $pageHtml = mb_convert_encoding($pageHtml, 'HTML-ENTITIES', 'UTF-8');

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($pageHtml);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $customNodes = $xpath->query("//*[contains(local-name(),'-')]");

        // Collect nodes with their depth (deepest nodes first)
        $nodesToProcess = [];
        foreach ($customNodes as $node) {
            $depth = 0;
            $temp = $node;
            while ($temp->parentNode !== null) {
                $depth++;
                $temp = $temp->parentNode;
            }
            $nodesToProcess[] = ['node' => $node, 'depth' => $depth];
        }

        // Sort descending by depth so that nested components are processed first.
        usort($nodesToProcess, function($a, $b) {
            return $b['depth'] - $a['depth'];
        });

        // Process each custom element
        foreach ($nodesToProcess as $item) {
            $node = $item['node'];
            $elementHtml = $doc->saveHTML($node);
            $rendered = $this->renderWebComponent($elementHtml, null, null, null, $embedCss);

            $fragment = $doc->createDocumentFragment();
            $tmpDoc = new DOMDocument();
            libxml_use_internal_errors(true);
            $tmpDoc->loadHTML("<div>$rendered</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            $wrapper = $tmpDoc->getElementsByTagName('div')->item(0);
            if ($wrapper) {
                while ($wrapper->firstChild) {
                    $importedNode = $doc->importNode($wrapper->firstChild, true);
                    $fragment->appendChild($importedNode);
                    $wrapper->removeChild($wrapper->firstChild);
                }
            }
            $node->parentNode->replaceChild($fragment, $node);
        }
        return $doc->saveHTML();
    }

    /**
     * Starts output buffering with a callback that automatically transforms the entire page output when flushed.
     * This way, only a single call (ElephantShadow::init()) at the beginning of your page is required.
     *
     * @param bool $embedCss Controls whether CSS is embedded inline.
     */
    public static function init($embedCss = true) {
        ob_start(function($buffer) use ($embedCss) {
            $renderer = new self();
            return $renderer->renderFullPage($buffer, $embedCss);
        });
    }
}
?>
