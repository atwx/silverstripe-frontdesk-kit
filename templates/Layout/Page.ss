<div class="fdk-manager">
    <% if $Title %>
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
    </div>
    <% end_if %>

    <% if $Description %>
    <div class="prose mb-4">$Description</div>
    <% end_if %>

    <% if $Content %>
    <div class="prose mb-4">$Content</div>
    <% end_if %>

    <% if $Form %>
    <div class="fdk-form-wrap">
        $Form
    </div>
    <% end_if %>
</div>
