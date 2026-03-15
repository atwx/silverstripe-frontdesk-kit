<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% loop $Actions %>
                <a href="$Link"
                   class="btn<% if $Primary %> btn-primary<% else %> btn-ghost btn-sm<% end_if %>"
                   <% if $Target %>target="$Target"<% end_if %>>$Title</a>
            <% end_loop %>
        </div>
    </div>

    <% if $FilterForm.Fields.Count %>
    <form class="fdk-filter-bar"
          hx-get="$Link"
          hx-target="#fdk-list-body"
          hx-trigger="change, submit"
          hx-swap="innerHTML"
          method="get"
          action="$Link">
        <% loop $FilterForm.Fields %>
            <div class="fdk-filter-field">
                $FieldHolder
            </div>
        <% end_loop %>
        <% if $FilterIsActive %>
            <a href="$Link" class="btn btn-ghost btn-sm"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.ACTION_RESET 'Reset' %></a>
        <% end_if %>
    </form>
    <% end_if %>

    <p class="fdk-count">
        <% if $FilterIsActive %>
            $Items.TotalItems <%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.LABEL_RECORDS_FOUND 'records found' %> –
            <a href="$Link" class="link link-primary"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.ACTION_CLEAR_FILTERS 'Clear filters' %></a>
        <% else %>
            $Items.TotalItems <%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.LABEL_RECORDS 'records' %>
        <% end_if %>
    </p>

    <div class="fdk-table-wrapper overflow-x-auto">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    <% loop $Columns %>
                        <th>$Title</th>
                    <% end_loop %>
                    <th></th>
                </tr>
            </thead>
            <tbody id="fdk-list-body">
                <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\ListTable %>
            </tbody>
        </table>
    </div>

    <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Pagination ItemList=$Items %>
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
        <h3 class="font-bold text-lg"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.CONFIRM_DELETE 'Are you sure you want to delete this record?' %></h3>
        <div class="modal-action">
            <button id="fdk-delete-confirm"
                    class="btn btn-error"
                    hx-delete=""
                    hx-target=""
                    hx-swap="outerHTML"
                    onclick="document.getElementById('fdk-delete-modal').close()">
                <%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.ACTION_DELETE 'Delete' %>
            </button>
            <form method="dialog"><button class="btn"><%t Atwx\SilverstripeFrontdeskKit\FrontdeskController.ACTION_CANCEL 'Cancel' %></button></form>
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
