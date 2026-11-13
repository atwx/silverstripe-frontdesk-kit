<p class="fdk-count">
    <% if $FilterIsActive %>
        $Items.TotalItems <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_RECORDS_FOUND 'records found' %> –
        <a href="$Link" class="link link-primary"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_CLEAR_FILTERS 'Clear filters' %></a>
    <% else %>
        $Items.TotalItems <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_RECORDS 'records' %>
    <% end_if %>
</p>

<% if $SummaryStats %>
<div class="fdk-summary-stats text-sm flex flex-wrap gap-x-4 gap-y-1 mb-2">
    <% loop $SummaryStats %>
        <span><span class="opacity-60">$Label:</span> <strong>$Value</strong><% if $SubLabel %> <span class="opacity-60">($SubLabel)</span><% end_if %></span>
    <% end_loop %>
</div>
<% end_if %>

<div class="fdk-table-wrapper">
    <table class="table table-zebra w-full fdk-stack">
        <thead>
            <tr>
                <% if $Top.BulkActions.Count %>
                    <th class="w-8">
                        <input type="checkbox" class="checkbox checkbox-sm" aria-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_SELECT_ALL 'Select all' %>"
                               onclick="document.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(c => c.checked = this.checked)">
                    </th>
                <% end_if %>
                <% loop $Top.ColumnHeaders %>
                    <% if $SortLink %>
                    <th<% if $AriaSort %> aria-sort="$AriaSort"<% end_if %>><a href="$SortLink" class="fdk-sort-link inline-flex items-center gap-1">$Title<% if $SortDir == 'asc' %> <span aria-hidden="true">▲</span><% else_if $SortDir == 'desc' %> <span aria-hidden="true">▼</span><% end_if %></a></th>
                    <% else %>
                    <th>$Title</th>
                    <% end_if %>
                <% end_loop %>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <% if $ItemRows.Count %>
                <% loop $ItemRows %>
                    <% if $GroupTitle %>
                    <tr class="fdk-group-row"><th colspan="99" class="bg-base-200">$GroupTitle</th></tr>
                    <% end_if %>
                    <tr id="fdk-row-$ID">
                        <% if $Top.BulkActions.Count %>
                            <td class="fdk-row-select">
                                <input type="checkbox" class="checkbox checkbox-sm" name="ids[]" value="$ID" form="fdk-bulk-form"
                                       aria-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_SELECT_RECORD 'Select record' %> $ID">
                            </td>
                        <% end_if %>
                        <% loop $Cells %>
                            <td data-label="$Label.ATT"<% if $IsEmpty %> class="fdk-cell-empty"<% end_if %>>
                                <% if $HasLink %><a href="$Link">$Value</a><% else %>$Value<% end_if %>
                            </td>
                        <% end_loop %>
                        <td class="fdk-row-actions">
                            <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\RowActions Actions=$RowActions %>
                        </td>
                    </tr>
                <% end_loop %>
            <% else %>
                <tr>
                    <td colspan="99" class="text-center text-base-content/50 py-8"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.NO_RECORDS 'No records found.' %></td>
                </tr>
            <% end_if %>
        </tbody>
    </table>
</div>

<% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Pagination ItemList=$Items %>
