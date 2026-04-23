import Alpine from 'alpinejs';
import htmx from 'htmx.org';
import Chart from 'chart.js/auto';
import { createIcons, Pencil, Trash2, ExternalLink, Github, KeyRound, Download, FileText, Printer, Minimize2, Maximize2 } from 'lucide';

window.Alpine = Alpine;
window.htmx = htmx;
window.Chart = Chart;

htmx.config.defaultSwapStyle = 'innerHTML';

window.fdkSearchable = () => ({
    open: false,
    value: '',
    selected: '',
    query: '',
    empty: '',
    options: [],
    init() {
        const ds = this.$el.dataset;
        this.value = ds.value || '';
        this.selected = ds.selected || '';
        this.empty = ds.empty || '';
        let opts = [];
        try {
            opts = JSON.parse(ds.options || '[]');
        } catch (e) {
            console.error('fdkSearchable: invalid options JSON', e);
        }
        this.options = [{ value: '', label: this.empty }, ...opts];
    },
    filtered() {
        const q = this.query.trim().toLowerCase();
        if (!q) return this.options;
        return this.options.filter(o => (o.label || '').toLowerCase().includes(q));
    },
    pick(opt) {
        this.value = opt.value;
        this.selected = opt.label;
        this.query = '';
        this.open = false;
        this.$nextTick(() => {
            this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
        });
    },
});

const fdkIcons = { Pencil, Trash2, ExternalLink, Github, KeyRound, Download, FileText, Printer, Minimize2, Maximize2 };

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
