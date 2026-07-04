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

// ─── Checkbox tree ([data-fdk-checkbox-tree]) ────────────────────────────────
// Renders a dropdown with a hierarchical, independent-node checkbox tree.
// Options come from an embedded JSON script block; the selection is written
// back to a hidden input as a JSON array of IDs.

function fdkBranchHasSelected(nodes, selected) {
    return nodes.some((n) =>
        selected.has(Number(n.value)) || (n.children && fdkBranchHasSelected(n.children, selected))
    );
}

function fdkBuildTreeList(nodes, selected, onChange) {
    const ul = document.createElement('ul');
    ul.className = 'fdk-tree-list';
    nodes.forEach((node) => {
        const li = document.createElement('li');
        const row = document.createElement('div');
        row.className = 'flex items-center gap-1 py-0.5';

        const hasChildren = node.children && node.children.length > 0;
        let childList = null;
        if (hasChildren) {
            childList = fdkBuildTreeList(node.children, selected, onChange);
            childList.className += ' ms-6';
            const open = fdkBranchHasSelected(node.children, selected);
            if (!open) childList.classList.add('hidden');

            const caret = document.createElement('button');
            caret.type = 'button';
            caret.className = 'btn btn-ghost btn-xs px-1 w-5';
            caret.textContent = open ? '▾' : '▸';
            caret.addEventListener('click', (e) => {
                e.preventDefault();
                const nowHidden = childList.classList.toggle('hidden');
                caret.textContent = nowHidden ? '▸' : '▾';
            });
            row.appendChild(caret);
        } else {
            const spacer = document.createElement('span');
            spacer.className = 'inline-block w-5 shrink-0';
            row.appendChild(spacer);
        }

        const label = document.createElement('label');
        label.className = 'flex items-center gap-2 cursor-pointer min-w-0';
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.className = 'checkbox checkbox-sm';
        cb.checked = selected.has(Number(node.value));
        cb.addEventListener('change', () => {
            if (cb.checked) {
                selected.add(Number(node.value));
            } else {
                selected.delete(Number(node.value));
            }
            onChange();
        });
        const text = document.createElement('span');
        text.className = 'truncate text-sm';
        text.textContent = node.name;
        label.appendChild(cb);
        label.appendChild(text);
        row.appendChild(label);

        li.appendChild(row);
        if (childList) li.appendChild(childList);
        ul.appendChild(li);
    });
    return ul;
}

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
        const summary = el.querySelector('[data-fdk-tree-summary]');

        let initial = [];
        try {
            initial = input.value ? JSON.parse(input.value) : [];
        } catch (e) {
            initial = [];
        }
        const selected = new Set((Array.isArray(initial) ? initial : []).map(Number));

        const update = () => {
            input.value = JSON.stringify([...selected]);
            if (summary) {
                summary.textContent = selected.size > 0 ? selected.size + ' ausgewählt' : 'Auswählen…';
            }
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        treeRoot.appendChild(fdkBuildTreeList(options, selected, update));
        input.value = JSON.stringify([...selected]);
        if (summary) {
            summary.textContent = selected.size > 0 ? selected.size + ' ausgewählt' : 'Auswählen…';
        }
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
