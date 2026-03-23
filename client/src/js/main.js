import Alpine from 'alpinejs';
import htmx from 'htmx.org';

window.Alpine = Alpine;
window.htmx = htmx;

htmx.config.defaultSwapStyle = 'innerHTML';

// Defer Alpine.start() until DOMContentLoaded so that app-level module scripts
// can register Alpine.data() components before Alpine initialises the DOM.
document.addEventListener('DOMContentLoaded', () => Alpine.start());
