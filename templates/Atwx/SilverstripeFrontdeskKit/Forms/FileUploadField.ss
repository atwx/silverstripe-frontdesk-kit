<div class="fdk-file-upload flex flex-col gap-1">
    <% loop $Current %>
        <div class="text-sm">
            <% if $Link %><a class="link" href="$Link.ATT" target="_blank" rel="noopener">$Name</a><% else %>$Name<% end_if %>
            <span class="text-base-content/60">($Size)</span>
        </div>
    <% end_loop %>
    <% if not $isReadonly %>
        <input type="file" name="$Name.ATT" id="$ID.ATT" accept="$AcceptAttribute.ATT"
               class="file-input w-full<% if $extraClass %> $extraClass<% end_if %>"<% if $Current.Count %><% else %><% if $isRequired %> required<% end_if %><% end_if %>>
        <% if $Current.Count && not $isRequired %>
            <label class="label text-sm gap-2">
                <input type="checkbox" class="checkbox checkbox-sm" name="$RemoveName.ATT" value="1">
                <%t Atwx\\SilverstripeFrontdeskKit\\Forms\\FileUploadField.REMOVE 'Remove file' %>
            </label>
        <% end_if %>
        <span class="text-xs text-base-content/60">$Hint</span>
    <% else_if not $Current.Count %>
        <span class="text-base-content/60">–</span>
    <% end_if %>
</div>
