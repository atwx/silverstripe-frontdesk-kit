<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Catalog\CatalogEntry;
use Atwx\SilverstripeFrontdeskKit\Catalog\Catalogs;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\SelectFilter;
use Atwx\SilverstripeFrontdeskKit\Filter\TextFilter;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\RowAction;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * Maintenance of the catalogs (catalogs/): entries per catalog, add, rename,
 * reorder, deactivate; entries in use cannot be deleted. Needs the permission
 * FDK_CATALOGS_EDIT (or ADMIN) to change entries.
 */
class CatalogController extends FrontdeskController
{
    private static $managed_model = CatalogEntry::class;

    private static $url_segment = 'catalogs';

    private static $title = 'Kataloge';

    private static $permission_code = 'FDK_CATALOGS';

    private static $check_record_permissions = true;

    private static $page_length = 100;

    /**
     * The catalog of the list: ?Catalog=<key>, default the first one.
     */
    protected function currentCatalog(): string
    {
        $catalogs = Catalogs::singleton();
        $request = $this->getRequest();
        $key = (string) ($request->getVar('Catalog') ?? $request->postVar('Catalog') ?? '');
        if ($catalogs->exists($key)) {
            return $key;
        }
        return (string) (array_key_first($catalogs->titles()) ?? '');
    }

    protected function getBaseList(): DataList
    {
        $keys = array_keys(Catalogs::singleton()->titles()) ?: [''];
        return CatalogEntry::get()->filter('Catalog', $keys);
    }

    protected function defineFilters(): FilterCollection
    {
        $catalogs = Catalogs::singleton();
        return FilterCollection::create()
            ->add(
                SelectFilter::create('Catalog', _t(CatalogEntry::class . '.db_Catalog', 'Catalog'))
                    ->options($catalogs->titles())
                    ->defaultValue(array_key_first($catalogs->titles()))
                    ->apply(fn (DataList $list, $value) => $list->filter('Catalog', $value))
            )
            ->add(
                TextFilter::create('q', _t(self::class . '.SEARCH', 'Search'))
                    ->apply(fn (DataList $list, $value) => $list->filterAny([
                        'Title:PartialMatch' => $value,
                        'Code:PartialMatch' => $value,
                    ]))
            );
    }

    protected function defineColumns(): ColumnCollection
    {
        $labels = CatalogEntry::singleton()->fieldLabels();
        return ColumnCollection::create()
            ->make('Title', $labels['Title'])->end()
            ->make('Code', $labels['Code'])->end()
            ->make('CatalogTitle', $labels['Catalog'])->end()
            ->make('Sort', $labels['Sort'])->end()
            ->make('Active', $labels['Active'])
                ->format(fn ($value) => $value
                    ? '<span class="badge badge-success">' . _t(self::class . '.YES', 'yes') . '</span>'
                    : '<span class="badge badge-ghost">' . _t(self::class . '.NO', 'no') . '</span>')
                ->type('html')
                ->end()
            ->make('IsDefault', $labels['IsDefault'])
                ->format(fn ($value) => $value ? '✓' : '')
                ->end();
    }

    /**
     * Edit and delete; delete only for entries that are not in use.
     *
     * @return array<int, RowAction>
     */
    protected function defineRowActions(DataObject $record): array
    {
        if (!$this->canEdit()) {
            return [];
        }
        $actions = [RowAction::edit($this->Link('edit/' . $record->ID))];
        if ($record->canDelete()) {
            $actions[] = RowAction::delete($this->Link('delete/' . $record->ID));
        }
        return $actions;
    }

    /**
     * New entries belong to the catalog chosen in the list (?Catalog=<key>); the
     * catalog and the code of existing entries are fixed.
     */
    protected function formFields(FieldList $fields): FieldList
    {
        $fields = parent::formFields($fields);
        $id = $this->currentRecordID();
        $record = $id ? CatalogEntry::get()->byID($id) : null;
        $label = CatalogEntry::singleton()->fieldLabel('Catalog');
        if ($record) {
            $fields->replaceField('Catalog', ReadonlyField::create('CatalogTitle', $label, $record->getCatalogTitle()));
            $fields->replaceField('Code', ReadonlyField::create('Code', $record->fieldLabel('Code')));
            return $fields;
        }
        $key = $this->currentCatalog();
        $fields->replaceField('Catalog', HiddenField::create('Catalog', 'Catalog', $key));
        $fields->unshift(ReadonlyField::create('CatalogTitle', $label, Catalogs::singleton()->title($key)));
        $fields->dataFieldByName('Code')?->setDescription(_t(
            self::class . '.CODE_HINT',
            'Stable key, e.g. for imports; generated from the title when empty. Cannot be changed later.'
        ));
        return $fields;
    }

    /**
     * New entries are created as the class of their catalog (catalogs with own
     * columns use a subclass of CatalogEntry).
     */
    public function getManagedModel(): string
    {
        $action = $this->getAction();
        $request = $this->getRequest();
        $new = $action === 'add' || ($action === 'EditForm' && !$request->postVar('ID'));
        return $new ? Catalogs::singleton()->entryClass($this->currentCatalog()) : CatalogEntry::class;
    }

    /**
     * "New" adds to the catalog shown in the list.
     */
    public function Actions(): ArrayList
    {
        $actions = parent::Actions();
        foreach ($actions as $action) {
            if ($action->Primary) {
                $action->Link = $this->Link('add') . '?Catalog=' . rawurlencode($this->currentCatalog())
                    . '&BackURL=' . rawurlencode($this->Link() . '?Catalog=' . $this->currentCatalog());
            }
        }
        return $actions;
    }

    public function canAdd(): bool
    {
        return parent::canAdd() && Catalogs::singleton()->titles() !== [];
    }
}
