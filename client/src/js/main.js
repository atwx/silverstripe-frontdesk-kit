import Alpine from 'alpinejs';
import htmx from 'htmx.org';
import Chart from 'chart.js/auto';
import { createIcons, Pencil, Trash2, ExternalLink, Github, KeyRound, Download, FileText, Printer } from 'lucide';

window.Alpine = Alpine;
window.htmx = htmx;
window.Chart = Chart;

htmx.config.defaultSwapStyle = 'innerHTML';

const fdkIcons = { Pencil, Trash2, ExternalLink, Github, KeyRound, Download, FileText, Printer };

function initFdkCharts(root = document) {
    root.querySelectorAll('[data-fdk-chart]').forEach((canvas) => {
        if (canvas.__fdkChart) {
            canvas.__fdkChart.destroy();
        }
        try {
            const config = JSON.parse(canvas.getAttribute('data-fdk-chart'));
            canvas.__fdkChart = new Chart(canvas, config);
        } catch (err) {
            console.error('fdk-chart: invalid config', err, canvas);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    Alpine.start();
    createIcons({ icons: fdkIcons });
    initFdkCharts();
});

document.addEventListener('htmx:afterSwap', (e) => {
    createIcons({ icons: fdkIcons });
    initFdkCharts(e.target || document);
});
