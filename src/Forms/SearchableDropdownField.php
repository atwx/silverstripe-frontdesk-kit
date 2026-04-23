<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Forms\DropdownField;

class SearchableDropdownField extends DropdownField
{
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
