# ElephantShadow
Use PHP for SSR of Webcomponents in declarative shadow dom. Just take the old elephant - he has a big shadow.

It supports:

- **Resource resolution:** Automatically loads templates, CSS, and JavaScript via data attributes, naming conventions, or explicit parameters.
- **File caching:** Caches loaded files to improve performance.
- **UTF-8 encoding handling:** Converts file contents and output to HTML entities for robust UTF-8 support.
- **Nested component support:** Processes nested web components (inner components are rendered first).
- **Full-page transformation:** Automatically transforms all custom elements in a page.
- **Output buffering integration:** Easily integrates with your page using a single call.

## Example 1: Transform a Single Custom Element Using Data Attributes

In this example, the custom element provides its resource file names via data attributes. The default directories and naming conventions are used.

```php
<?php
require_once 'ElephantShadow.php';

$elementHtml = '<my-widget data-template="custom-widget.html" data-css="custom-widget.css" data-js="custom-widget.js">
  <span slot="title">Hello, World!</span>
  <div slot="content">This is a custom widget.</div>
</my-widget>';

$renderer = new ElephantShadow();
try {
    // Using data attributes to determine resource paths; CSS is embedded inline.
    $transformed = $renderer->renderWebComponent($elementHtml, null, null, null, true);
    echo $transformed;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
```

## Example 2: Transform a Single Custom Element with Explicit Resource Paths

Override defaults by providing explicit file paths. Here, CSS is referenced externally using a `<link>` tag.

```php
<?php
require_once 'ElephantShadow.php';

$elementHtml = '<fancy-button>
  Click Me!
</fancy-button>';

$renderer = new ElephantShadow('/path/to/templates/', '/path/to/css/', '/path/to/js/');
try {
    // Explicit resource paths are provided.
    $transformed = $renderer->renderWebComponent($elementHtml, 'fancy-button-template.html', 'fancy-button-script.js', 'fancy-button-style.css', false);
    echo $transformed;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
```

## Example 3: Transform an Entire HTML Page

Process a complete HTML document so that all custom elements are transformed. This example embeds CSS inline.

```php
<?php
require_once 'ElephantShadow.php';

$pageHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>My Full Page</title>
</head>
<body>
  <header>
    <h1>Page Header</h1>
  </header>
  <main>
    <custom-card data-template="card.html" data-css="card.css" data-js="card.js">
      <span slot="header">Card Title</span>
      <div slot="content">Card content goes here.</div>
    </custom-card>
    <another-widget>
      <div slot="info">Additional Info</div>
    </another-widget>
  </main>
  <footer>
    <p>Page Footer</p>
  </footer>
</body>
</html>
HTML;

$renderer = new ElephantShadow();
try {
    // Process the entire page; CSS is embedded inline.
    $fullPage = $renderer->renderFullPage($pageHtml, true);
    echo $fullPage;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
```

## Example 4: Automatic Full-Page Transformation via Output Buffering

Integrate the transformation automatically with a single call at the top of your PHP page. This example demonstrates nested web components as well.

Place the following code at the very top of your PHP page:

```php
<?php
require_once 'ElephantShadow.php';
ElephantShadow::init(); // Starts output buffering with automatic transformation
?>
```

Then, your page can look like this:

```html
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Auto Transformed Page</title>
</head>
<body>
  <header>
    <h1>Page Header</h1>
  </header>
  <main>
    <!-- Nested web components example -->
    <my-a data-template="a.html" data-css="a.css" data-js="a.js">
      Outer content
      <my-b data-template="b.html" data-css="b.css" data-js="b.js">
        Inner content
      </my-b>
    </my-a>
  </main>
  <footer>
    <p>Page Footer</p>
  </footer>
</body>
</html>
```

**Explanation:**

- **Automatic Output Buffering:**  
  `ElephantShadow::init()` starts output buffering with a callback that processes the entire page output when the script ends. No additional call is needed at the end.
- **Nested Component Support:**  
  In the example, `<my-b>` is nested within `<my-a>`. The renderer processes the deepest components first, ensuring proper transformation of all nested components

