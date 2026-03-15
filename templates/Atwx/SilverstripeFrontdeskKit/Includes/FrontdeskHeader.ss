<header class="fdk-header navbar bg-base-100 shadow-sm">
    <div class="navbar-start">
        <a href="/" class="navbar-item">
            <% if $FrontdeskLogo %>
                <img src="$FrontdeskLogo" alt="Logo" class="h-8">
            <% else %>
                <span class="font-bold text-lg">Home</span>
            <% end_if %>
        </a>
    </div>
    <div class="navbar-end">
        <% if $MainNavigation %>
        <ul class="menu menu-horizontal px-1">
            <% loop $MainNavigation %>
                <li><a href="$Link"<% if $Active %> class="active"<% end_if %>>$Title</a></li>
            <% end_loop %>
        </ul>
        <% end_if %>
    </div>
</header>
