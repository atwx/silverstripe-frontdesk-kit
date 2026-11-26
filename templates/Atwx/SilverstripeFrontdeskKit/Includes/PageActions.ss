<%-- Actions of a list page: buttons, and the menu "Export ▾" (exports in code, saved exports,
     "Set up export …"). Export links keep the current filters (data-fdk-keep-query). --%>
<% if $Actions %><% loop $Actions %>
    <% if $MenuItems %>
    <details class="dropdown dropdown-end fdk-export-menu">
        <summary class="btn btn-sm btn-ghost flex-nowrap">$Title <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Chevron %></summary>
        <ul class="dropdown-content menu bg-base-100 rounded-box shadow z-20 w-64 p-2">
            <% loop $MenuItems %>
            <li><a href="$Link" target="_blank" data-fdk-keep-query="$ExtraQuery.ATT">$Title</a></li>
            <% end_loop %>
            <% if $SavedItems %>
            <li class="menu-title"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORTS_SAVED 'Own exports' %></li>
            <% loop $SavedItems %>
            <li><a href="$Link" target="_blank" data-fdk-keep-query="$ExtraQuery.ATT">$Title</a></li>
            <% end_loop %>
            <% end_if %>
            <% if $CreateLink %>
            <li class="border-t border-base-200 mt-1 pt-1"><a href="$CreateLink" data-fdk-export-create><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_CREATE 'Set up export …' %></a></li>
            <li><a href="$ManageLink"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORTS_MANAGE 'Manage exports' %></a></li>
            <% end_if %>
        </ul>
    </details>
    <% else %>
    <a href="$Link"
       class="btn btn-sm<% if $Primary %> btn-primary<% else %> btn-ghost<% end_if %>"
       <% if $Target %>target="$Target"<% end_if %>
       <% if $KeepQuery %>data-fdk-keep-query="$ExtraQuery.ATT"<% end_if %>>$Title</a>
    <% end_if %>
<% end_loop %><% end_if %>
