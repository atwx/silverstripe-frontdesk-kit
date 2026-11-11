<%-- Record view arranged by a FieldLayout: one card per group. --%>
<% loop $ViewGroups %>
<section class="fdk-view-group card bg-base-100 border border-base-200 mb-4">
    <div class="card-body p-4">
        <% if $Title %><h2 class="card-title text-base">$Title</h2><% end_if %>
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
    </div>
</section>
<% end_loop %>
