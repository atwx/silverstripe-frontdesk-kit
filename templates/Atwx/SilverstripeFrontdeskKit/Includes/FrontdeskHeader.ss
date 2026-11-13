<%-- Header with main navigation (FrontdeskController.main_navigation):
     horizontal from lg up, in a burger menu below. secondary_navigation is in
     the user menu from lg up and in the burger menu below. --%>
<header class="fdk-header navbar px-2 sm:px-4 gap-2">
    <div class="navbar-start w-auto flex-1 lg:flex-none gap-1 min-w-0">
        <% if $CurrentMember %><% if $FrontdeskMainNavigation || $FrontdeskSecondaryNavigation %>
        <div class="dropdown lg:hidden">
            <div tabindex="0" role="button" class="btn btn-ghost btn-square" aria-label="<%t Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation.MENU 'Menu' %>">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
            </div>
            <ul tabindex="0" class="fdk-burger-menu menu dropdown-content bg-base-100 rounded-box shadow z-50 mt-2 w-72 max-w-[calc(100vw-1rem)] p-2">
                <% loop $FrontdeskMainNavigation %>
                    <% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskNavEntry %>
                <% end_loop %>
                <% if $FrontdeskSecondaryNavigation %>
                    <li class="menu-title"><%t Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation.MORE 'More' %></li>
                    <% loop $FrontdeskSecondaryNavigation %>
                        <% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskNavEntry %>
                    <% end_loop %>
                <% end_if %>
            </ul>
        </div>
        <% end_if %><% end_if %>
        <a href="$BaseHref" class="flex items-center gap-2 font-bold text-lg px-2 truncate">
            <% if $FrontdeskLogo %>
                <img src="$FrontdeskLogo" alt="$SiteConfig.Title" class="h-8">
            <% else %>
                <% if $SiteConfig.Title %>$SiteConfig.Title<% else %>Home<% end_if %>
            <% end_if %>
        </a>
    </div>
    <div class="navbar-center hidden lg:flex flex-1 min-w-0">
        <% if $CurrentMember && $FrontdeskMainNavigation %>
        <ul class="menu menu-horizontal flex-nowrap px-1">
            <% loop $FrontdeskMainNavigation %>
                <% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskNavEntry %>
            <% end_loop %>
        </ul>
        <% end_if %>
    </div>
    <div class="navbar-end w-auto flex-none">
        <% if $CurrentMember %>
        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost gap-2">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary text-primary-content text-xs font-semibold">
                    $FrontdeskUserInitials
                </span>
                <span class="hidden sm:inline whitespace-nowrap">$FrontdeskUserName</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box shadow z-50 w-56 p-2 mt-1">
                <% if $FrontdeskSecondaryNavigation %>
                    <% loop $FrontdeskSecondaryNavigation %>
                        <% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskNavEntry ExtraClass='max-lg:hidden' %>
                    <% end_loop %>
                    <li class="max-lg:hidden"><hr class="my-1 p-0 border-base-200"></li>
                <% end_if %>
                <li><a href="profile"><%t Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation.PROFILE 'Profile' %></a></li>
                <% if $HasPerm('FDK_USERS_VIEW') %>
                <li><a href="users"><%t Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation.USERS 'Users' %></a></li>
                <% end_if %>
                <% if $HasPerm('FDK_GROUPS_VIEW') %>
                <li><a href="groups"><%t Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation.GROUPS 'Groups' %></a></li>
                <% end_if %>
                <% if $HasPerm('ADMIN') %>
                <li><a href="site-config"><%t Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation.SETTINGS 'Settings' %></a></li>
                <% end_if %>
                <li><a href="Security/logout"><%t Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation.LOGOUT 'Log out' %></a></li>
            </ul>
        </div>
        <% else %>
        <a href="Security/login" class="btn btn-primary btn-sm"><%t Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation.LOGIN 'Log in' %></a>
        <% end_if %>
    </div>
</header>
