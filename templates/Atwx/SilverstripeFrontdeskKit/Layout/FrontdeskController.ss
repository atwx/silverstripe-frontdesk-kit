<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% loop $Actions %>
                <a href="$Link"
                   class="btn<% if $Primary %> btn-primary<% else %> btn-ghost btn-sm<% end_if %>"
                   <% if $Target %>target="$Target"<% end_if %>>$Title</a>
            <% end_loop %>
        </div>
    </div>

    <% if $FilterForm.Fields.Count %>
    <form class="fdk-filter-bar"
          hx-get="$Link"
          hx-target="#fdk-list-body"
          hx-trigger="change, submit"
          hx-swap="innerHTML"
          method="get"
          action="$Link">
        <% loop $FilterForm.Fields %>
            <div class="fdk-filter-field">
                $FieldHolder
            </div>
        <% end_loop %>
        <% if $FilterIsActive %>
            <a href="$Link" class="btn btn-ghost btn-sm">Zurücksetzen</a>
        <% end_if %>
    </form>
    <% end_if %>

    <p class="fdk-count">
        <% if $FilterIsActive %>
            $Items.TotalItems Einträge gefunden –
            <a href="$Link" class="link link-primary">Filter zurücksetzen</a>
        <% else %>
            $Items.TotalItems Einträge
        <% end_if %>
    </p>

    <div class="fdk-table-wrapper overflow-x-auto">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    <% loop $Columns %>
                        <th>$Title</th>
                    <% end_loop %>
                    <th></th>
                </tr>
            </thead>
            <tbody id="fdk-list-body">
                <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\ListTable %>
            </tbody>
        </table>
    </div>

    <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Pagination ItemList=$Items %>
</div>
