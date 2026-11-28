<%-- Menu "Reports" of a list (ListReports()): reports in groups, with description. --%>
<% if $ListReports.Count %>
<details class="dropdown fdk-reports-menu">
    <summary class="btn btn-sm btn-ghost">
        <i data-lucide="file-text" class="size-4" aria-hidden="true"></i>
        <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.REPORTS 'Reports' %> ▾
    </summary>
    <ul class="dropdown-content menu bg-base-100 rounded-box shadow z-20 w-80 max-h-[70vh] overflow-y-auto flex-nowrap p-2">
        <% loop $ListReports %>
            <% if $Title %><li class="menu-title">$Title</li><% end_if %>
            <% loop $Reports %>
            <li>
                <a href="$Link" class="flex flex-col items-start gap-0<% if $Active %> menu-active<% end_if %>"<% if $Active %> aria-current="page"<% end_if %>>
                    <span>$Title</span>
                    <% if $Description %><span class="text-xs opacity-70">$Description</span><% end_if %>
                </a>
            </li>
            <% end_loop %>
        <% end_loop %>
    </ul>
</details>
<% end_if %>
