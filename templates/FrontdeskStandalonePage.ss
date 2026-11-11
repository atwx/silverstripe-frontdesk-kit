<!DOCTYPE html>
<%-- Reduced page for StandaloneRecordController: no navigation, no login.
     Projects override this template to load their own stylesheet. --%>
<html lang="$ContentLocale">
<head>
    <% base_tag %>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>$Title</title>
    <link rel="stylesheet" href="$resourceURL('atwx/silverstripe-frontdesk-kit:client/dist/frontdesk.css')">
</head>
<body class="fdk-app">
<main class="fdk-main">
    $Layout
</main>
<script src="$resourceURL('atwx/silverstripe-frontdesk-kit:client/dist/frontdesk.js')" defer></script>
</body>
</html>
