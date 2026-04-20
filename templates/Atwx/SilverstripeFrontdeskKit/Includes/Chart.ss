<div class="fdk-chart-wrapper bg-base-100 rounded-box shadow-sm p-6">
    <% if $ChartTitle %><h2 class="text-sm opacity-60 mb-4">$ChartTitle</h2><% end_if %>
    <div style="position: relative; height: <% if $ChartHeight %>$ChartHeight<% else %>320px<% end_if %>;">
        <canvas data-fdk-chart="$ChartConfigJson.ATT"></canvas>
    </div>
</div>
