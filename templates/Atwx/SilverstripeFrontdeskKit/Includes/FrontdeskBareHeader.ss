<%-- Header without navigation (log in, error pages): logo or site title. --%>
<header class="fdk-header navbar px-4">
    <a href="$BaseHref" class="flex items-center gap-2 font-bold text-lg">
        <% if $FrontdeskLogo %>
            <img src="$FrontdeskLogo" alt="$SiteConfig.Title" class="h-8">
        <% else %>
            <% if $SiteConfig.Title %>$SiteConfig.Title<% else %>Frontdesk<% end_if %>
        <% end_if %>
    </a>
</header>
