<%-- Dialog "Save view" (list views bar and list toolbar). --%>
<% if $CanSaveListViews %>
<%-- The form is inserted when the dialog opens, so its labels (column names) are not in the page twice. --%>
<template id="fdk-save-view-form">
        <h3 class="font-bold text-lg mb-4"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_SAVE 'Save view' %></h3>
        <form method="post" action="$Link('saveview')" class="space-y-4">
            <input type="hidden" name="SecurityID" value="$SecurityTokenValue">
            <input type="hidden" name="Query" value="">
            <% if $UpdatableListView %><% with $UpdatableListView %>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="UpdateID" value="$ID" class="checkbox checkbox-sm" checked>
                <span><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_UPDATE 'Update "{title}" (otherwise saved as a new report)' title=$Title %></span>
            </label>
            <% end_with %><% end_if %>
            <label class="block">
                <span class="block mb-1 text-sm font-medium"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_TITLE 'Name' %></span>
                <input type="text" name="Title" class="input w-full" required value="$UpdatableListView.Title.ATT">
            </label>
            <label class="block">
                <span class="block mb-1 text-sm font-medium"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_DESCRIPTION 'Description' %></span>
                <input type="text" name="Description" class="input w-full" value="$UpdatableListView.Description.ATT">
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="Pinned" value="1" class="checkbox checkbox-sm"<% if $UpdatableListView.Pinned %> checked<% end_if %>>
                <span><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_PINNED 'Show in the view bar' %></span>
            </label>
            <% if $CanShareListViews %>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="Shared" value="1" class="checkbox checkbox-sm"<% if $UpdatableListView.Shared %> checked<% end_if %>>
                <span><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_SHARED 'For everyone' %></span>
            </label>
            <% end_if %>
            <label class="block">
                <span class="block mb-1 text-sm font-medium"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_GROUP_BY 'Group by' %></span>
                <select name="GroupBy" class="select w-full">
                    <option value="">–</option>
                    <% loop $ListViewGroupColumns %><option value="$Name.ATT"<% if $Selected %> selected<% end_if %>>$Title</option><% end_loop %>
                </select>
            </label>
            <fieldset>
                <legend class="mb-1 text-sm font-medium"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_COLUMNS 'Columns (list and export)' %></legend>
                <div class="grid grid-cols-2 gap-1">
                    <% loop $ListViewColumns %>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="Columns[]" value="$Name" class="checkbox checkbox-sm"<% if $Checked %> checked<% end_if %>>
                        <span>$Title</span>
                    </label>
                    <% end_loop %>
                </div>
            </fieldset>
            <p class="text-sm text-base-content/60"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_HINT 'The report keeps the current filters, the search and the sort order.' %></p>
            <div class="modal-action">
                <button type="submit" class="btn btn-primary"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_SAVE 'Save' %></button>
                <button type="button" class="btn" onclick="this.closest('dialog').close()"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_CANCEL 'Cancel' %></button>
            </div>
        </form>
</template>
<dialog id="fdk-save-view" class="modal">
    <div class="modal-box"></div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>
<% end_if %>
