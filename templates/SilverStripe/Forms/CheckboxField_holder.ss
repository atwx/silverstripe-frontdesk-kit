<div id="$HolderID" class="mb-4">
    <label class="label cursor-pointer justify-start gap-2 py-0" for="$ID">
        $Field
        <span class="label-text">$Title</span>
        <% if $RightTitle %><span class="label-text-alt">$RightTitle</span><% end_if %>
    </label>
    <% if $Message %>
    <div class="label pt-1">
        <span class="text-error">$Message</span>
    </div>
    <% end_if %>
    <% if $Description %>
    <p class="text-xs text-base-content/60 mt-1">$Description</p>
    <% end_if %>
</div>
