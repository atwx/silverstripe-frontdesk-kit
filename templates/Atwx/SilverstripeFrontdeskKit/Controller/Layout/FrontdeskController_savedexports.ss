<%-- Exports of a list set up by members (FrontdeskController::savedexports()). --%>
<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <a href="$NewLink" class="btn btn-sm btn-primary"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_CREATE 'Set up export …' %></a>
            <a href="$BackLink" class="btn btn-sm btn-ghost"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_BACK '← Back' %></a>
        </div>
    </div>
    <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\FlashMessage %>
    <% if $Exports %>
    <div class="fdk-table-wrapper">
        <table class="table w-full fdk-stack">
            <thead><tr>
                <th><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_FIELD_TITLE 'Title' %></th>
                <th><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_FIELD_COLUMNS 'Columns' %></th>
                <th><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_OWNER 'By' %></th>
                <th></th>
            </tr></thead>
            <tbody>
            <% loop $Exports %>
            <tr>
                <td data-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_FIELD_TITLE 'Title' %>">
                    $Export.Title<% if $Export.Shared %> <span class="badge badge-sm badge-ghost"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_FIELD_SHARED 'For everyone' %></span><% end_if %>
                </td>
                <td class="text-sm text-base-content/70" data-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_FIELD_COLUMNS 'Columns' %>">$Columns</td>
                <td data-label="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_OWNER 'By' %>">$Owner</td>
                <td class="text-end whitespace-nowrap">
                    <% if $CanManage %><a href="$EditLink" class="btn btn-ghost btn-xs"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_EDIT 'Edit' %></a><% end_if %>
                    <form method="post" action="$CopyLink" class="inline">
                        <input type="hidden" name="SecurityID" value="$Top.Token">
                        <button type="submit" class="btn btn-ghost btn-xs"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_DUPLICATE 'Duplicate' %></button>
                    </form>
                    <% if $CanManage %>
                    <form method="post" action="$DeleteLink" class="inline"
                          onsubmit="return confirm('<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORT_CONFIRM_DELETE 'Delete this export?' %>')">
                        <input type="hidden" name="SecurityID" value="$Top.Token">
                        <button type="submit" class="btn btn-ghost btn-xs text-error"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_DELETE 'Delete' %></button>
                    </form>
                    <% end_if %>
                </td>
            </tr>
            <% end_loop %>
            </tbody>
        </table>
    </div>
    <% else %>
    <p class="text-base-content/60"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.EXPORTS_NONE 'No exports set up yet.' %></p>
    <% end_if %>
</div>
