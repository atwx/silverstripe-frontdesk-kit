<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% loop $Actions %>
                <a href="$Link"
                   class="btn btn-sm<% if $Primary %> btn-primary<% else %> btn-ghost<% end_if %>"
                   <% if $Target %>target="$Target"<% end_if %>>$Title</a>
            <% end_loop %>
        </div>
    </div>

    <% if $FilterForm %>
    <form class="fdk-filter-bar"
          hx-get="$Link"
          hx-target="#fdk-list-region"
          hx-trigger="change, submit, input delay:400ms from:find input[type=text]"
          hx-swap="innerHTML"
          hx-push-url="true"
          method="get"
          action="$Link">
        <% loop $FilterForm.Fields %>
            <div class="fdk-filter-field">
                $FieldHolder
            </div>
        <% end_loop %>
    </form>
    <% end_if %>

    <% if $BulkMessage %>
    <div role="status" class="alert alert-success mb-4">$BulkMessage</div>
    <% end_if %>

    <% if $BulkActions.Count %>
    <form id="fdk-bulk-form" class="fdk-bulk-bar flex flex-wrap items-center gap-2 mb-2" method="post" action="$Link('bulk')"
          onsubmit="this.BackURL.value = window.location.pathname + window.location.search">
        <input type="hidden" name="SecurityID" value="$SecurityTokenValue">
        <input type="hidden" name="BackURL" value="">
        <label for="fdk-bulk-action" class="text-sm"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_BULK 'Selected records' %>:</label>
        <select id="fdk-bulk-action" name="BulkAction" class="select select-sm" required>
            <option value=""><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.LABEL_BULK_CHOOSE 'Choose action' %></option>
            <% loop $BulkActions %>
                <% if $HasOptions %>
                    <optgroup label="$Label">
                        <% loop $Choices %><option value="$Value">$Title</option><% end_loop %>
                    </optgroup>
                <% else %>
                    <% loop $Choices %><option value="$Value">$Title</option><% end_loop %>
                <% end_if %>
            <% end_loop %>
        </select>
        <button type="submit" class="btn btn-sm"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_BULK_APPLY 'Apply' %></button>
    </form>
    <% end_if %>

    <div id="fdk-list-region">
        <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\ListTable %>
    </div>
</div>

<!-- Edit modal: content loaded via HTMX -->
<dialog id="fdk-modal" class="modal">
    <div class="modal-box w-11/12 max-w-3xl">
        <span id="fdk-modal-spinner" class="htmx-indicator loading loading-spinner loading-md"></span>
        <div id="fdk-modal-content"></div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- Delete confirm modal -->
<dialog id="fdk-delete-modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.CONFIRM_DELETE 'Are you sure you want to delete this record?' %></h3>
        <div class="modal-action">
            <button id="fdk-delete-confirm"
                    class="btn btn-error"
                    hx-delete=""
                    hx-target=""
                    hx-swap="outerHTML"
                    onclick="document.getElementById('fdk-delete-modal').close()">
                <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_DELETE 'Delete' %>
            </button>
            <form method="dialog"><button class="btn"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_CANCEL 'Cancel' %></button></form>
        </div>
    </div>
</dialog>

<script>
function fdkOpenDelete(btn) {
    var modal = document.getElementById('fdk-delete-modal');
    var confirm = document.getElementById('fdk-delete-confirm');
    var rowId = btn.dataset.rowId;
    confirm.setAttribute('hx-delete', btn.dataset.deleteUrl);
    confirm.setAttribute('hx-target', '#fdk-row-' + rowId);
    // Re-process HTMX on the button after updating its attributes
    if (window.htmx) { htmx.process(confirm); }
    modal.showModal();
}
</script>
