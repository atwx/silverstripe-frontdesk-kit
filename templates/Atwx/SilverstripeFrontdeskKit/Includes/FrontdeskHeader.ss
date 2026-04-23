<header class="fdk-header navbar">
    <div class="navbar-start">
        <a href="/">
            <% if $FrontdeskLogo %>
                <img src="$FrontdeskLogo" alt="Logo" class="h-8">
            <% else %>
                <span class="font-bold text-lg">Home</span>
            <% end_if %>
        </a>
    </div>
    <div class="navbar-center">
        <% if $MainNavigation %>
        <ul class="menu menu-horizontal px-1">
            <% loop $MainNavigation %>
                <li><a href="$Link"<% if $Active %> class="menu-active"<% end_if %>>$Title</a></li>
            <% end_loop %>
        </ul>
        <% end_if %>
    </div>
    <div class="navbar-end">
        <% if $CurrentUser %>
        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost gap-2">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary text-primary-content text-xs font-semibold">
                    $CurrentUserInitials
                </span>
                <span class="hidden sm:inline">$CurrentUserDisplayName</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box shadow z-50 w-52 p-2 mt-1">
                <li><a href="/profile">Profil</a></li>
                <% if $HasPermission('FDK_USERS_VIEW') %>
                <li><a href="/users">Benutzer</a></li>
                <% end_if %>
                <% if $HasPermission('FDK_GROUPS_VIEW') %>
                <li><a href="/groups">Gruppen</a></li>
                <% end_if %>
                <li><a href="/Security/logout">Logout</a></li>
            </ul>
        </div>
        <% end_if %>
    </div>
</header>
