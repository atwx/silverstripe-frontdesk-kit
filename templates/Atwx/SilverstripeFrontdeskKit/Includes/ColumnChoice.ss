<%-- "Spalten" menu of a list: choose the shown columns (FrontdeskController::choosecolumns).
     The checkboxes are inserted when the menu opens (template), so their labels
     are not in the page twice next to filter fields with the same names. --%>
<details class="dropdown dropdown-end fdk-column-choice ms-auto" data-fdk-column-choice>
    <summary class="btn btn-sm btn-ghost flex-nowrap"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.COLUMNS 'Columns' %> <% include Atwx\\SilverstripeFrontdeskKit\\Includes\\Chevron %></summary>
    <form method="post" action="$Link('choosecolumns')"
          class="dropdown-content bg-base-100 rounded-box shadow z-20 w-72 max-w-[calc(100vw-2rem)] p-3"
          onsubmit="this.BackURL.value = window.location.pathname + window.location.search">
        <input type="hidden" name="SecurityID" value="$SecurityTokenValue">
        <input type="hidden" name="BackURL" value="">
        <div class="fdk-column-choice-body"></div>
        <template>
        <div class="max-h-80 overflow-y-auto space-y-1" data-fdk-select-all="<%t Atwx\SilverstripeFrontdeskKit\Forms\CheckboxSetSelectAll.ALL 'Select all' %>" data-fdk-select-none="<%t Atwx\SilverstripeFrontdeskKit\Forms\CheckboxSetSelectAll.NONE 'None' %>">
            <% loop $ColumnChoice %>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="Columns[]" value="$Name.ATT" class="checkbox checkbox-sm"<% if $Checked %> checked<% end_if %>>
                <span>$Title</span>
            </label>
            <% end_loop %>
        </div>
        </template>
        <% if $ColumnChoiceIsShared %>
        <p class="text-xs opacity-60 mt-2"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.COLUMNS_SHARED 'Applies to everyone.' %></p>
        <% end_if %>
        <div class="flex gap-2 mt-3">
            <button type="submit" class="btn btn-sm btn-primary"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.COLUMNS_APPLY 'Apply' %></button>
            <button type="submit" name="reset" value="1" class="btn btn-sm btn-ghost"><%t Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController.COLUMNS_RESET 'Default' %></button>
        </div>
    </form>
</details>
