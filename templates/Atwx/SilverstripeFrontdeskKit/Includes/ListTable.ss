<% if $ItemRows.Count %>
    <% loop $ItemRows %>
        <tr>
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
        <td colspan="99" class="text-center text-base-content/50 py-8"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.NO_RECORDS 'No records found.' %></td>
    </tr>
<% end_if %>
