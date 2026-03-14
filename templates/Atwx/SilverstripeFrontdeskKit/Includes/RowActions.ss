<% if $Actions %>
<div class="fdk-row-actions-wrap dropdown dropdown-end">
    <label tabindex="0" class="btn btn-ghost btn-xs">···</label>
    <ul tabindex="0" class="dropdown-content menu p-1 shadow bg-base-100 rounded-box w-40 z-10">
        <% loop $Actions %>
            <% if $IsDelete %>
            <li>
                <a href="$Url"
                   class="text-error"
                   <% if $HasConfirm %>
                   hx-delete="$Url"
                   hx-target="closest tr"
                   hx-swap="outerHTML"
                   hx-confirm="$ConfirmMessage"
                   <% end_if %>>
                    $Label
                </a>
            </li>
            <% else_if $IsHtmx %>
            <li>
                <a hx-$Method="$Url"
                   hx-target="closest tr"
                   hx-swap="outerHTML"
                   <% if $HasConfirm %>hx-confirm="$ConfirmMessage"<% end_if %>>
                    $Label
                </a>
            </li>
            <% else %>
            <li><a href="$Url">$Label</a></li>
            <% end_if %>
        <% end_loop %>
    </ul>
</div>
<% end_if %>
