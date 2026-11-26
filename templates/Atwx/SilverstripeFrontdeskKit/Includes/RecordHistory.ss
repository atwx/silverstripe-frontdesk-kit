<%-- Page "History" of a record (config timeline_page): header like the record view, the whole timeline. --%>
<div class="fdk-page-header flex-wrap gap-4 items-start">
    <div class="min-w-0">
        <p class="text-sm"><a href="$BackLink" class="link link-hover">← $Title</a></p>
        <h1 class="fdk-page-title">$HistoryTitle: $Title</h1>
        <% if $HeaderFields %>
        <dl class="fdk-header-fields flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-base-content/70">
            <% loop $HeaderFields %><div class="flex gap-1"><dt>$Label:</dt><dd class="font-medium">$Value</dd></div><% end_loop %>
        </dl>
        <% end_if %>
    </div>
    <% if $HeaderStatus %>
    <dl class="fdk-header-status grid grid-cols-[auto_auto] gap-x-3 gap-y-1 text-sm ms-auto">
        <% loop $HeaderStatus %><dt class="font-semibold text-end">$Title</dt><dd>$Value</dd><% end_loop %>
    </dl>
    <% end_if %>
</div>
<div class="fdk-record-history max-w-4xl">
    <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Timeline %>
</div>
