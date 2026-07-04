<details class="fdk-collapse collapse collapse-arrow bg-base-200 mb-4"<% if $IsOpen %> open<% end_if %>>
    <summary class="collapse-title font-medium">$Title</summary>
    <div class="collapse-content space-y-4">
        <% loop $FieldList %>
            $FieldHolder
        <% end_loop %>
    </div>
</details>
