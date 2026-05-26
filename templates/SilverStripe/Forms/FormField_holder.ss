<div id="$HolderID" class="mb-4">
    <% if $Title %>
    <label class="block mb-1 text-sm font-medium" for="$ID">
        $Title
        <% if $RightTitle %><span class="text-xs opacity-60 ml-1">$RightTitle</span><% end_if %>
    </label>
    <% end_if %>
    $Field
    <% if $Message %>
    <div class="text-xs text-error mt-1">$Message</div>
    <% end_if %>
    <% if $Description %>
    <p class="text-xs text-base-content/60 mt-1">$Description</p>
    <% end_if %>
</div>
