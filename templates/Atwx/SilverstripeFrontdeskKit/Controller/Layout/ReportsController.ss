<%-- Page "Reports": the reports of all lists (ReportsController). --%>
<div class="fdk-manager">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
    </div>
    <% if $Lists.Count %>
    <div class="grid gap-4 md:grid-cols-2">
        <% loop $Lists %>
        <section class="card bg-base-100 shadow-sm fdk-report-list">
            <div class="card-body">
                <h2 class="card-title"><a href="$Link" class="link link-hover">$Title</a></h2>
                <% loop $Groups %>
                    <% if $Title %><h3 class="text-sm font-semibold text-base-content/70 mt-2">$Title</h3><% end_if %>
                    <ul class="space-y-1">
                        <% loop $Reports %>
                        <li>
                            <a href="$Link" class="link link-primary">$Title</a>
                            <% if $Description %><div class="text-sm text-base-content/70">$Description</div><% end_if %>
                        </li>
                        <% end_loop %>
                    </ul>
                <% end_loop %>
            </div>
        </section>
        <% end_loop %>
    </div>
    <% else %>
    <p class="text-base-content/70"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.REPORTS_NONE 'No reports yet. Save a list with its filters as a report to find it here.' %></p>
    <% end_if %>
</div>
