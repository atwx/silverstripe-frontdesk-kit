<p class="fdk-count">
    <% if $FilterIsActive %>
        $Items.TotalItems <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_RECORDS_FOUND 'records found' %> –
        <a href="$Link" class="link link-primary"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_CLEAR_FILTERS 'Clear filters' %></a>
    <% else %>
        $Items.TotalItems <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_RECORDS 'records' %>
    <% end_if %>
</p>

<div class="fdk-table-wrapper overflow-x-auto">
    <table class="table table-zebra w-full">
        <thead>
            <tr>
                <% loop $Columns %>
                    <th>$Title</th>
                <% end_loop %>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <% if $ItemRows.Count %>
                <% loop $ItemRows %>
                    <tr id="fdk-row-$ID">
                        <% loop $Cells %>
                            <td>
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
