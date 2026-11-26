<%-- Result of the global search (SearchController): one block per type. Replace with
     SearchController.results_template; the groups and hits have their own templates. --%>
<% if $Groups %>
<div class="fdk-search-groups flex flex-col gap-4">
    <% loop $Groups %>$Html<% end_loop %>
</div>
<% else_if $HasQuery %>
<p class="fdk-search-empty py-4 text-base-content/60"><%t Atwx\SilverstripeFrontdeskKit\Search\SearchController.NOTHING 'Nothing found.' %></p>
<% end_if %>
