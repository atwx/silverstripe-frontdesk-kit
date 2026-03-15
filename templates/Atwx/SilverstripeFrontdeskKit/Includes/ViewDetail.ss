<div class="fdk-table-wrapper overflow-x-auto">
    <table class="table table-zebra w-full">
        <tbody>
            <% loop $ViewFields %>
            <tr>
                <th class="w-1/4 font-medium text-base-content/70 whitespace-nowrap">$Label</th>
                <td>
                    <% if $Type == html %>
                        $Value.RAW
                    <% else %>
                        $Value
                    <% end_if %>
                </td>
            </tr>
            <% end_loop %>
        </tbody>
    </table>
</div>
