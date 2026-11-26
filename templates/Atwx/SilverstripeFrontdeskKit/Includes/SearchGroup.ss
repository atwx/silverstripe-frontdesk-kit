<%-- One type in the global search: its best hits and a link to the list with the search.
     Per list: config search_group_template on its controller. --%>
<section class="fdk-search-group" data-fdk-search-group="$Key.ATT">
    <h3 class="text-sm font-semibold text-base-content/60 mb-1">$Type <span class="font-normal">($Count)</span></h3>
    <ul class="menu w-full p-0">
        <% loop $Hits %><li>$Html</li><% end_loop %>
    </ul>
    <% if $More %>
    <a href="$MoreLink" class="link link-primary text-sm ms-3"><%t Atwx\SilverstripeFrontdeskKit\Search\SearchController.MORE 'More results ({count})' count=$More %></a>
    <% end_if %>
</section>
