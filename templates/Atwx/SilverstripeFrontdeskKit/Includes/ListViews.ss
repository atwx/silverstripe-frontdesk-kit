<%-- View bar of a list: views defined in code and saved views, save dialog. --%>
<% if $ListViews.Count || $CanSaveListViews || $CanChooseColumns %>
<div class="fdk-list-views flex flex-wrap items-center gap-2 mb-4">
    <% loop $ListViews %>
        <span class="inline-flex items-center">
            <a href="$Link" class="btn btn-sm<% if $Active %> btn-primary<% else %> btn-ghost<% end_if %>"<% if $Active %> aria-current="page"<% end_if %>>$Title</a>
            <% if $DeleteLink %>
            <form method="post" action="$DeleteLink" class="inline"
                  onsubmit="return confirm('<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_CONFIRM_DELETE 'Delete this view?' %>')">
                <input type="hidden" name="SecurityID" value="$Top.SecurityTokenValue">
                <button type="submit" class="btn btn-xs btn-ghost" title="<%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_DELETE 'Delete view' %>">✕</button>
            </form>
            <% end_if %>
        </span>
    <% end_loop %>
    <% if $CanSaveListViews %>
        <button type="button" class="btn btn-sm btn-ghost"
                onclick="var d=document.getElementById('fdk-save-view'); var box=d.querySelector('.modal-box'); box.replaceChildren(document.getElementById('fdk-save-view-form').content.cloneNode(true)); box.querySelector('[name=Query]').value=window.location.search.substring(1); d.showModal()">
            + <%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.VIEW_SAVE 'Save view' %>
        </button>
    <% end_if %>
    <% if $CanChooseColumns %>
        <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\ColumnChoice %>
    <% end_if %>
</div>

<% include Atwx\\SilverstripeFrontdeskKit\\Includes\\SaveViewDialog %>
<% end_if %>
