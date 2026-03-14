import Alpine from 'alpinejs';
import htmx from 'htmx.org';

window.Alpine = Alpine;
window.htmx = htmx;

htmx.config.defaultSwapStyle = 'innerHTML';

Alpine.start();
