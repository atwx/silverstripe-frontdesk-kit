<%-- Record view arranged by a FieldLayout: a heading and a table per group. --%>
<% loop $ViewGroups %>
<section class="fdk-view-group mb-6">
    <% if $Title %><h2 class="fdk-sublist-title">$Title</h2><% end_if %>
    <div class="fdk-table-wrapper overflow-x-auto">
        <table class="table w-full">
            <tbody>
                <% loop $Rows %>
                <tr>
                    <th class="w-1/4 font-medium text-base-content/70 whitespace-nowrap">$Label</th>
                    <td><% if $Type == html %>$Value.RAW<% else %>$Value<% end_if %></td>
                </tr>
                <% end_loop %>
            </tbody>
        </table>
    </div>
</section>
<% end_loop %>
