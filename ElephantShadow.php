<?php

class ElephantShadow {

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

     * @param string|null $cssDir      Directory for CSS files.

     * @param string|null $jsDir       Directory for JavaScript files.

     */

    public function __construct($templateDir = null, $cssDir = null, $jsDir = null) {

        $this->templateDir = $templateDir ?: __DIR__ . '/templates/';

        $this->cssDir      = $cssDir      ?: __DIR__ . '/css/';

        $this->jsDir       = $jsDir       ?: __DIR__ . '/js/';

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

            throw new Exception("Could not load "font-size:10.5pt;font-family:Consolas;color:#001080;mso-fareast-language:DE">$filePath");

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

        if (preg_match('/this\.shadowRoot\.innerHTML\s*=\s*`(.*?)`/s', $jsContent, $matches)) {

            return $matches[1];

        }

        throw new Exception("Template could not be extracted from JS "font-size:10.5pt;font-family:Consolas;color:#001080;mso-fareast-language:DE">$jsFilePath");

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

    private function resolveResourcePaths(DOMElement $element, string $tagName, ?string $templatePath, ?string $cssPath, ?string $jsPath): array {

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

     * @param DOMElement $sourceElement   The custom element.

     */

    private function processDataBindings(DOMElement $templateElement, DOMElement $sourceElement): void {

        foreach ($templateElement->getElementsByTagName('*') as $node) {

            if ($node->hasAttribute('data-bind')) {

                $bindAttr = $node->getAttribute('data-bind');

                $value = $sourceElement->getAttribute($bindAttr);

                $node->nodeValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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

    private function loadAndProcessTemplate(string $templateContent, DOMElement $sourceElement, ?string $cssContent, bool $embedCss): string {

        $templateDom = new DOMDocument();

        // Load the template HTML wrapped in a <template> tag

        @$templateDom->loadHTML('<?xml encoding="UTF-8"><template>' . $templateContent . '</template>');

        $templateTag = $templateDom->getElementsByTagName('template')->item(0);

        // Process data bindings

        $this->processDataBindings($templateTag, $sourceElement);

        $processedTemplate = '';

        foreach ($templateTag->childNodes as $child) {

            $processedTemplate .= $templateDom->saveHTML($child);

        }

        // Optionally embed CSS inline

        if ($embedCss && $cssContent) {

            $processedTemplate = '<style>' . $cssContent . '</style>' . $processedTemplate;

        }

        // Process slots

        @$templateDom->loadHTML(

