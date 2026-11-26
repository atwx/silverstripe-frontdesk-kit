<%-- "⋯" menu of the record header: History (config timeline_page), view actions and record actions moved here. --%>
<% if $MoreMenu %><% with $MoreMenu %>
<details class="dropdown dropdown-end fdk-record-more">
    <summary class="btn btn-sm btn-ghost list-none" aria-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.MORE_MENU 'More' %>"
             title="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.MORE_MENU 'More' %>">⋯</summary>
    <ul class="dropdown-content menu bg-base-100 rounded-box shadow z-20 w-56 p-2">
        <% loop $Links %>
        <li><a href="$Link"<% if $Target %> target="$Target"<% end_if %>>$Title</a></li>
        <% end_loop %>
        <% loop $Actions %>
        <li><button type="button" onclick="this.closest('details').removeAttribute('open'); document.getElementById('fdk-record-action-$Name').showModal()">$Label</button></li>
        <% end_loop %>
    </ul>
</details>
<% end_with %><% end_if %>
