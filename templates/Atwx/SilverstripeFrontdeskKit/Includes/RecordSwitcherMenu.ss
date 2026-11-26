<%-- RecordSwitcher as an entry of the sidebar menu (display 'menu'). --%>
<li class="fdk-record-switcher my-1">
    <div class="flex items-center gap-1 p-0 hover:bg-transparent">
        <% if $Options.Count > 1 && $IsSearchable %>
        <details class="dropdown flex-1 min-w-0" data-fdk-switcher-search>
            <summary class="btn btn-sm btn-ghost w-full justify-between flex-nowrap"><span class="truncate min-w-0">$ActiveTitle</span><% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Chevron %></summary>
            <div class="dropdown-content bg-base-100 rounded-box shadow z-10 w-72 p-2">
                <input type="search" class="input input-sm w-full mb-1" autocomplete="off"
                       placeholder="<%t Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher.SEARCH 'Search' %>"
                       aria-label="<%t Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher.SEARCH 'Search' %>">
                <ul class="menu w-full p-0 ms-0 ps-0 before:hidden max-h-80 overflow-y-auto flex-nowrap">
                    <% loop $Options %>
                    <li data-search="$SearchText.ATT"><a href="$Link"<% if $Active %> class="menu-active"<% end_if %>>$Title</a></li>
                    <% end_loop %>
                </ul>
                <p class="fdk-switcher-empty px-3 py-2 text-sm opacity-60" hidden><%t Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher.NO_MATCH 'No matches' %></p>
            </div>
        </details>
        <% else_if $Options.Count > 1 %>
        <details class="dropdown flex-1 min-w-0">
            <summary class="btn btn-sm btn-ghost w-full justify-between flex-nowrap"><span class="truncate min-w-0">$ActiveTitle</span><% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Chevron %></summary>
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
