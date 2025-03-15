<?php
class ElephantShadow {
    // Default directories for resources
    private $templateDir;
    private $cssDir;
    private $jsDir;

    // Cache for loaded files (filepath => content)
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
        $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
        self::$fileCache[$filePath] = $content;
        return $content;
    }

    /**
     * Extracts the template string from a JS component file.
     * Looks for the pattern: this.shadowRoot.innerHTML = `...`;
     *
     * @param string $jsFilePath
     * @return string
     * @throws Exception if the template cannot be found.
     */
    private function extractTemplateFromJS($jsFilePath): string {
        $jsContent = self::loadFile($jsFilePath);
        // Use regex to extract content between backticks after "this.shadowRoot.innerHTML ="
        if (preg_match('/this\.shadowRoot\.innerHTML\s*=\s*`(.*?)`/s', $jsContent, $matches)) {
            return $matches[1];
        }
        throw new Exception("Template could not be extracted from JS file: $jsFilePath");
    }

    /**
     * Transforms a single custom element (as HTML string) into a complete code block with SSR.
     * Determines resources via data attributes, naming conventions, and falls back to JS extraction.
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
        // Ensure input is treated as UTF-8
        $elementHtml = mb_convert_encoding($elementHtml, 'HTML-ENTITIES', 'UTF-8');

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($elementHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $customElement = $doc->documentElement;
        $tagName = strtolower($customElement->tagName);

        // Determine resource paths based on data attributes and naming conventions
        if ($customElement->hasAttribute("data-template")) {
            $templatePath = $this->templateDir . $customElement->getAttribute("data-template");
        } elseif (!$templatePath) {
            // Check if a template file exists according to naming convention
            $possibleTemplatePath = $this->templateDir . $tagName . '.html';
            if (file_exists($possibleTemplatePath)) {
                $templatePath = $possibleTemplatePath;
            }
        }

        if ($customElement->hasAttribute("data-css")) {
            $cssPath = $this->cssDir . $customElement->getAttribute("data-css");
        } elseif (!$cssPath) {
            // Check if a CSS file exists according to naming convention
            $possibleCssPath = $this->cssDir . $tagName . '.css';
            if (file_exists($possibleCssPath)) {
                $cssPath = $possibleCssPath;
            }
        }

        if ($customElement->hasAttribute("data-js")) {
            $jsPath = $this->jsDir . $customElement->getAttribute("data-js");
        } elseif (!$jsPath) {
            $jsPath = $this->jsDir . $tagName . '.js';
        }

        // Determine template content:
        // If a templatePath is set, load it.
        // Otherwise, if the JS file exists and ends with .js, try extracting the template from it.
        if (isset($templatePath)) {
            $templateContent = self::loadFile($templatePath);
        } elseif ($jsPath && substr($jsPath, -3) === '.js') {
            $templateContent = $this->extractTemplateFromJS($jsPath);
        } else {
            throw new Exception("No valid template source found for <$tagName>");
        }

        // Load CSS (if available) and JS via cache
        $cssContent = ($embedCss && isset($cssPath)) ? self::loadFile($cssPath) : null;
        $jsContent = self::loadFile($jsPath);

        // Render the component using the templating engine
        $renderedElement = $this->renderComponentWithTemplate($customElement, $templateContent, $cssContent, $embedCss);

        // Wrap the JS code as a module to register the component if not already defined
        $wrappedJs = <<<EOD
<script type="module">
if (!customElements.get('$tagName')) {
  $jsContent
}
</script>
EOD;
        return $renderedElement . "\n" . $wrappedJs;
    }

    /**
     * Renders a custom element using a template.
     * Replaces attribute placeholders, processes slots, and wraps the output in a declarative Shadow DOM.
     *
     * @param DOMElement $element       The custom element
     * @param string     $templateHtml  The HTML template (with placeholders and <slot> elements).
     * @param string|null $cssContent    Optional CSS content.
     * @param bool       $embedCss      Whether to embed CSS inline.
     * @return string Rendered HTML of the custom element.
     */
    private function renderComponentWithTemplate(DOMElement $element, string $templateHtml, ?string $cssContent, bool $embedCss): string {
        $tag = $element->tagName;
        // (1) Replace attribute placeholders
        foreach ($element->attributes as $attr) {
            $name = $attr->name;
            $value = htmlspecialchars($attr->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $templateHtml = str_replace("{{{$name}}}", $value, $templateHtml);
        }
        // Remove any unreplaced placeholders
        $templateHtml = preg_replace('/\{\{[^}]+\}\}/', '', $templateHtml);

        // (2) Process slots: load template HTML into a DOMDocument to process <slot> elements
        $templateDom = new DOMDocument();
        @$templateDom->loadHTML('<?xml encoding="UTF-8"><template>' . $templateHtml . '</template>');
        $templateTag = $templateDom->getElementsByTagName('template')->item(0);
        $slots = iterator_to_array($templateTag->getElementsByTagName('slot'));
        $childrenBySlot = $this->groupChildrenBySlot($element);
        foreach ($slots as $slotElement) {
            $nameAttr = $slotElement->getAttribute('name');
            $slotName = $nameAttr !== '' ? $nameAttr : '__default__';
            $replacementHtml = '';
            if (!empty($childrenBySlot[$slotName])) {
                foreach ($childrenBySlot[$slotName] as $childNode) {
                    $replacementHtml .= $this->renderNode($childNode);
                }
            } else {
                // Use fallback content inside the <slot> element
                foreach ($slotElement->childNodes as $fallbackNode) {
                    $replacementHtml .= $templateDom->saveHTML($fallbackNode);
                }
            }
            $slotOuterHTML = $templateDom->saveHTML($slotElement);
            $templateHtml = str_replace($slotOuterHTML, $replacementHtml, $templateHtml);
        }

        // (3) Optionally embed CSS inline
        if ($embedCss && $cssContent) {
            $templateHtml = '<style>' . $cssContent . '</style>' . $templateHtml;
        }

        // (4) Wrap the processed template in a declarative Shadow DOM
        $shadowContent = '<template shadowroot="open">' . $templateHtml . '</template>';

        // (5) Rebuild the host element with original attributes, shadow DOM, and light DOM children
        $result = '<' . $tag;
        foreach ($element->attributes as $attr) {
            $name = $attr->name;
            $value = htmlspecialchars($attr->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $result .= " $name=\"$value\"";
        }
        $result .= '>';
        $result .= $shadowContent;
        foreach ($element->childNodes as $childNode) {
            $result .= $this->renderNode($childNode);
        }
        $result .= "</$tag>";
        return $result;
    }

    /**
     * Recursively renders a DOMNode.
     * Text nodes are escaped; element nodes are built with their attributes and children.
     *
     * @param DOMNode $node
     * @return string
     */
    private function renderNode(DOMNode $node): string {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($node instanceof DOMElement) {
            $tagName = $node->tagName;
            $html = '<' . $tagName;
            foreach ($node->attributes as $attr) {
                $name = $attr->name;
                $value = htmlspecialchars($attr->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= " $name=\"$value\"";
            }
            $html .= '>';
            foreach ($node->childNodes as $child) {
                $html .= $this->renderNode($child);
            }
            $html .= "</$tagName>";
            return $html;
        }
        return '';
    }

    /**
     * Groups an element's children by slot name.
     * Returns an array: 'slotName' => [DOMNode, ...] and '__default__' for unnamed children.
     *
     * @param DOMElement $element
     * @return array
     */
    private function groupChildrenBySlot(DOMElement $element): array {
        $groups = [];
        foreach ($element->childNodes as $child) {
            if (!($child instanceof DOMElement)) {
                if ($child instanceof DOMText) {
                    $textVal = trim($child->nodeValue);
                    if ($textVal !== '') {
                        $groups['__default__'][] = $child;
                    }
                }
                continue;
            }
            $assignedSlot = $child->getAttribute('slot');
            if ($assignedSlot === '') {
                $assignedSlot = '__default__';
            }
            $groups[$assignedSlot][] = $child;
        }
        return $groups;
    }

    /**
     * Transforms an entire HTML page by processing all custom elements
     * (i.e., all elements whose tag names contain a hyphen).
     * Nested web components are processed from the innermost outward.
     *
     * @param string $pageHtml The full HTML code of the page.
     * @param bool   $embedCss Controls whether CSS is embedded inline.
     * @return string The full HTML of the page with transformed web components.
     */
    public function renderFullPage($pageHtml, $embedCss = true) {
        $pageHtml = mb_convert_encoding($pageHtml, 'HTML-ENTITIES', 'UTF-8');

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($pageHtml);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $customNodes = $xpath->query("//*[contains(local-name(),'-')]");

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

        usort($nodesToProcess, function($a, $b) {
            return $b['depth'] - $a['depth'];
        });

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
     * Starts output buffering with a callback that automatically transforms
     * the entire page output when flushed.
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
