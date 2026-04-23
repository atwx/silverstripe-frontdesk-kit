<div class="fdk-searchable relative"
     x-data="fdkSearchable()"
     data-name="$Name.ATT"
     data-value="$Value.ATT"
     data-selected="$SelectedLabel.ATT"
     data-empty="<% if $EmptyString %>$EmptyString.ATT<% else %>Alle<% end_if %>"
     data-options="$OptionsAsJson.ATT"
     @keydown.escape="open = false"
     @click.outside="open = false">
    <input type="hidden" name="$Name.ATT" x-ref="hidden" :value="value">
    <input type="text"
           class="input w-full"
           x-model="query"
           @focus="open = true"
           @click="open = true"
           :placeholder="selected || empty"
           autocomplete="off">
    <ul x-show="open" x-cloak
        class="menu bg-base-100 rounded-box shadow absolute z-50 w-full md:w-[200%]! mt-1 max-h-72 overflow-y-auto overflow-x-hidden flex-nowrap">
        <template x-for="opt in filtered()" :key="opt.value">
            <li class="min-w-0 w-full">
                <a @click="pick(opt)"
                   :class="{ 'menu-active': opt.value === value }"
                   :title="opt.label || empty"
                   class="block w-full min-w-0 whitespace-nowrap overflow-hidden text-ellipsis"
                   x-text="opt.label || empty"></a>
            </li>
        </template>
        <li x-show="filtered().length === 0" class="px-3 py-2 text-sm opacity-60">Keine Treffer</li>
    </ul>
</div>
