<nav role="tablist" class="tabs tabs-border fdk-tabs mb-6">
    <% loop $Tabs %>
        <a role="tab" href="$Link" class="tab<% if $Active %> tab-active<% end_if %>"<% if $Active %> aria-current="page"<% end_if %>>$Title</a>
    <% end_loop %>
</nav>
