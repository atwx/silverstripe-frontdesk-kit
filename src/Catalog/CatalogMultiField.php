<?php

namespace Atwx\SilverstripeFrontdeskKit\Catalog;

use Atwx\SilverstripeFrontdeskKit\Forms\CheckboxTreeField;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\RelationList;

/**
 * Multiple choice of catalog entries for a many_many relation, rendered as a
 * searchable checkbox list (treeselect). Only the given entries can be chosen
 * (the catalog's active entries plus the record's current ones).
 */
class CatalogMultiField extends CheckboxTreeField
{
    /**
     * @param iterable<CatalogEntry> $entries
     */
    public function __construct($name, $title, iterable $entries, $value = null)
    {
        parent::__construct($name, $title, $entries, $value);
        $this->addExtraClass('fdk-catalog-multi');
    }

    protected function buildNode($item): array
    {
        return [
            'value' => (int) $item->ID,
            'name' => $item instanceof CatalogEntry ? $item->getLabel() : (string) $item->Title,
            'children' => [],
        ];
    }

    public function setValue($value, $data = null)
    {
        // Form::loadDataFrom($record) passes null for a many_many relation:
        // read the relation, else saving the form would empty it.
        if ($value === null && $data instanceof DataObject && $data->hasMethod($this->getName())) {
            $value = $data->{$this->getName()}();
        }
        if ($value instanceof SS_List) {
            $value = $value->column('ID');
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array_filter(explode(',', $value));
        }
        $this->value = array_values(array_unique(array_map('intval', is_array($value) ? $value : [])));
        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function selectedIDs(): array
    {
        return is_array($this->value) ? array_values(array_filter($this->value)) : [];
    }

    /**
     * @return array<int, int>
     */
    private function allowedIDs(): array
    {
        $ids = [];
        foreach ($this->getItems() ?? [] as $item) {
            $ids[] = (int) $item->ID;
        }
        return $ids;
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (array_diff($this->selectedIDs(), $this->allowedIDs())) {
            $result->addFieldError($this->name, _t(
                self::class . '.INVALID',
                'Please only choose values from the list.'
            ), 'validation');
        }
        return $result;
    }

    public function saveInto(DataObjectInterface $record): void
    {
        if ($this->isReadonly() || $this->isDisabled() || !$record instanceof DataObject) {
            return;
        }
        $relation = $record->{$this->name}();
        if ($relation instanceof RelationList || method_exists($relation, 'setByIDList')) {
            $relation->setByIDList(array_intersect($this->selectedIDs(), $this->allowedIDs()));
        }
    }

    public function performReadonlyTransformation()
    {
        $titles = [];
        foreach ($this->getItems() ?? [] as $item) {
            if (in_array((int) $item->ID, $this->selectedIDs(), true)) {
                $titles[] = $item instanceof CatalogEntry ? $item->getLabel() : (string) $item->Title;
            }
        }
        $field = ReadonlyField::create($this->name, $this->title, implode(', ', $titles));
        $field->setForm($this->form);
        return $field;
    }
}
