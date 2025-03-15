# ElephantShadow
Use PHP for SSR of Webcomponents in declarative shadow dom. Just take the old elephant - he has a big shadow.

**ElephantShadow** is a PHP-based server-side rendering (SSR) engine for Web Components. It enables HTML authors to write standard custom elements (using attributes and slots) that are automatically transformed into fully rendered Web Components with declarative Shadow DOM. This approach ensures that your pages are SEO-friendly, accessible, and provide a fully working fallback even if JavaScript is disabled.

## Features

- **Server-Side Rendering (SSR):**  
  Transforms custom elements written in HTML into fully rendered components on the server. This means that all content—including component structure, CSS, and light DOM—is present in the initial HTML output.

- **Declarative Shadow DOM:**  
  Wraps component templates in a `<template shadowroot="open">` so that modern browsers can directly create a Shadow DOM from the SSR output.

- **Resource Resolution:**  
  Automatically loads the component’s template, CSS, and JS based on naming conventions or data attributes. For example, for a `<my-component>` element:
  - The template is searched in the `templates/` directory as `my-component.html`.
  - The CSS is searched in the `css/` directory as `my-component.css`.
  - The JS is searched in the `js/` directory as `my-component.js`.

- **JS Fallback Extraction:**  
  If a separate template file is not found, ElephantShadow can extract the template from a JS file by searching for the `this.shadowRoot.innerHTML = \`...\`` pattern.

- **CSS Embedding vs. Linking:**  
  You can choose to either embed the CSS inline within the Shadow DOM or link to an external CSS file.

- **Data Binding using Standard Web Component Practices:**  
  Instead of using non-standard placeholders like `{{text}}`, ElephantShadow uses a `data-bind` attribute. For example, an element like `<p data-bind="message"></p>` will be filled with the value of the `message` attribute from the custom element.

- **Slot Processing:**  
  The engine processes `<slot>` elements and automatically assigns the light DOM children to their corresponding slots. Default content in slots is preserved if no matching light DOM child is provided.

- **Full Page Rendering:**  
  The `renderFullPage()` method processes an entire HTML page, handling nested components from the innermost outward.

- **Automatic Component Registration:**  
  The JS code is appended in a `<script type="module">` block that registers the component only if it hasn’t been defined yet.

## Example Usage

### 1. Component Definition

Assume you have a Web Component defined in JavaScript (e.g., `js/my-component.js`):

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

### 2. Template and CSS Files (Optional)

You can also have separate template and CSS files based on naming conventions.

**Template (`templates/my-component.html`):**

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

**CSS (`css/my-component.css`):**

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

### 3. Authoring the Component in HTML

HTML authors can use the component as follows:

```html
<my-component message="Hello from SSR">
  <p slot="default">This is additional slot content.</p>
</my-component>
```

### 4. Resulting SSR Output

After processing by ElephantShadow, the output HTML will look similar to this:

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
  // JS code from js/my-component.js is included here
  customElements.define('my-component', class extends HTMLElement {});
}
</script>
```

## How It Works

1. **Resource Resolution:**  
   ElephantShadow first checks for `data-template`, `data-css`, and `data-js` attributes. If not provided, it uses naming conventions (e.g., `templates/my-component.html`, `css/my-component.css`, `js/my-component.js`).

2. **Template Processing:**  
   The template is loaded and processed by:
   - Replacing elements with a `data-bind` attribute with the corresponding attribute value from the custom element.
   - Processing `<slot>` elements by mapping light DOM children to their respective slots.
   - Optionally embedding the CSS inline if desired.

3. **Declarative Shadow DOM:**  
   The processed template is wrapped in a `<template shadowroot="open">` block so that the browser can automatically attach a Shadow DOM.

4. **Full Page Rendering:**  
   The `renderFullPage()` method processes an entire HTML document and recursively handles nested custom elements from the innermost outward.

5. **Automatic JS Registration:**  
   The associated JS code is appended in a `<script type="module">` block to ensure the component is registered on the client side.

## Initialization

To automatically process your page output, simply call the `init()` method at the beginning of your PHP page:

```php
<?php
require 'ElephantShadow.php';
ElephantShadow::init(); // Optionally pass true/false to embed CSS inline
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>My SSR Page</title>
</head>
<body>
  <!-- Your HTML content with custom elements goes here -->
  <my-component message="Hello from SSR">
    <p slot="default">This is additional slot content.</p>
  </my-component>
</body>
</html>
```

ElephantShadow intercepts the output buffer, processes the HTML, and replaces custom elements with fully rendered components.
