<?php
class ElephantShadow
{
    // Default directories for resources
    private $templateDir;
    private $cssDir;
    private $jsDir;

    public const TEMPLATE_ATTR = "data-els-template";
    public const CSS_ATTR = "data-els-css";
    public const JS_ATTR = "data-els-js";

    // Cache for loaded files (filepath => content)
    private static $fileCache = [];

     // Collected JS snippets keyed by component tag name
     private static $collectedJs = [];

    
    /**
     * Constructor to set default directories.
     *
     * @param string|null $templateDir Directory for HTML templates.
     * @param string|null $cssDir      Directory for CSS files.
     * @param string|null $jsDir       Directory for JavaScript files.
     */
    public function __construct($templateDir = null, $cssDir = null, $jsDir = null)
    {
        $this->templateDir = $templateDir ?: __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        $this->templateDir = trim($this->templateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->cssDir = $cssDir ?: __DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR;
        $this->cssDir = trim($this->cssDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->jsDir = $jsDir ?: __DIR__ . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR;
        $this->jsDir = trim($this->jsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Loads a file, converts its content to HTML entities (UTF-8) and caches the result.
     *
     * @param string $filePath
     * @return string
     * @throws Exception if the file cannot be loaded.
     */
    private static function loadFile($filePath)
    {
        if (isset(self::$fileCache[$filePath])) {
            return self::$fileCache[$filePath];
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Could not load file: $filePath");
        }
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
    private function extractTemplateFromJS($jsFilePath): string
    {
        $jsContent = self::loadFile($jsFilePath);
        if (preg_match('/this\.shadowRoot\.innerHTML\s*=\s*`(.*?)`/s', $jsContent, $matches)) {
            return $matches[1];
        }
        throw new Exception("Template could not be extracted from JS file: $jsFilePath");
    }

    /**
     * Resolves resource paths (template, CSS, JS) based on data attributes and naming conventions.
     * Resolve order: data-attribute, argument value in this function, file in conventional folders
     * jsDir, cssDir and templateDir
     *
     * @param DOMElement $element The custom element.
     * @param string $tagName Lowercase tag name of the custom element.
     * @param string|null $templatePath Explicit template path if provided.
     * @param string|null $cssPath Explicit CSS path if provided.
     * @param string|null $jsPath Explicit JS path if provided.
     * @return array An array with keys [templatePath, cssPath, jsPath].
     */
    private function resolveResourcePaths(DOMElement $element, string $tagName, ?string $templatePath, ?string $cssPath, ?string $jsPath): array
    {
        // Template resolution
        if ($element->hasAttribute(self::TEMPLATE_ATTR)) {
            $templatePath = $this->templateDir . $element->getAttribute(self::TEMPLATE_ATTR);
        } elseif (!$templatePath) {
            $possibleTemplatePath = $this->templateDir . $tagName . '.html';
            if (file_exists($possibleTemplatePath)) {
                $templatePath = $possibleTemplatePath;
            }
        }
        // CSS resolution
        if ($element->hasAttribute(self::CSS_ATTR)) {
            $cssPath = $this->cssDir . $element->getAttribute(self::CSS_ATTR);
        } elseif (!$cssPath) {
            $possibleCssPath = $this->cssDir . $tagName . '.css';
            if (file_exists($possibleCssPath)) {
                $cssPath = $possibleCssPath;
            }
        }
        // JS resolution
        if ($element->hasAttribute(self::JS_ATTR)) {
            $jsPath = $this->jsDir . $element->getAttribute(self::JS_ATTR);
        } elseif (!$jsPath) {
            $jsPath = $this->jsDir . $tagName . '.js';
        }
        return [$templatePath, $cssPath, $jsPath];
    }

    /**
     * Processes data bindings in the template.
     * For each element with a "data-bind" attribute, sets its text content to the corresponding attribute value from the custom element.
     *
     * @param DOMElement $templateElement The template element containing the DOM structure.
     * @param DOMElement $sourceElement   The custom element.
     */

    private function processDataBindings(DOMElement $templateElement, DOMElement $sourceElement): void
    {
        foreach ($templateElement->getElementsByTagName('*') as $node) {
            if ($node->hasAttribute('data-bind')) {
                $bindAttr = $node->getAttribute('data-bind');
                $value = $sourceElement->getAttribute($bindAttr);
            }
        }
    }

    /**
     * Loads and processes the template.
     * Loads the template content, processes data bindings and slots, and optionally embeds CSS.
     *
     * @param string $templateContent The raw template content.
     * @param DOMElement $sourceElement The custom element.
     * @param string|null $cssContent Optional CSS content.
     * @param bool $embedCss Whether to embed CSS inline.
     * @return string The processed template.
     */
    private function loadAndProcessTemplate(string $templateContent, DOMElement $sourceElement, ?string $cssContent, bool $embedCss): string
    {
        $templateDom = new DOMDocument();
        // Check if the template content already includes a <template> tag.
        $trimmedContent = trim($templateContent);
        if (stripos($trimmedContent, '<template') === 0) {
            // Use the content as provided.
            @$templateDom->loadHTML('<?xml encoding="UTF-8">' . $templateContent);
        } else {
            // Wrap the content in a <template> tag.
            @$templateDom->loadHTML('<?xml encoding="UTF-8"><template>' . $templateContent . '</template>');
        }
        $templateTag = $templateDom->getElementsByTagName('template')->item(0);
        // Process data bindings.
        $this->processDataBindings($templateTag, $sourceElement);
        $processedTemplate = '';
        foreach ($templateTag->childNodes as $child) {
            $processedTemplate .= $templateDom->saveHTML($child);
        }
        // Optionally embed CSS inline.
        if ($embedCss && $cssContent) {
            $processedTemplate = '<style>' . $cssContent . '</style>' . $processedTemplate;
        }
        // Process <slot> elements.
        @$templateDom->loadHTML('<?xml encoding="UTF-8"><template>' . $processedTemplate . '</template>');
        $templateTag = $templateDom->getElementsByTagName('template')->item(0);
        $slots = iterator_to_array($templateTag->getElementsByTagName('slot'));
        $childrenBySlot = $this->groupChildrenBySlot($sourceElement);
        foreach ($slots as $slotElement) {
            $nameAttr = $slotElement->getAttribute('name');
            $slotName = $nameAttr !== '' ? $nameAttr : '__default__';
            $replacementHtml = '';
            if (!empty($childrenBySlot[$slotName])) {
                foreach ($childrenBySlot[$slotName] as $childNode) {
                    $replacementHtml .= $this->renderNode($childNode);
                }
            } else {
                // Use fallback content inside the <slot> element.
                foreach ($slotElement->childNodes as $fallbackNode) {
                    $replacementHtml .= $templateDom->saveHTML($fallbackNode);
                }
            }
            $slotOuterHTML = $templateDom->saveHTML($slotElement);
            $processedTemplate = str_replace($slotOuterHTML, $replacementHtml, $processedTemplate);
        }
        return $processedTemplate;
    }

    /**
     * Renders a custom element using a processed template.
     * Wraps the processed template in a declarative Shadow DOM and rebuilds the host element.
     *
     * @param DOMElement $element The custom element.
     * @param string $templateContent The raw template content.
     * @param string|null $cssContent Optional CSS content.
     * @param bool $embedCss Whether to embed CSS inline.
     * @return string The rendered HTML for the custom element.
     */
    private function renderComponentWithTemplate(DOMElement $element, string $templateContent, ?string $cssContent, bool $embedCss, string $shadowrootmode): string
    {
        $tag = $element->tagName;
        $processedTemplate = $this->loadAndProcessTemplate($templateContent, $element, $cssContent, $embedCss);
        //TODO: Allow closed
        $shadowContent = '<template shadowrootmode="'.$shadowrootmode.'">' . $processedTemplate . '</template>';
        // Rebuild host element with original attributes.
        $result = '<' . $tag;
        foreach ($element->attributes as $attr) {
            $result .= " {$attr->name}=\"{$attr->value}\"";
        }
        $result .= '>' . $shadowContent;
        // Append only light DOM children that are not already processed (i.e. skip nodes with a "slot" attribute)
        foreach ($element->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->hasAttribute('slot')) {
                continue;
            }
            $result .= $this->renderNode($childNode);
        }
        $result .= "</$tag>";
        return $result;
    }

    private function safeguarAttachShadow($jsContent, $shadowrootmode) {
          // Define regex patterns.
          $patternAssignment = '/\b(let|const|var)\s+(\w+)\s*=\s*this\.attachShadow\s*\(\s*\{\s*mode\s*:\s*[\'"](open|closed)[\'"]\s*\}\s*\)\s*;/';
          $patternNoAssignment = '/^\s*this\.attachShadow\s*\(\s*\{\s*mode\s*:\s*[\'"](open|closed)[\'"]\s*\}\s*\)\s*;?\s*$/m';

          // First handle cases where attachShadow is assigned.
          $jsContent = preg_replace_callback($patternAssignment, function($matches) use ($shadowrootmode) {
              // Return a conditional expression that safely assigns the value.
              return "{$matches[1]} {$matches[2]} = (this.shadowRoot && this.shadowRoot.innerHTML.trim()) ? this.shadowRoot : this.attachShadow({ mode: '{$shadowrootmode}' });";
          }, $jsContent);

          // Then replace any standalone attachShadow call with an if-statement.
          $jsContent = preg_replace_callback($patternNoAssignment, function($matches) use ($shadowrootmode) {
              return "if (!this.shadowRoot || !this.shadowRoot.innerHTML.trim()) { this.attachShadow({ mode: '{$shadowrootmode}' }); }";
          }, $jsContent);

          return $jsContent;

    }

    /**
     * Public method to render a single custom element.
     * Accepts the HTML string of the custom element, resolves its resources,
     * processes the template, and returns the SSR-rendered HTML without inlined JS.
     *
     * The JS registration code is collected separately.
     *
     * @param string      $elementHtml The HTML markup of the custom element.
     * @param string|null $templatePath Optional explicit template path.
     * @param string|null $jsPath       Optional explicit JS path.
     * @param string|null $cssPath      Optional explicit CSS path.
     * @param bool        $embedCss     If true, embed CSS inline.
     * @param bool        $includeJs    If true, include JS content in the rendered element.
     * @param string      $shadowrootmode The shadow root mode to use for the custom element.
     * @return string The SSR-rendered HTML of the custom element.
     */
    public function renderWebComponent(string $elementHtml, ?string $templatePath = null, ?string $jsPath = null, ?string $cssPath = null, bool $embedCss = true, bool $includeJs = true, string $shadowrootmode = 'open'): string
    {
        $doc = $this->loadHTMLtoDOM($elementHtml);
        // Assume the document element is the custom element.
        $customElement = $doc->documentElement;
        $tagName = strtolower($customElement->tagName);
        // Custom Element?
        if (strpos($tagName, '-') !== false) {
            // Resolve resource paths using data attributes or naming conventions.
            list($templatePath, $cssPath, $jsPath) = $this->resolveResourcePaths($customElement, $tagName, $templatePath, $cssPath, $jsPath);
            // Determine template content:
            // If a template file exists, load it; otherwise, extract from JS.
            if ($templatePath) {
                $templateContent = self::loadFile($templatePath);
            } elseif ($jsPath && substr($jsPath, -3) === '.js') {
                $templateContent = $this->extractTemplateFromJS($jsPath);
            } else {
                throw new Exception("No valid template source found for <$tagName>");
            }
            // Load CSS (if available) and JS.
            $cssContent = ($embedCss && $cssPath) ? self::loadFile($cssPath) : null;
            $jsContent = self::loadFile($jsPath);

            // Safeguard attachShadow calls in the JS content.
            $jsContent = $this->safeguarAttachShadow($jsContent, $shadowrootmode);

            // Check if the JS file already contains a <style> tag
            if ($cssContent && strpos($jsContent, '<style>') === false) {
                // Embed the CSS into the render function of the JS file.
                $jsContent = preg_replace(
                    '/(this\.shadowRoot\.innerHTML\s*=\s*`)/',
                    '$1<style>' . addslashes($cssContent) . '</style>',
                    $jsContent
                );
            }

            // Render the component using the processed template.
            $renderedElement = $this->renderComponentWithTemplate($customElement, $templateContent, $cssContent, $embedCss, $shadowrootmode);
            // Collect the JS registration snippet if not already added.
            $jsSnippet = "if (!customElements.get('$tagName')) {\n  $jsContent\n}";
            if (!isset(self::$collectedJs[$tagName])) {
                self::$collectedJs[$tagName] = $jsSnippet;
            }
            // Optionally include the JS content in the rendered element.
            if ($includeJs) {
                $renderedElement .= '<script>' . $jsSnippet . '</script>';
            }
            return $renderedElement;
        } else {
            throw new Exception("<$tagName> is not a custom element. No hyphen in name");
        }
    }

    /**
     * Simplified method to render a DOMNode as an HTML string using the built-in DOMDocument::saveHTML().
     *
     * @param DOMNode $node
     * @return string
     */
    private function renderNode(DOMNode $node): string
    {
        // If the node is a document, use saveHTML() directly.
        if ($node instanceof DOMDocument) {
            return $node->saveHTML();
        }
        // Otherwise, use the owner document's saveHTML() on the node.
        return $node->ownerDocument ? $node->ownerDocument->saveHTML($node) : '';
    }

    /**
     * Groups an element's children by slot name.
     *
     * @param DOMElement $element
     * @return array Associative array: 'slotName' => [DOMNode, ...] and '__default__' for unnamed children.
     */
    private function groupChildrenBySlot(DOMElement $element): array
    {
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
            if ($assignedSlot === '' || $assignedSlot === 'default') { // treat "default" as __default__
                $assignedSlot = '__default__';
            }
            $groups[$assignedSlot][] = $child;
        }
        return $groups;
    }

    /**
     * Load HTML to DOMDocument for further processing
     *
     * @param string $html The  HTML code
     * @return DOMDocument A DOMDocument instance
     */
    private function loadHTMLtoDOM($html)
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        // Load the raw HTML as a fragment.
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $doc;
    }

    /**
     * Processes an entire HTML page by transforming all custom elements
     * (i.e., elements whose tag names contain a hyphen).
     * Nested web components are processed from the innermost outward.
     *
     * @param string $pageHtml The full HTML code of the page.
     * @param bool $embedCss Whether CSS is embedded inline.
     * @return string The processed HTML with SSR-transformed components.
     */
    public function renderFullPage($pageHtml, $embedCss = true): string
    {
        $doc = $this->loadHTMLtoDOM($pageHtml);
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
        usort($nodesToProcess, function ($a, $b) {
            return $b['depth'] - $a['depth'];
        });
        foreach ($nodesToProcess as $item) {
            $node = $item['node'];
            $elementHtml = $doc->saveHTML($node);
            $rendered = $this->renderWebComponent($elementHtml, null, null, null, $embedCss, false);
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
        // After processing all custom elements, insert collected JS into <head>
        if (!empty(self::$collectedJs)) {
            $allJs = implode("\n", self::$collectedJs);
            $head = $doc->getElementsByTagName("head")->item(0);
            if ($head) {
                $script = $doc->createElement("script", "\n" . $allJs . "\n");
                $script->setAttribute("type", "module");
                $head->appendChild($script);
            } else {
                // If no head is present, append to the end of <body>
                $body = $doc->getElementsByTagName("body")->item(0);
                if ($body) {
                    $script = $doc->createElement("script", "\n" . $allJs . "\n");
                    $script->setAttribute("type", "module");
                    $body->appendChild($script);
                }
            }
        }
        return $doc->saveHTML();
    }

    /**
     * Starts output buffering with a callback that automatically transforms
     * the entire page output when flushed.
     *
     * @param bool $embedCss Whether CSS is embedded inline.
     */
    public function init($embedCss = true)
    {
        ob_start(function ($buffer) use ($embedCss) {
            return $this->renderFullPage($buffer, $embedCss);
        });
    }

   
}
