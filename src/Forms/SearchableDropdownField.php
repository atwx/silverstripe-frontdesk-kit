<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\SearchableDropdownField as CmsSearchableDropdownField;

class SearchableDropdownField extends DropdownField
{
    /**
     * Silverstripe 6 scaffolds has_one relations as React SearchableDropdownFields,
     * which render as empty selects outside the CMS; saving such a form would clear
     * the relation. Replaces them (in place, also inside composite fields) with this
     * field, keeping name, title, value and options.
     */
    public static function replaceCmsFields(FieldList $fields): FieldList
    {
        foreach ($fields->dataFields() as $field) {
            if (!$field instanceof CmsSearchableDropdownField) {
                continue;
            }
            $replacement = static::create($field->getName(), $field->Title(), $field->getSource(), $field->getValue())
                ->setHasEmptyDefault(true)
                ->setEmptyString(_t(self::class . '.EMPTY', '– please choose –'));
            $fields->replaceField($field->getName(), $replacement);
        }
        return $fields;
    }

    /**
     * An empty has_one is 0: with an empty default, 0 means "none" (unless 0 is
     * one of the options), like the empty string.
     */
    public function getValueForValidation(): mixed
    {
        $value = $this->getValue();
        if ($this->getHasEmptyDefault() && in_array($value, [0, '0'], true) && !array_key_exists(0, $this->getSource())) {
            return null;
        }
        return parent::getValueForValidation();
    }

    public function Field($properties = [])
    {
        return $this->renderWith(self::class);
    }

    public function getOptionsAsJson(): string
    {
        $out = [];
        foreach ($this->getSource() as $value => $label) {
            $out[] = ['value' => (string) $value, 'label' => (string) $label];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    public function getSelectedLabel(): string
    {
        $source = $this->getSource();
        $val = (string) $this->getValue();
        return isset($source[$val]) ? (string) $source[$val] : '';
    }
}
