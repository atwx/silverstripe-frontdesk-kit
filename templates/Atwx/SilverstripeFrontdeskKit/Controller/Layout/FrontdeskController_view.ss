<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% loop $RecordActions %>
                <button type="button" class="btn btn-sm btn-primary"
                        onclick="document.getElementById('fdk-record-action-$Name').showModal()">$Label</button>
            <% end_loop %>
            <% loop $ViewActions %>
                <a href="$Link"
                   class="btn btn-sm <% if $Primary %>btn-primary<% else %>btn-ghost<% end_if %><% if $Active %> btn-active<% end_if %>"
                   <% if $Target %>target="$Target"<% end_if %>>$Title</a>
            <% end_loop %>
        </div>
    </div>

    <% if $FlashMessage %>
        <div role="alert" class="alert <% if $FlashMessage.Type == 'error' %>alert-error<% else_if $FlashMessage.Type == 'info' %>alert-info<% else %>alert-success<% end_if %> mb-4">
            <span>$FlashMessage.Text</span>
        </div>
    <% end_if %>

    <% if $Tabs %>
        <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Tabs %>
    <% end_if %>

    $ViewContent

    <% if $Timeline %>
        <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Timeline %>
    <% end_if %>

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

<% loop $RecordActions %>
<dialog id="fdk-record-action-$Name" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-2">$Label</h3>
        <% if $Description %><p class="mb-4 text-base-content/70">$Description</p><% end_if %>
        <form method="post" action="$Url" class="space-y-4">
            <input type="hidden" name="SecurityID" value="$Top.SecurityTokenValue">
            <input type="hidden" name="RecordAction" value="$Name">
            <% if $HasOptions %>
            <fieldset class="space-y-2">
                <% loop $Options %>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" class="radio radio-sm" name="Option" value="$Value" required<% if $IsFirst %> checked<% end_if %>>
                    <span>$Label</span>
                </label>
                <% end_loop %>
            </fieldset>
            <% end_if %>
            <% if $CommentLabel %>
            <label class="block">
                <span class="label-text">$CommentLabel</span>
                <textarea name="Comment" class="textarea textarea-bordered w-full mt-1" rows="3"<% if $CommentRequired %> required<% end_if %>></textarea>
            </label>
            <% end_if %>
            <div class="modal-action">
                <button type="submit" class="btn btn-primary">$SubmitLabel</button>
                <button type="button" class="btn" onclick="this.closest('dialog').close()"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_CANCEL 'Cancel' %></button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>
<% end_loop %>

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
    if (window.htmx) { htmx.process(confirm); }
    modal.showModal();
}
</script>
