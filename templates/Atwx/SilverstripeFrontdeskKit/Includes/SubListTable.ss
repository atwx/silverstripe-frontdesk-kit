<% if $Top.FilterForm %>
<form class="fdk-filter-bar"
      hx-get="$Top.Link"
      hx-target="$Top.HtmxTarget"
      hx-trigger="change, submit"
      hx-swap="innerHTML"
      method="get"
      action="$Top.Link">
    <% loop $Top.FilterForm.Fields %>
        <div class="fdk-filter-field">
            $FieldHolder
        </div>
    <% end_loop %>
    <% if $Top.FilterIsActive %>
        <a href="$Top.Link"
           hx-get="$Top.Link"
           hx-target="$Top.HtmxTarget"
           hx-swap="innerHTML"
           class="btn btn-ghost btn-sm"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_RESET 'Reset' %></a>
    <% end_if %>
</form>
<% end_if %>

<p class="fdk-count">
    <% if $Top.FilterIsActive %>
        $Items.TotalItems <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_RECORDS_FOUND 'records found' %>
    <% else %>
        $Items.TotalItems <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_RECORDS 'records' %>
    <% end_if %>
</p>

<div class="fdk-table-wrapper">
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
            <% if $Top.ItemRows.Count %>
                <% loop $Top.ItemRows %>
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

<% if $Items.MoreThanOnePage %>
<div class="fdk-pagination flex justify-center mt-4">
    <div class="join">
        <% if $Items.NotFirstPage %>
            <a class="join-item btn btn-sm"
               href="$Items.PrevLink"
               hx-get="$Items.PrevLink"
               hx-target="$Top.HtmxTarget"
               hx-swap="innerHTML">«</a>
        <% end_if %>
        <% loop $Items.PaginationSummary %>
            <% if $CurrentBool %>
                <button class="join-item btn btn-sm btn-active">$PageNum</button>
            <% else %>
                <% if $Link %>
                    <a class="join-item btn btn-sm"
                       href="$Link"
                       hx-get="$Link"
                       hx-target="$Top.HtmxTarget"
                       hx-swap="innerHTML">$PageNum</a>
                <% else %>
                    <button class="join-item btn btn-sm btn-disabled">…</button>
                <% end_if %>
            <% end_if %>
        <% end_loop %>
        <% if $Items.NotLastPage %>
            <a class="join-item btn btn-sm"
               href="$Items.NextLink"
               hx-get="$Items.NextLink"
               hx-target="$Top.HtmxTarget"
               hx-swap="innerHTML">»</a>
        <% end_if %>
    </div>
</div>
<% end_if %>
