<div class="fdk-manager fdk-standalone">
    <div class="fdk-page-header">
        <h1 class="fdk-page-title">$Title</h1>
        <div class="fdk-page-actions">
            <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordActionButtons %>
        </div>
    </div>

    <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\FlashMessage %>

    <% if $IntroText %>
        <p class="mb-6 text-base-content/70 whitespace-pre-line">$IntroText</p>
    <% end_if %>

    <% if $Form %>
        <div class="fdk-form-wrap">
            $Form
        </div>
    <% else %>
        <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\ViewGroups %>
    <% end_if %>
</div>

<% include Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordActionDialogs %>
