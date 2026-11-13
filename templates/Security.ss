<!DOCTYPE html>
<%-- Log in, MFA, lost and change password, log out and permission denied
     (Security controller) in the frontdesk layout, without navigation. --%>
<html lang="$FrontdeskLang" data-theme="light">
<head>
    <% base_tag %>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>$Title<% if $SiteConfig.Title %> - $SiteConfig.Title<% end_if %></title>
    <% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskAssets %>
</head>
<body class="fdk-app fdk-security">
<% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskBareHeader %>
<main class="fdk-main">
    <div class="fdk-security-card card bg-base-100 shadow-sm">
        <div class="card-body">
            <% if $Title %><h1 class="fdk-page-title mb-2">$Title</h1><% end_if %>
            <% if $Message %>
                <div role="alert" class="alert $FrontdeskAlertClass($MessageType) mb-2">$Message</div>
            <% end_if %>
            <% if $Content && $Content != $Message %>
                <div class="prose mb-2">$Content</div>
            <% end_if %>
            $Form
            <% if $SilvergateLoginLink %>
                <div class="divider"><%t Atwx\SilverstripeFrontdeskKit\Security\FrontdeskSecurityExtension.OR 'or' %></div>
                <a href="$SilvergateLoginLink" class="btn btn-outline w-full fdk-silvergate-login">
                    <%t Atwx\SilverstripeFrontdeskKit\Security\FrontdeskSecurityExtension.SILVERGATE 'Log in via Silvergate' %>
                </a>
            <% end_if %>
        </div>
    </div>
</main>
</body>
</html>
