# ElephantShadow
Use PHP for SSR of Webcomponents in declarative shadow dom. Just take the old elephant - he has a big shadow.

**ElephantShadow** is a PHP-based SSR engine for Web Components. It lets you write standard custom elements (using attributes and slots) that are automatically transformed into fully rendered components with declarative Shadow DOM. This ensures that your page is SEO-friendly, accessible, and provides a usable fallback when JavaScript is disabled.

## Key Features

- **Server-Side Rendering (SSR):**  
  Renders your Web Components on the server so that all the component content (including its Shadow DOM structure) is available in the initial HTML output.

- **Declarative Shadow DOM:**  
  Wraps the component template in a `<template shadowroot="open">` element, enabling modern browsers to automatically attach a Shadow DOM.

- **Resource Resolution:**  
  Automatically resolves the component’s template, CSS, and JS files using naming conventions (e.g., for a `<my-component>`, it checks for `templates/my-component.html`, `css/my-component.css`, and `js/my-component.js`).  
  If a separate template file is not found, it can also extract the template block from the JS file (by looking for the pattern `this.shadowRoot.innerHTML = `…``).

- **CSS Embedding vs. Linking:**  
  You can choose whether to embed CSS inline within the Shadow DOM or reference it via a `<link>` element.

- **Standard Data Binding:**  
  Instead of using non-standard placeholders like `{{text}}`, ElephantShadow uses the native Web Component approach. In your template, add elements with a `data-bind` attribute (for example, `<p data-bind="message"></p>`) and ElephantShadow will automatically insert the corresponding attribute value from the custom element.

- **Slot Processing:**  
  Automatically maps light DOM children to their respective `<slot>` elements in the template. If no matching slot content is provided, the default slot content is used.

- **Full Page and Single Component Processing:**  
  The engine can process an entire HTML page (handling nested components) or just transform a single Web Component.

- **Automatic Component Registration:**  
  The associated JS file is appended as a `<script type="module">` block that registers the component only if it isn’t already defined on the client.

## Example Usage

### A. Transforming a Whole Page

When you want to process an entire HTML page that includes one or more custom elements, use the `renderFullPage()` method (or simply call `ElephantShadow::init()` at the top of your PHP file).

**PHP File (index.php):**

```php
<?php
require 'ElephantShadow.php';
ElephantShadow::init(); // This will process the entire output buffer.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My SSR Page</title>
</head>
<body>
  <!-- Custom elements in your page -->
  <my-component message="Hello from SSR">
    <p slot="default">This is additional slot content.</p>
  </my-component>
  <another-component attribute="Some value"></another-component>
</body>
</html>
```

When the page is sent to the browser, ElephantShadow processes every custom element (elements with a hyphen in the tag name) and replaces them with fully rendered HTML blocks that include a declarative Shadow DOM and the required JS registration.

### B. Transforming a Single Web Component

If you prefer to process only an individual component rather than an entire page, you can call the `renderWebComponent()` method directly.

**Example:**

```php
<?php
require 'ElephantShadow.php';

// Create an instance with default directories
$shadow = new ElephantShadow();

// Define the custom element markup as authored by the HTML writer:
$componentHtml = '
<my-component message="Hello from SSR">
  <p slot="default">This is additional slot content.</p>
</my-component>
';

// Transform the component. The method will automatically resolve the template,
// CSS, and JS files (using naming conventions or data attributes) and generate
// the SSR output.
$renderedComponent = $shadow->renderWebComponent($componentHtml);

// Output the SSR-rendered component
echo $renderedComponent;
?>
```

**Assume the following resource files exist:**

- **Template (`templates/my-component.html`):**

  ```html
  <!-- templates/my-component.html -->
  <p data-bind="message"></p>
  <div>
    <h3>Child Elements:</h3>
    <ul>
      <li>Item 1</li>
      <li>Item 2</li>
      <li>Item 3</li>
    </ul>
  </div>
  <slot></slot>
  ```

- **CSS (`css/my-component.css`):**

  ```css
  /* css/my-component.css */
  :host {
      display: block;
      padding: 10px;
      background: #f0f0f0;
      border-radius: 5px;
      font-family: Arial, sans-serif;
  }
  ```

- **JS (`js/my-component.js`):**

  ```js
  // js/my-component.js
  class MyComponent extends HTMLElement {
      constructor() {
          super();
          this.attachShadow({ mode: 'open' });
      }
      static get observedAttributes() {
          return ['message'];
      }
      attributeChangedCallback(name, oldValue, newValue) {
          if (name === 'message') {
              this.render();
          }
      }
      connectedCallback() {
          this.render();
      }
      render() {
          this.shadowRoot.innerHTML = `
              <style>
                  :host {
                      display: block;
                      padding: 10px;
                      background: #f0f0f0;
                      border-radius: 5px;
                      font-family: Arial, sans-serif;
                  }
              </style>
              <p>${this.getAttribute('message') || 'Default message'}</p>
              <div>
                  <h3>Child Elements:</h3>
                  <ul>
                      <li>Item 1</li>
                      <li>Item 2</li>
                      <li>Item 3</li>
                  </ul>
              </div>
              <slot></slot>
          `;
      }
  }
  customElements.define('my-component', MyComponent);
  ```

**Resulting SSR Output:**

```html
<my-component message="Hello from SSR">
  <template shadowroot="open">
    <style>
      :host {
          display: block;
          padding: 10px;
          background: #f0f0f0;
          border-radius: 5px;
          font-family: Arial, sans-serif;
      }
    </style>
    <p>Hello from SSR</p>
    <div>
      <h3>Child Elements:</h3>
      <ul>
          <li>Item 1</li>
          <li>Item 2</li>
          <li>Item 3</li>
      </ul>
    </div>
    <slot></slot>
  </template>
  <p slot="default">This is additional slot content.</p>
</my-component>
<script type="module">
if (!customElements.get('my-component')) {
  // JS code from js/my-component.js is inserted here.
  customElements.define('my-component', class extends HTMLElement {});
}
</script>
```

---

## How It Works

1. **Resource Resolution:**  
   ElephantShadow checks for data attributes (`data-template`, `data-css`, `data-js`) on the custom element. If none are provided, it uses naming conventions to load the respective resource files.

2. **Template Processing:**  
   The engine loads the template, processes data binding for elements with a `data-bind` attribute (inserting the corresponding attribute values from the custom element), and handles `<slot>` elements by mapping them to the provided light DOM content.

3. **Declarative Shadow DOM:**  
   The processed template is wrapped in a `<template shadowroot="open">` block, so that browsers can automatically attach a Shadow DOM when rendering the component.

4. **Automatic JS Registration:**  
   The associated JavaScript is appended as a `<script type="module">` block. This block ensures that the component is defined on the client side if it hasn’t already been registered.

5. **Full Page vs. Single Component Rendering:**  
   - For full page rendering, use `renderFullPage()` or `ElephantShadow::init()`, which processes all custom elements on the page.
   - For transforming individual components, use `renderWebComponent()`.
