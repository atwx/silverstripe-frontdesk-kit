<%-- A related record shown on another record's page (FrontdeskController::embeddedView()). --%>
<section class="fdk-embedded-record mb-8">
    <div class="flex items-center justify-between gap-4 mb-4">
        <h2 class="fdk-sublist-title">$Record.Title</h2>
        <% if $EditLink %>
            <a href="$EditLink" class="btn btn-sm btn-primary"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_EDIT 'Edit' %></a>
        <% end_if %>
    </div>
    $Fields
    <% loop $SubControllerData %>
        <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\SubListBlock Title=$Title, Segment=$Segment, Url=$Url %>
    <% end_loop %>
</section>
