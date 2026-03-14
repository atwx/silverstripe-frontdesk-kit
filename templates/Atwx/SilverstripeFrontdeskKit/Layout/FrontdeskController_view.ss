<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% if $Top.canEdit %>
                <a href="$Top.Link('edit/$Item.ID')" class="btn btn-primary btn-sm"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.ACTION_EDIT 'Edit' %></a>
            <% end_if %>
            <a href="$Top.Link" class="btn btn-ghost btn-sm"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.ACTION_BACK '← Back' %></a>
        </div>
    </div>

    <div class="fdk-detail-wrap">
        <dl class="fdk-detail-list">
            <% loop $ViewFields %>
                <div class="fdk-detail-row">
                    <dt class="fdk-detail-label">$Label</dt>
                    <dd class="fdk-detail-value">
                        <% if $Type == html %>
                            $Value.RAW
                        <% else %>
                            $Value
                        <% end_if %>
                    </dd>
                </div>
            <% end_loop %>
        </dl>
    </div>
</div>
