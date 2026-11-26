<%-- A sub-controller list loaded via HTMX (record view, record pages and cards). --%>
<section class="fdk-sublist<% if $Compact %> fdk-sublist-compact<% else %> mt-8<% end_if %>">
    <% if $Title %><h2 class="fdk-sublist-title">$Title</h2><% end_if %>
    <div id="fdk-sublist-$Segment"
         hx-get="$Url"
         hx-trigger="load"
         hx-swap="innerHTML">
        <span class="loading loading-spinner loading-sm text-base-content/30"></span>
    </div>
</section>
