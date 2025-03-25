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
        // Check for a declarative shadow template inserted by ElephantShadow.
        const template = this.shadowRoot.querySelector('template');
        if (template) {
            // Clone the content into the shadow DOM and remove the template.
            this.shadowRoot.appendChild(template.content.cloneNode(true));
            template.remove();
        }
        this.render();
    }
    render() {
        const dynamicContainer = this.shadowRoot.getElementById('dynamic-content');
        if (dynamicContainer) {
            dynamicContainer.innerHTML = `
                <div>
                    <h3>This is from the JS file. We are progressively enhancing it:</h3>
                     <p>Display here the attribute value too: ${this.getAttribute('message') || 'Default message'}</p>
                    <button>Click me</button>
                </div>
            `;
            // Now attach the click event listener after the button is in the DOM.
            const button = dynamicContainer.querySelector('button');
            if (button) {
                button.addEventListener('click', () => {
                    const attrValue = this.getAttribute('message');
                    console.log('Attributwert:', attrValue);
                    alert('Attributwert: ' + attrValue);
                });
            }
        }
    }
}
customElements.define('my-component', MyComponent);