<!doctype html>
<html lang="de" data-theme="light">
<head>
    <% base_tag %>
    $MetaTags(false)
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title>$Title</title>
    <link rel="stylesheet" href="$resourceURL('atwx/silverstripe-frontdesk-kit:dist/frontdesk.css')">
</head>
<body class="fdk-app bg-base-200 min-h-screen">
    <header class="fdk-header navbar bg-base-100 shadow-sm">
        <div class="navbar-start">
            <a href="/" class="navbar-item">
                <% if $Logo %>
                    <img src="$Logo" alt="Logo" class="h-8">
                <% else %>
                    <span class="font-bold text-lg">Home</span>
                <% end_if %>
            </a>
        </div>
        <div class="navbar-end">
            <ul class="menu menu-horizontal px-1">
                <% loop $MainNavigation %>
                    <li<% if $Active %> class="active"<% end_if %>>
                        <a href="$Link">$Title</a>
                    </li>
                <% end_loop %>
            </ul>
        </div>
    </header>

    <main class="fdk-main container mx-auto px-4 py-6">
        $Layout
    </main>

    <script src="$resourceURL('atwx/silverstripe-frontdesk-kit:dist/frontdesk.js')" defer></script>
</body>
</html>
