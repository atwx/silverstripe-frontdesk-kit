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

// ─── Filter bar triggers ─────────────────────────────────────────────────────
// Text fields reload the list while typing (delayed "input"); their "change" on
// leaving the field must not reload it a second time, or a row checkbox clicked
// right then is replaced. The search box of a searchable dropdown never reloads
// (its hidden input fires "change" when an option is picked).

const fdkTypedFields = 'input[type=text], input[type=search], input:not([type]), textarea';

window.fdkFilterChange = (el) => !(el instanceof Element && el.matches(fdkTypedFields));
window.fdkFilterInput = (el) => el instanceof Element && el.matches(fdkTypedFields) && !el.closest('.fdk-searchable');

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

// ─── Searchable record switcher ([data-fdk-switcher-search]) ────────────────
// A search box above the options filters them; Enter opens the first match,
// Escape closes the list.

document.addEventListener('toggle', (e) => {
    const details = e.target;
    if (details instanceof HTMLDetailsElement && details.open && details.matches('[data-fdk-switcher-search]')) {
        const input = details.querySelector('input[type=search]');
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        // The list is still hidden while daisyUI's open transition starts.
        requestAnimationFrame(() => input.focus());
    }
}, true);

document.addEventListener('input', (e) => {
    const details = e.target instanceof Element ? e.target.closest('[data-fdk-switcher-search]') : null;
    if (!details) return;
    const q = e.target.value.trim().toLowerCase();
    let shown = 0;
    details.querySelectorAll('li[data-search]').forEach((li) => {
        const match = !q || li.dataset.search.includes(q);
        li.hidden = !match;
        shown += match ? 1 : 0;
    });
    details.querySelector('.fdk-switcher-empty').hidden = shown > 0;
});

document.addEventListener('keydown', (e) => {
    const details = e.target instanceof Element ? e.target.closest('[data-fdk-switcher-search]') : null;
    if (!details) return;
    if (e.key === 'Escape') {
        details.open = false;
        details.querySelector('summary').focus();
    } else if (e.key === 'Enter' && e.target.matches('input[type=search]')) {
        e.preventDefault();
        const first = details.querySelector('li[data-search]:not([hidden]) a');
        if (first) window.location.href = first.href;
    }
});

// ─── Select all / none for checkbox sets ([data-fdk-select-all]) ────────────
// The attribute holds the label of "all", data-fdk-select-none the one of
// "none"; disabled checkboxes are left as they are.

function initFdkSelectAll(root = document) {
    root.querySelectorAll('[data-fdk-select-all]').forEach((set) => {
        if (set.__fdkSelectAll) return;
        set.__fdkSelectAll = true;
        const button = (label, checked) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'link link-hover link-primary';
            b.textContent = label;
            b.addEventListener('click', () => {
                set.querySelectorAll('input[type=checkbox]:not(:disabled)').forEach((c) => {
                    if (c.checked !== checked) {
                        c.checked = checked;
                        c.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });
            return b;
        };
        const bar = document.createElement('div');
        bar.className = 'fdk-select-all flex gap-3 mb-1 text-sm';
        bar.append(button(set.dataset.fdkSelectAll, true), button(set.dataset.fdkSelectNone || '–', false));
        set.parentNode.insertBefore(bar, set);
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
    initFdkSelectAll();
});

// Keep the chosen rows when the list is reloaded (filters, paging): the bulk
// form posts the checkboxes of the rows, so a reload must not lose them.
let fdkKeptIds = null;
document.addEventListener('htmx:beforeSwap', (e) => {
    const target = e.detail.target;
    if (target && target.id === 'fdk-list-region') {
        fdkKeptIds = new Set(Array.from(target.querySelectorAll('input[name="ids[]"]:checked'), (c) => c.value));
    }
});

document.addEventListener('htmx:afterSwap', (e) => {
    createIcons({ icons: fdkIcons });
    initFdkCharts(e.target || document);
    initFdkCheckboxTrees(e.target || document);
    initFdkToggleAll(e.target || document);
    initFdkSelectAll(e.target || document);
    if (fdkKeptIds && e.target && e.target.id === 'fdk-list-region') {
        e.target.querySelectorAll('input[name="ids[]"]').forEach((c) => {
            if (fdkKeptIds.has(c.value)) {
                c.checked = true;
            }
        });
        fdkKeptIds = null;
    }
});

// Export links: use the filters currently in the address bar (htmx updates
// them without reloading the page header), plus the link's own parameters.
document.addEventListener('click', (e) => {
    const link = e.target instanceof Element ? e.target.closest('a[data-fdk-keep-query]') : null;
    if (!link) {
        return;
    }
    const params = new URLSearchParams(window.location.search);
    params.delete('export');
    new URLSearchParams(link.getAttribute('data-fdk-keep-query') || '').forEach((value, key) => params.set(key, value));
    const query = params.toString();
    link.href = link.href.split('?')[0] + (query ? '?' + query : '');
});
