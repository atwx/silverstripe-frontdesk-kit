<%-- The current member's recently viewed records (RecentView::recent()), e.g. on a start page. --%>
<section class="fdk-recent-views">
    <h2 class="fdk-sublist-title"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.RECENT_VIEWS 'Recently viewed' %></h2>
    <% with $FrontdeskRecentViews %>
    <% if $Count %>
    <ul class="fdk-recent-views-list divide-y divide-base-300">
        <% loop $Me %>
        <li class="flex flex-wrap items-baseline justify-between gap-x-3 py-2">
            <span>
                <% if $Link %><a href="$Link" class="link link-hover font-medium">$Title</a><% else %><span class="font-medium">$Title</span><% end_if %>
                <span class="text-sm text-base-content/60">$Type</span>
            </span>
            <time class="text-sm text-base-content/60" datetime="$ViewedAt.Rfc3339">$ViewedAt.Format('dd.MM.yyyy HH:mm')</time>
        </li>
        <% end_loop %>
    </ul>
    <% else %>
    <p class="text-base-content/60"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.RECENT_VIEWS_EMPTY 'Nothing viewed yet.' %></p>
    <% end_if %>
    <% end_with %>
</section>
