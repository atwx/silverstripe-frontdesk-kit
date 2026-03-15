<div id="$HolderID" class="form-control mb-4">
    <% if $Title %>
    <label class="label" for="$ID">
        <span class="label-text">$Title</span>
        <% if $RightTitle %><span class="label-text-alt">$RightTitle</span><% end_if %>
    </label>
    <% end_if %>
    $Field
    <% if $Message %>
    <div class="label pt-1">
        <span class="label-text-alt text-error">$Message</span>
    </div>
    <% end_if %>
    <% if $Description %>
    <p class="text-xs text-base-content/60 mt-1">$Description</p>
    <% end_if %>
</div>
