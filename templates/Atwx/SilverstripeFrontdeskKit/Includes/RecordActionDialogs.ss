<%-- Dialogs of the record actions (see RecordAction); opened by RecordActionButtons. --%>
<% loop $RecordActions %>
<dialog id="fdk-record-action-$Name" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-2">$Label</h3>
        <% if $Description %><p class="mb-4 text-base-content/70">$Description</p><% end_if %>
        <form method="post" action="$Url" class="space-y-4"
              onsubmit="this.BackURL.value = window.location.pathname + window.location.search">
            <input type="hidden" name="SecurityID" value="$Top.SecurityTokenValue">
            <input type="hidden" name="BackURL" value="">
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
            <% loop $Selects %>
            <label class="block">
                <span class="label-text">$Label</span>
                <select name="Select[$Name]" class="select select-bordered w-full mt-1">
                    <option value="">$EmptyLabel</option>
                    <% loop $Options %>
                    <option value="$Value"<% if $Selected %> selected<% end_if %>>$Label</option>
                    <% end_loop %>
                </select>
            </label>
            <% end_loop %>
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
