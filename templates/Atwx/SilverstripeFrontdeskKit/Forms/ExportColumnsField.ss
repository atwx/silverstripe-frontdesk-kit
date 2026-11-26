<%-- Columns of an export (ExportColumnsField): chosen columns with order and width, and a select to add one.
     The script fills the list from the JSON; the hidden field holds the result. --%>
<div class="fdk-export-columns" data-fdk-export-columns data-choices="$ChoicesJson.ATT"
     data-label-up="<%t Atwx\SilverstripeFrontdeskKit\Forms\ExportColumnsField.UP 'Up' %>"
     data-label-down="<%t Atwx\SilverstripeFrontdeskKit\Forms\ExportColumnsField.DOWN 'Down' %>"
     data-label-remove="<%t Atwx\SilverstripeFrontdeskKit\Forms\ExportColumnsField.REMOVE 'Remove' %>"
     data-label-width="<%t Atwx\SilverstripeFrontdeskKit\Forms\ExportColumnsField.WIDTH 'Width' %>">
    <input type="hidden" name="$Name" value="$ValueJson.ATT" data-fdk-export-value>
    <ol class="fdk-export-chosen flex flex-col gap-1 mb-2" data-fdk-export-chosen></ol>
    <div class="flex gap-2 items-center">
        <select class="select select-sm w-auto" data-fdk-export-add aria-label="<%t Atwx\SilverstripeFrontdeskKit\Forms\ExportColumnsField.ADD 'Add column' %>">
            <option value=""><%t Atwx\SilverstripeFrontdeskKit\Forms\ExportColumnsField.ADD 'Add column' %> …</option>
        </select>
    </div>
    <noscript><textarea name="{$Name}_names" class="textarea w-full" rows="6">$ChosenNames</textarea></noscript>
</div>
