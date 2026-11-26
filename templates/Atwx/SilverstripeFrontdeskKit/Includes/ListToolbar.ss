<%-- Compact list toolbar (config filter_panel): search with search area, button "Filter (n)",
     view select, save view, columns; below the filter panel (a drawer on phones).
     The filter form uses "display: contents", so the view controls (own forms) are not nested in it. --%>
<div class="fdk-list-toolbar flex flex-wrap items-center gap-2 mb-3">
    <form id="fdk-filter-form" class="contents"
          hx-get="$Link"
          hx-target="#fdk-list-region"
          hx-trigger="change[fdkFilterChange(target)], submit, input[fdkFilterInput(target)] delay:400ms"
          hx-sync="this:replace"
          hx-swap="innerHTML"
          hx-push-url="true"
          method="get"
          action="$Link">
        <% loop $FilterHiddenFields %>$Field<% end_loop %>
        <% if $SearchBox %><% with $SearchBox %>
        <div class="fdk-list-search join flex-1 min-w-56 max-w-xl" role="search">
            <input type="search" name="$Name" value="$Value.ATT" class="input input-sm join-item w-full" autocomplete="off"
                   placeholder="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.SEARCH_PLACEHOLDER 'Search' %>"
                   aria-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.SEARCH_PLACEHOLDER 'Search' %>">
            <% if $Scopes %>
            <select name="$ScopeName" class="select select-sm join-item w-auto min-w-32 shrink-0"
                    aria-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.SEARCH_SCOPE 'Search in' %>">
                <% loop $Scopes %><option value="$Key.ATT"<% if $Selected %> selected<% end_if %>>$Title</option><% end_loop %>
            </select>
            <% end_if %>
        </div>
        <% end_with %><% end_if %>
        <% if $PanelFilters %>
        <button type="button" class="btn btn-sm fdk-filter-button" data-fdk-filter-toggle aria-controls="fdk-filter-panel"
                aria-expanded="<% if $ActiveFilterCount %>true<% else %>false<% end_if %>">
            <i data-lucide="filter" class="size-4" aria-hidden="true"></i>
            <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_FILTER 'Filter' %>
            <span class="badge badge-sm badge-primary" data-fdk-filter-count<% if not $ActiveFilterCount %> hidden<% end_if %>>$ActiveFilterCount</span>
        </button>
        <div id="fdk-filter-panel" class="fdk-filter-panel basis-full order-last<% if $ActiveFilterCount %> lg-open<% end_if %>"
             aria-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_FILTER 'Filter' %>">
            <div class="fdk-filter-panel-head flex items-center justify-between mb-2 lg:hidden">
                <span class="font-semibold"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_FILTER 'Filter' %></span>
                <button type="button" class="btn btn-sm btn-ghost" data-fdk-filter-toggle
                        aria-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_CLOSE 'Close' %>">✕</button>
            </div>
            <div class="fdk-filter-panel-fields">
                <% loop $PanelFilters %>
                <div class="fdk-filter-field" data-fdk-filter-name="$Name.ATT"<% if not $Shown %> hidden<% end_if %>>$FormField.FieldHolder</div>
                <% end_loop %>
                <div class="fdk-filter-add" data-fdk-no-submit>
                    <label class="sr-only" for="fdk-add-filter"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.FILTER_ADD 'Add filter' %></label>
                    <select id="fdk-add-filter" class="select select-sm" data-fdk-add-filter>
                        <option value="">+ <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.FILTER_ADD 'Add filter' %></option>
                        <% loop $PanelFilters %><% if not $Shown %><option value="$Name.ATT">$Label</option><% end_if %><% end_loop %>
                    </select>
                </div>
            </div>
        </div>
        <% end_if %>
    </form>
    <% if $ListViews.Count %>
    <label class="fdk-list-view-select flex items-center gap-2" data-fdk-no-submit>
        <span class="text-sm"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW 'View' %></span>
        <select class="select select-sm w-auto" data-fdk-navigate>
            <% loop $ListViews %><option value="$Link.ATT"<% if $Active %> selected<% end_if %>>$Title</option><% end_loop %>
        </select>
    </label>
    <% if $ActiveListViewEntry.DeleteLink %>
    <form method="post" action="$ActiveListViewEntry.DeleteLink" class="inline"
          onsubmit="return confirm('<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_CONFIRM_DELETE 'Delete this view?' %>')">
        <input type="hidden" name="SecurityID" value="$SecurityTokenValue">
        <button type="submit" class="btn btn-sm btn-ghost" title="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_DELETE 'Delete view' %>">✕</button>
    </form>
    <% end_if %>
    <% end_if %>
    <% if $CanSaveListViews %>
    <button type="button" class="btn btn-sm btn-ghost"
            onclick="var d=document.getElementById('fdk-save-view'); var box=d.querySelector('.modal-box'); box.replaceChildren(document.getElementById('fdk-save-view-form').content.cloneNode(true)); box.querySelector('[name=Query]').value=window.location.search.substring(1); d.showModal()">
        + <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_SAVE 'Save view' %>
    </button>
    <% end_if %>
    <% if $CanChooseColumns %>
        <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\ColumnChoice %>
    <% end_if %>
</div>
<div class="fdk-filter-backdrop" data-fdk-filter-toggle hidden></div>
<% include Atwx\\SilverstripeFrontdeskKit\\Includes\\SaveViewDialog %>
