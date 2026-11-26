<%-- RecordSwitcher as a select field (display 'select') with the add link next to it. --%>
<div class="fdk-record-switcher-select flex items-center gap-2 mb-4">
    <% if $Options.Count > 1 %>
    <select class="select select-sm flex-1 min-w-0" data-fdk-switcher-select
            aria-label="<%t Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher.SWITCH 'Switch record' %>">
        <% loop $Options %>
        <option value="$Link.ATT"<% if $Active %> selected<% end_if %>>$Title</option>
        <% end_loop %>
    </select>
    <% else %>
    <span class="flex-1 min-w-0 truncate font-medium">$ActiveTitle</span>
    <% end_if %>
    <% if $AddLink %><a href="$AddLink" class="btn btn-sm btn-ghost" title="$AddTitle.ATT">$AddTitle</a><% end_if %>
</div>
