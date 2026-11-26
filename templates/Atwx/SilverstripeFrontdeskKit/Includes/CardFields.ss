<%-- Field rows on a record card (layout 'tabs'): label and value, empty values left out. --%>
<dl class="fdk-card-fields grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-3 gap-y-1 text-sm">
    <% loop $Rows %>
    <dt class="text-base-content/60">$Label</dt>
    <dd class="break-words"><% if $Type == html %>$Value.RAW<% else %>$Value<% end_if %></dd>
    <% end_loop %>
</dl>
