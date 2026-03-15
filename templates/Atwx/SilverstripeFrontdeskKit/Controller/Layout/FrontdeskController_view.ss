<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% loop $ViewActions %>
                <a href="$Link"
                   class="btn btn-sm <% if $Primary %>btn-primary<% else %>btn-ghost<% end_if %><% if $Active %> btn-active<% end_if %>"
                   <% if $Target %>target="$Target"<% end_if %>>$Title</a>
            <% end_loop %>
        </div>
    </div>

    $ViewContent

    <% loop $SubControllerData %>
    <section class="fdk-sublist mt-8">
        <h2 class="fdk-sublist-title">$Title</h2>
        <div id="fdk-sublist-$Segment"
             hx-get="$Url"
             hx-trigger="load"
             hx-swap="innerHTML">
            <span class="loading loading-spinner loading-sm text-base-content/30"></span>
        </div>
    </section>
    <% end_loop %>
</div>
