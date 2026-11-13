<%-- One-time message after an action (FlashMessage()). --%>
<% if $FlashMessage %>
    <div role="alert" class="alert <% if $FlashMessage.Type == 'error' %>alert-error<% else_if $FlashMessage.Type == 'info' %>alert-info<% else %>alert-success<% end_if %> mb-4">
        <span>$FlashMessage.Text
            <% if $FlashMessage.Links.Count %>
                <% loop $FlashMessage.Links %><a href="$Link" class="link font-medium">$Title</a><% if not $IsLast %>, <% end_if %><% end_loop %>
            <% end_if %>
        </span>
    </div>
<% end_if %>
