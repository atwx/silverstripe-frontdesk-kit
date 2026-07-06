import Alpine from 'alpinejs';
import htmx from 'htmx.org';
import Chart from 'chart.js/auto';
import Treeselect from 'treeselectjs';
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

// ─── Checkbox tree ([data-fdk-checkbox-tree]) ────────────────────────────────
// Hierarchical, independent-node checkbox tree rendered by treeselectjs.
// Options come from an embedded JSON script block; the selection is written
// back to a hidden input as a JSON array of IDs.

function initFdkCheckboxTrees(root = document) {
    root.querySelectorAll('[data-fdk-checkbox-tree]').forEach((el) => {
        if (el.__fdkTree) return;
        el.__fdkTree = true;

        let options = [];
        try {
            options = JSON.parse(el.querySelector('[data-fdk-tree-options]').textContent);
        } catch (e) {
            console.error('fdk-checkbox-tree: invalid options JSON', e, el);
            return;
        }
        const input = el.querySelector('[data-fdk-tree-input]');
        const treeRoot = el.querySelector('[data-fdk-tree-root]');

        let value = [];
        try {
            value = input.value ? JSON.parse(input.value) : [];
        } catch (e) {
            value = [];
        }

        const treeselect = new Treeselect({
            parentHtmlContainer: treeRoot,
            value: Array.isArray(value) ? value : [],
            options: options,
            isIndependentNodes: true,
        });

        treeselect.srcElement.addEventListener('input', (e) => {
            input.value = JSON.stringify(e.detail);
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

// ─── Toggle all collapsible groups ([data-fdk-toggle-all]) ───────────────────
// Opens all matching <details> if any is closed, otherwise closes all.
// The attribute value may hold a custom selector (default: details.fdk-collapse).

function initFdkToggleAll(root = document) {
    root.querySelectorAll('[data-fdk-toggle-all]').forEach((btn) => {
        if (btn.__fdkToggleAll) return;
        btn.__fdkToggleAll = true;
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const selector = btn.getAttribute('data-fdk-toggle-all') || 'details.fdk-collapse';
            const all = document.querySelectorAll(selector);
            const anyClosed = Array.from(all).some((d) => !d.open);
            all.forEach((d) => {
                d.open = anyClosed;
            });
        });
    });
}

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
    initFdkCheckboxTrees();
    initFdkToggleAll();
});

document.addEventListener('htmx:afterSwap', (e) => {
    createIcons({ icons: fdkIcons });
    initFdkCharts(e.target || document);
    initFdkCheckboxTrees(e.target || document);
    initFdkToggleAll(e.target || document);
});
