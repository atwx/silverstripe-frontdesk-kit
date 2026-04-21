# Charts

Frontdesk-kit bundles [Chart.js](https://www.chartjs.org/) and auto-initialises any canvas carrying a `data-fdk-chart` attribute — on initial page load and after every HTMX swap.

## Quick Use

Render the shipped include with a JSON-encoded Chart.js config:

```ss
<% include Atwx\SilverstripeFrontdeskKit\Includes\Chart
    ChartTitle='Revenue (12 months)',
    ChartHeight='360px',
    ChartConfigJson=$RevenueChartJson %>
```

The include wraps the canvas in a DaisyUI card (`bg-base-100 rounded-box shadow-sm p-6`) with an optional title.

### Variables

| Name              | Description                                                        | Default  |
| ----------------- | ------------------------------------------------------------------ | -------- |
| `ChartConfigJson` | JSON-encoded Chart.js configuration (required)                     | —        |
| `ChartTitle`      | Optional heading shown above the chart                             | none     |
| `ChartHeight`     | CSS height for the chart container                                 | `320px`  |

## Providing Config From a Controller

```php
public function RevenueChart(): array
{
    return [
        'type' => 'line',
        'data' => [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'       => 'Revenue',
                    'data'        => $series,
                    'borderColor' => '#3b82f6',
                    'tension'     => 0.3,
                ],
            ],
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
        ],
    ];
}

public function RevenueChartJson(): string
{
    return json_encode($this->RevenueChart());
}
```

Since `maintainAspectRatio` is `false`, the chart fills the wrapper height set by `ChartHeight`.

## Inline Canvas (Without the Include)

If you need a custom wrapper — e.g. a filter dropdown alongside the chart — skip the include and drop the canvas yourself:

```ss
<div style="position: relative; height: 360px;">
    <canvas data-fdk-chart="$RevenueChartJson.ATT"></canvas>
</div>
```

Anything the init scanner finds via `[data-fdk-chart]` gets hydrated.

## Re-rendering on HTMX Swap

The initialiser destroys the previous `Chart` instance before re-creating it, so swapping a canvas into the DOM via HTMX works out of the box. Each canvas tracks its instance on `canvas.__fdkChart`.

## Accessing the Global

`window.Chart` exposes the Chart.js class for ad-hoc charts built by app-level JavaScript.
