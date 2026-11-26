<%-- Buttons that open the record action dialogs (those of the "⋯" menu are in RecordMoreMenu). --%>
<% loop $RecordActions %><% if not $InMenu %>
    <button type="button" class="btn btn-sm btn-primary"
            onclick="document.getElementById('fdk-record-action-$Name').showModal()">$Label</button>
<% end_if %><% end_loop %>
<% include Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordMoreMenu %>
