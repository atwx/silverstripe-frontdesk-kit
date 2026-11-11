<%-- Timeline of a record, newest first (see FrontdeskController::defineTimeline()). --%>
<section class="fdk-timeline mt-8">
    <h2 class="fdk-sublist-title">$TimelineTitle</h2>
    <% if $Timeline.Count %>
    <ul class="fdk-timeline-list space-y-3">
        <% loop $Timeline %>
        <li class="fdk-timeline-entry<% if $Kind %> fdk-timeline-$Kind<% end_if %> border-l-4 border-base-300 pl-4">
            <div class="flex flex-wrap items-baseline gap-x-3 text-sm text-base-content/60">
                <time datetime="$Date.Rfc3339">$Date.Format('dd.MM.yyyy HH:mm')</time>
                <% if $Author %><span>$Author</span><% end_if %>
            </div>
            <div class="font-medium">$Title</div>
            <% if $Text %><div class="whitespace-pre-line">$Text</div><% end_if %>
        </li>
        <% end_loop %>
    </ul>
    <% else %>
    <p class="text-base-content/60"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.TIMELINE_EMPTY 'No entries yet.' %></p>
    <% end_if %>
</section>
