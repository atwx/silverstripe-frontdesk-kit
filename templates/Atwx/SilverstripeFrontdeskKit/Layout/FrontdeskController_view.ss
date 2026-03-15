<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% if $Top.canEdit %>
                <a href="$Top.Link('edit')/$Item.ID" class="btn btn-primary btn-sm"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.ACTION_EDIT 'Edit' %></a>
            <% end_if %>
            <a href="$Top.Link" class="btn btn-ghost btn-sm"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.ACTION_BACK '← Back' %></a>
        </div>
    </div>

    <div class="fdk-table-wrapper overflow-x-auto">
        <table class="table table-zebra w-full">
            <tbody>
                <% loop $ViewFields %>
                <tr>
                    <th class="w-1/4 font-medium text-base-content/70 whitespace-nowrap">$Label</th>
                    <td>
                        <% if $Type == html %>
                            $Value.RAW
                        <% else %>
                            $Value
                        <% end_if %>
                    </td>
                </tr>
                <% end_loop %>
            </tbody>
        </table>
    </div>

    <% loop $SubControllerData %>
    <section class="fdk-sublist mt-8">
        <h2 class="fdk-sublist-title">$Title</h2>
        <div id="fdk-sublist-$Segment"
             hx-get="$Url"
             hx-trigger="load"
             hx-swap="innerHTML">
            <span class="loading loading-spinner loading-sm text-base-content/30"></span>
        </div>
    </section>
    <% end_loop %>
</div>
