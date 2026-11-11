<%-- One-time message after an action (FlashMessage()). --%>
<% if $FlashMessage %>
    <div role="alert" class="alert <% if $FlashMessage.Type == 'error' %>alert-error<% else_if $FlashMessage.Type == 'info' %>alert-info<% else %>alert-success<% end_if %> mb-4">
        <span>$FlashMessage.Text</span>
    </div>
<% end_if %>
