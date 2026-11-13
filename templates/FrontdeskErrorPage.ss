<!DOCTYPE html>
<%-- Error page (404, 403, 500 …) of FrontdeskErrorExtension. --%>
<html lang="$FrontdeskLang" data-theme="light">
<head>
    <% base_tag %>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>$Title<% if $SiteConfig.Title %> - $SiteConfig.Title<% end_if %></title>
    <% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskAssets %>
</head>
<body class="fdk-app fdk-error">
<% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskBareHeader %>
<main class="fdk-main">
    <div class="fdk-security-card card bg-base-100 shadow-sm">
        <div class="card-body">
            <p class="text-sm opacity-60">$Code</p>
            <h1 class="fdk-page-title">$Title</h1>
            <p>$Text</p>
            <% if $Detail %><p class="text-sm opacity-60">$Detail</p><% end_if %>
            <div class="card-actions mt-2">
                <a href="$BaseHref" class="btn btn-primary"><%t Atwx\SilverstripeFrontdeskKit\Security\FrontdeskErrorExtension.HOME 'To the start page' %></a>
            </div>
        </div>
    </div>
</main>
</body>
</html>
