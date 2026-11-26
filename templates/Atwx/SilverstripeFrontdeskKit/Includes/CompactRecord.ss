<%-- A related record on another record's card (FrontdeskController::compactView()). --%>
<div class="fdk-compact-record">
    <div class="flex items-center justify-between gap-2 mb-1">
        <a href="$ViewLink" class="link link-hover font-medium">$Record.Title</a>
        <% if $EditLink %>
        <a href="$EditLink" class="btn btn-ghost btn-xs" title="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_EDIT 'Edit' %>" aria-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_EDIT 'Edit' %>: $Record.Title.ATT"><i data-lucide="pencil" class="size-3.5"></i></a>
        <% end_if %>
    </div>
    <% if $Rows %>
    <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\CardFields Rows=$Rows %>
    <% end_if %>
</div>
