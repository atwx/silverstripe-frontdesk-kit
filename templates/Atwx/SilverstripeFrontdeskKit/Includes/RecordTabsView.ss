<%-- Record view in the layout 'tabs': header with title and status, cards on the left, pages as tabs. --%>
<div class="fdk-page-header fdk-record-header flex-wrap gap-4 items-start">
    <div class="min-w-0">
        <h1 class="fdk-page-title">$Title</h1>
        <% if $HeaderFields %>
        <dl class="fdk-header-fields flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-base-content/70">
            <% loop $HeaderFields %>
            <div class="flex gap-1"><dt>$Label:</dt><dd class="font-medium">$Value</dd></div>
            <% end_loop %>
        </dl>
        <% end_if %>
    </div>
    <% if $HeaderStatus %>
    <dl class="fdk-header-status grid grid-cols-[auto_auto] gap-x-3 gap-y-1 text-sm ms-auto">
        <% loop $HeaderStatus %>
        <dt class="font-semibold text-end">$Title</dt>
        <dd><% if $Color %><span class="badge badge-sm badge-$Color">$Value</span><% else %>$Value<% end_if %></dd>
        <% end_loop %>
    </dl>
    <% end_if %>
</div>
<% if $RecordActions || $ViewActions %>
<div class="fdk-page-actions flex-wrap justify-end mb-4">
    <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordActionButtons %>
    <% loop $ViewActions %>
        <a href="$Link" class="btn btn-sm btn-ghost" <% if $Target %>target="$Target"<% end_if %>>$Title</a>
    <% end_loop %>
</div>
<% end_if %>

<% include Atwx\\SilverstripeFrontdeskKit\\Includes\\FlashMessage %>

<div class="fdk-record-tabs flex flex-col lg:flex-row gap-6 items-start">
    <% if $RecordCards || $RecordImage || $RecordSwitchers %>
    <aside class="fdk-record-cards w-full lg:w-96 shrink-0 flex flex-col gap-3">
        <% if $RecordImage %>
            <img src="$RecordImage" alt="" class="fdk-record-image w-24 h-24 object-cover rounded-box">
        <% end_if %>
        <% loop $RecordSwitchers %>
            <% if $IsSelect %>
                <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordSwitcherSelect %>
            <% else %>
                <ul class="menu bg-base-100 rounded-box w-full p-0"><% include Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordSwitcherMenu %></ul>
            <% end_if %>
        <% end_loop %>
        <% loop $RecordCards %>
        <details class="fdk-record-card group collapse collapse-arrow bg-base-100 border border-base-300 rounded-box" data-fdk-card="{$Up.CardStateKey}{$Key}" data-card-key="$Key"<% if $Open %> open<% end_if %>>
            <summary class="collapse-title min-h-0 py-3 pe-10">
                <span class="flex items-center gap-2">
                    <span class="font-semibold">$Title</span>
                    <% if $EditLink %>
                    <a href="$EditLink" class="fdk-card-edit link link-hover text-sm text-base-content/60" onclick="event.stopPropagation()"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_EDIT 'Edit' %></a>
                    <% end_if %>
                </span>
                <% if $Summary %><span class="fdk-card-summary group-open:hidden block text-sm text-base-content/70 font-normal truncate mt-1">$Summary</span><% end_if %>
            </summary>
            <div class="collapse-content text-sm">
                <% if $Content %>$Content<% else %><p class="text-base-content/50"><%t Atwx\SilverstripeFrontdeskKit\View\RecordCard.EMPTY 'No details yet' %></p><% end_if %>
            </div>
        </details>
        <% end_loop %>
    </aside>
    <% end_if %>
    <section class="fdk-record-page flex-1 min-w-0 w-full">
        <nav role="tablist" class="tabs tabs-box fdk-record-tablist mb-4">
            <% loop $RecordPages %>
                <% if $Type == page %>
                <a role="tab" href="$Link" class="tab<% if $Active %> tab-active<% end_if %>"<% if $Active %> aria-selected="true" aria-current="page"<% else %> aria-selected="false"<% end_if %>>$Title</a>
                <% end_if %>
            <% end_loop %>
        </nav>
        <% if $CurrentPage.EditLink %>
        <div class="flex justify-end mb-2">
            <a href="$CurrentPage.EditLink" class="btn btn-sm btn-primary"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_EDIT 'Edit' %></a>
        </div>
        <% end_if %>
        $PageContent
    </section>
</div>
