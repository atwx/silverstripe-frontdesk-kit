<%-- Buttons that open the record action dialogs. --%>
<% loop $RecordActions %>
    <button type="button" class="btn btn-sm btn-primary"
            onclick="document.getElementById('fdk-record-action-$Name').showModal()">$Label</button>
<% end_loop %>
