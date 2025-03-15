# ElephantShadow
Use PHP for SSR of Webcomponents in declarative shadow dom. Just take the old elephant - he has a big shadow.

---

## Overview

**ElephantShadow** is a PHP solution for server-side rendering (SSR) of web components using declarative Shadow DOM. Its purpose is to let HTML authors write custom elements with attributes and slots directly in HTML while the server transforms them into fully rendered web components. This yields significant benefits for SEO, accessibility, and progressive enhancement because the complete content is already available on page load—even if JavaScript is disabled.

### Key Features

- **Declarative Shadow DOM Rendering:**  
  Each custom element is transformed so that its content is wrapped in a `<template shadowroot="open">`. This ensures that styles and markup are encapsulated as they would be in a client-side Shadow DOM.

- **Attribute Replacement & Slot Processing:**  
  A simple templating syntax (using placeholders like `{{attribute}}`) is used to replace attribute values in the component’s HTML template. The system also processes `<slot>` elements, distributing the custom element’s children to their correct positions.

- **Resource Resolution via Naming Conventions:**  
  ElephantShadow automatically looks for separate resource files using naming conventions. For a custom element such as `<my-component>`:
  - It checks for a template file in the `templates/` directory (e.g., `templates/my-component.html`).
  - It checks for a CSS file in the `css/` directory (e.g., `css/my-component.css`).
  - It checks for a JavaScript file in the `js/` directory (e.g., `js/my-component.js`).
  
  If a separate HTML template is not found, it falls back to extracting the template block directly from the JS file by searching for the pattern `this.shadowRoot.innerHTML = \`...\``.

- **Flexible CSS Handling – Embedding vs. Linking:**  
  ElephantShadow allows you to choose how CSS is delivered:
  - **Embedding (Inline):** If you set the flag to embed CSS, the CSS content is included directly in the generated Shadow DOM inside a `<style>` tag.
  - **Linking (External):** If embedding is turned off, ElephantShadow will create a `<link rel="stylesheet">` element pointing to the appropriate CSS file. This can be useful if you prefer caching or want to serve CSS separately.

- **Automatic JavaScript Registration:**  
  The loaded JavaScript file is wrapped in a `<script type="module">` block that checks whether the custom element is already defined before registering it. This ensures the component is properly registered on the client side without duplication.

- **Full-Page Rendering:**  
  In addition to rendering single components, ElephantShadow provides a method to process an entire HTML page. It locates all custom elements (identified by a hyphen in the tag name) and replaces them with their SSR-generated output. Nested components are processed from the innermost outward.

---

## Usage Examples

### 1. Component with Separate Resources and Embedded CSS

**Author's HTML Input:**

```html
<my-card message="Hello from SSR">
  <p slot="body">This is some additional content for the card.</p>
</my-card>
```

**Files in Your Project:**

- **Template (`templates/my-card.html`):**

  ```html
  <div class="card">
    <p>{{message}}</p>
    <div class="card-body">
      <slot name="body">Default content for the card.</slot>
    </div>
  </div>
  ```

- **CSS (`css/my-card.css`):**

  ```css
  :host {
    display: block;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-family: sans-serif;
  }
  ```

- **JavaScript (`js/my-card.js`):**

  ```js
  customElements.define('my-card', class extends HTMLElement {});
  ```

**Processing & Resulting SSR Output (with CSS Embedded Inline):**

```html
<my-card message="Hello from SSR">
  <template shadowroot="open">
    <style>
      :host {
        display: block;
        padding: 10px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-family: sans-serif;
      }
    </style>
    <div class="card">
      <p>Hello from SSR</p>
      <div class="card-body">
        <p>This is some additional content for the card.</p>
      </div>
    </div>
  </template>
  <p slot="body">This is some additional content for the card.</p>
</my-card>
<script type="module">
if (!customElements.get('my-card')) {
  customElements.define('my-card', class extends HTMLElement {});
}
</script>
```

**Explanation:**  
- The template file is loaded and the placeholder `{{message}}` is replaced by the attribute value.
- The CSS file is also loaded and, because the embed flag is set to true, its content is wrapped in a `<style>` tag inside the Shadow DOM.
- The `<slot name="body">` is filled with the content provided by the author.
- The JavaScript file is included and wrapped in a module script for client-side registration.

---

### 2. Component with JS-Embedded Template and Linked CSS

In this example, there is no separate HTML template file. Instead, the template is embedded in the JS file, but the CSS is served as an external file.

**Author's HTML Input:**

```html
<my-component message="Dynamic message">
  <p>This is slotted content.</p>
</my-component>
```

**JavaScript File (`js/my-component.js`):**

```js
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
                    background: #e0f7fa;
                    border-radius: 5px;
                    font-family: sans-serif;
                }
            </style>
            <p>${this.getAttribute('message') || 'Default message'}</p>
            <div>
                <h3>Children:</h3>
                <slot></slot>
            </div>
        `;
    }
}

customElements.define('my-component', MyComponent);
```

**CSS File (`css/my-component.css`):**

```css
/* Additional styling that is served externally */
.my-component-extra {
    color: darkblue;
}
```

**Processing & Resulting SSR Output (with CSS Linked Externally):**

```html
<my-component message="Dynamic message">
  <template shadowroot="open">
    <!-- Note: Since there is no separate HTML template file, the template is extracted from the JS file -->
    <link rel="stylesheet" href="css/my-component.css">
    <style>
        :host {
            display: block;
            padding: 10px;
            background: #e0f7fa;
            border-radius: 5px;
            font-family: sans-serif;
        }
    </style>
    <p>Dynamic message</p>
    <div>
        <h3>Children:</h3>
        <slot></slot>
    </div>
  </template>
  <p>This is slotted content.</p>
</my-component>
<script type="module">
if (!customElements.get('my-component')) {
  customElements.define('my-component', class extends HTMLElement {});
}
</script>
```

**Explanation:**  
- The system does not find a separate template file; therefore, it extracts the template from the JS file.
- For CSS, it checks and finds an external CSS file. Since the embed flag is set to false, the CSS is not embedded inline but is instead linked via a `<link rel="stylesheet">` element.
- The component’s Shadow DOM is constructed from the extracted template, and slot content is inserted appropriately.
- The JS file is still wrapped and included for client-side registration.

---

### 3. Full-Page Rendering

You can use ElephantShadow to process an entire HTML page containing multiple custom elements. The `renderFullPage()` method or the static `init()` function will recursively transform all custom elements based on their resource files or embedded templates.

**Example HTML Page Input:**

```html
<!DOCTYPE html>
<html>
<head>
  <title>My SSR Page</title>
</head>
<body>
  <my-header message="Welcome to My Site"></my-header>
  <my-card message="Card Message">
    <p slot="body">Card content goes here.</p>
  </my-card>
  <my-component message="JS Template Example">
    <p>This content is in the default slot.</p>
  </my-component>
</body>
</html>
```

Assuming:
- Separate template and CSS files exist for `my-header` and `my-card`,
- And `my-component` uses an embedded template in its JS file (with CSS linked externally),

**Processing:**

You can process the page by calling:

```php
$pageOutput = $elephantShadow->renderFullPage($inputHtml, /* embedCss */ true);
```

Or, to initialize it automatically:

```php
ElephantShadow::init();
```

**Resulting SSR Page:**  
All custom elements in the page will be transformed into their SSR-generated versions with declarative Shadow DOM, complete with correctly embedded or linked CSS, and the JS registration code appended for each component
