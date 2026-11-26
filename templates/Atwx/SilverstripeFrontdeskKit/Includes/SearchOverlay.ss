<%-- Shell of the global search (config FrontdeskController.search_overlay): opens with a
     navigation entry (open_search: true) or the key "/", closes with Escape or a click beside it.
     The results come as HTML from fdk-search (SearchResults); replace this include for an own shell. --%>
<dialog id="fdk-search" class="modal modal-top fdk-search-overlay" aria-label="<%t Atwx\SilverstripeFrontdeskKit\Search\SearchController.TITLE 'Global search' %>">
    <div class="modal-box max-w-3xl w-11/12 mx-auto mt-16 rounded-box">
        <form role="search" onsubmit="return false" class="mb-3">
            <label class="input w-full flex items-center gap-2">
                <i data-lucide="search" class="size-4 opacity-60" aria-hidden="true"></i>
                <input type="search" name="q" class="grow" autocomplete="off" data-fdk-search-input
                       placeholder="<%t Atwx\SilverstripeFrontdeskKit\Search\SearchController.PLACEHOLDER 'Search everything' %>"
                       aria-label="<%t Atwx\SilverstripeFrontdeskKit\Search\SearchController.PLACEHOLDER 'Search everything' %>"
                       hx-get="fdk-search" hx-trigger="input changed delay:250ms, search" hx-target="#fdk-search-results"
                       hx-sync="this:replace" hx-indicator="#fdk-search-spinner">
                <span id="fdk-search-spinner" class="htmx-indicator loading loading-spinner loading-xs"></span>
            </label>
        </form>
        <div id="fdk-search-results" aria-live="polite"></div>
    </div>
    <form method="dialog" class="modal-backdrop"><button><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.ACTION_CLOSE 'Close' %></button></form>
</dialog>
