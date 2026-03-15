<% if $Actions %>
<details class="fdk-row-actions-wrap dropdown dropdown-end">
    <summary class="btn btn-ghost btn-xs list-none">···</summary>
    <ul class="dropdown-content menu p-1 shadow bg-base-100 rounded-box w-44 z-10">
        <% loop $Actions %>
            <% if $IsDelete %>
            <li>
                <button type="button"
                        class="text-error w-full text-left"
                        data-delete-url="$Url.ATT"
                        data-row-id="$RowId"
                        onclick="fdkOpenDelete(this)">
                    $Label
                </button>
            </li>
            <% else_if $IsHtmx %>
            <li>
                <button type="button"
                        class="w-full text-left"
                        hx-get="$Url"
                        hx-target="#fdk-modal-content"
                        hx-swap="innerHTML"
                        hx-indicator="#fdk-modal-spinner"
                        onclick="document.getElementById('fdk-modal').showModal()">
                    $Label
                </button>
            </li>
            <% else %>
            <li><a href="$Url"<% if $HasTarget %> target="$Target"<% end_if %>>$Label</a></li>
            <% end_if %>
        <% end_loop %>
    </ul>
</details>
<% end_if %>
