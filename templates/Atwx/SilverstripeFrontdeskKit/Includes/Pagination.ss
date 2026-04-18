<% if $ItemList.MoreThanOnePage %>
<div class="fdk-pagination flex justify-center mt-4">
    <div class="join">
        <% if $ItemList.NotFirstPage %>
            <a class="join-item btn btn-sm"
               href="$ItemList.PrevLink"
               hx-get="$ItemList.PrevLink"
               hx-target="#fdk-list-region"
               hx-swap="innerHTML"
               hx-push-url="true">«</a>
        <% end_if %>
        <% loop $ItemList.PaginationSummary %>
            <% if $CurrentBool %>
                <button class="join-item btn btn-sm btn-active">$PageNum</button>
            <% else %>
                <% if $Link %>
                    <a class="join-item btn btn-sm"
                       href="$Link"
                       hx-get="$Link"
                       hx-target="#fdk-list-region"
                       hx-swap="innerHTML"
                       hx-push-url="true">$PageNum</a>
                <% else %>
                    <button class="join-item btn btn-sm btn-disabled">…</button>
                <% end_if %>
            <% end_if %>
        <% end_loop %>
        <% if $ItemList.NotLastPage %>
            <a class="join-item btn btn-sm"
               href="$ItemList.NextLink"
               hx-get="$ItemList.NextLink"
               hx-target="#fdk-list-region"
               hx-swap="innerHTML"
               hx-push-url="true">»</a>
        <% end_if %>
    </div>
</div>
<% end_if %>
