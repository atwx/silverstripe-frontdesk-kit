<%-- One hit in the global search (title, a line below, optional image or icon).
     Per list: config search_hit_template on its controller; the data comes from defineSearchHit(). --%>
<a href="$Link" class="fdk-search-hit flex items-center gap-3">
    <% if $Image %><img src="$Image" alt="" class="size-8 rounded object-cover shrink-0">
    <% else_if $Icon %><i data-lucide="$Icon" class="size-5 shrink-0" aria-hidden="true"></i><% end_if %>
    <span class="min-w-0">
        <span class="block font-medium truncate">$Title</span>
        <% if $Text %><span class="block text-sm text-base-content/60 truncate">$Text</span><% end_if %>
    </span>
</a>
