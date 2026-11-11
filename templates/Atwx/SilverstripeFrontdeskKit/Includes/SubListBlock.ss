<%-- A sub-controller list loaded via HTMX (record view and record pages). --%>
<section class="fdk-sublist mt-8">
    <h2 class="fdk-sublist-title">$Title</h2>
    <div id="fdk-sublist-$Segment"
         hx-get="$Url"
         hx-trigger="load"
         hx-swap="innerHTML">
        <span class="loading loading-spinner loading-sm text-base-content/30"></span>
    </div>
</section>
