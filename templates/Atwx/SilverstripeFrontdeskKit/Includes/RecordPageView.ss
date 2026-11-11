<%-- Record view with record pages: fixed header, sidebar with the pages, current page. --%>
<div class="fdk-page-header">
    <div>
        <h1 class="fdk-page-title">$Title</h1>
        <% if $HeaderFields %>
        <dl class="fdk-header-fields flex flex-wrap gap-x-6 gap-y-1 mt-2 text-sm">
            <% loop $HeaderFields %>
            <div class="flex gap-2"><dt class="text-base-content/60">$Label</dt><dd class="font-medium">$Value</dd></div>
            <% end_loop %>
        </dl>
        <% end_if %>
    </div>
    <div class="fdk-page-actions">
        <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordActionButtons %>
        <% loop $ViewActions %>
            <a href="$Link" class="btn btn-sm btn-ghost" <% if $Target %>target="$Target"<% end_if %>>$Title</a>
        <% end_loop %>
    </div>
</div>

<% include Atwx\\SilverstripeFrontdeskKit\\Includes\\FlashMessage %>

<div class="fdk-record-pages flex flex-col md:flex-row gap-6 items-start">
    <aside class="fdk-record-sidebar w-full md:w-56 shrink-0">
        <% if $RecordImage %>
            <img src="$RecordImage" alt="" class="fdk-record-image w-36 h-36 object-cover rounded-box mb-4">
        <% end_if %>
        <ul class="menu bg-base-100 rounded-box w-full">
            <% loop $RecordPages %>
                <% if $Type == page %>
                    <li><a href="$Link"<% if $Active %> class="menu-active" aria-current="page"<% end_if %>>$Title</a></li>
                <% else_if $Type == switcher %>
                    <% with $Switcher %>
                    <li class="fdk-record-switcher my-1">
                        <div class="flex items-center gap-1 p-0 hover:bg-transparent">
                            <% if $Options.Count > 1 %>
                            <details class="dropdown flex-1">
                                <summary class="btn btn-sm btn-ghost w-full justify-between">$ActiveTitle</summary>
                                <ul class="menu dropdown-content bg-base-100 rounded-box shadow z-10 w-52">
                                    <% loop $Options %>
                                    <li><a href="$Link"<% if $Active %> class="menu-active"<% end_if %>>$Title</a></li>
                                    <% end_loop %>
                                </ul>
                            </details>
                            <% else %>
                            <span class="flex-1 px-3 font-medium">$ActiveTitle</span>
                            <% end_if %>
                            <% if $AddLink %><a href="$AddLink" class="btn btn-sm btn-ghost" title="$AddTitle">$AddTitle</a><% end_if %>
                        </div>
                    </li>
                    <% end_with %>
                <% else %>
                    <li class="menu-title p-0"><hr class="border-base-300 my-1"></li>
                <% end_if %>
            <% end_loop %>
        </ul>
    </aside>
    <section class="fdk-record-page flex-1 min-w-0">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-xl font-semibold">$CurrentPage.Title</h2>
            <% if $CurrentPage.EditLink %>
                <a href="$CurrentPage.EditLink" class="btn btn-sm btn-primary"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_EDIT 'Edit' %></a>
            <% end_if %>
        </div>
        $PageContent
    </section>
</div>
