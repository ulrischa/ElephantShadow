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
        // Attach a click event listener to the button
        // Klick-Handler hinzufügen
        this.shadowRoot.querySelector('button').addEventListener('click', () => {
            const attrValue = this.getAttribute('message');
            console.log('Attributwert:', attrValue);
            alert('Attributwert: ' + attrValue);
          });
    }
    render() {
        // Style will be inserted here
        this.shadowRoot.innerHTML = `
            <p>Display here the attribute value too: ${this.getAttribute('message') || 'Default message'}</p>
            <div>
                <h3>Child Elements:</h3>
                <ul>
                    <li>Item 1</li>
                    <li>Item 2</li>
                    <li>Item 3</li>
                </ul>
                <button>Click me</button>
            </div>
            <slot></slot>
        `;
    }
}
customElements.define('my-component', MyComponent);