import Alpine from 'alpinejs';
import htmx from 'htmx.org';
import { createIcons, Pencil, Trash2, ExternalLink, Github } from 'lucide';

window.Alpine = Alpine;
window.htmx = htmx;

htmx.config.defaultSwapStyle = 'innerHTML';

const fdkIcons = { Pencil, Trash2, ExternalLink, Github };

document.addEventListener('DOMContentLoaded', () => {
    Alpine.start();
    createIcons({ icons: fdkIcons });
});

document.addEventListener('htmx:afterSwap', () => createIcons({ icons: fdkIcons }));
