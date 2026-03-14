<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% if $Top.canEdit %>
                <a href="$Top.Link('edit')/$Item.ID" class="btn btn-primary btn-sm">Bearbeiten</a>
            <% end_if %>
            <a href="javascript:history.back();" class="btn btn-ghost btn-sm">← Zurück</a>
        </div>
    </div>

    <div class="fdk-detail-wrap">
        $Item
    </div>
</div>
