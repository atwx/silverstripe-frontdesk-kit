<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Forms\CheckboxSetField;

/**
 * Adds "select all" and "none" links above a CheckboxSetField in frontdesk
 * forms (rendered by the kit's JavaScript), once it has a minimum number of
 * options. Disabled options are left as they are.
 */
class CheckboxSetSelectAll
{
    public static function apply(CheckboxSetField $field, int $minOptions = 3): CheckboxSetField
    {
        if (count($field->getSource()) < $minOptions) {
            return $field;
        }
        return $field
            ->setAttribute('data-fdk-select-all', _t(self::class . '.ALL', 'Select all'))
            ->setAttribute('data-fdk-select-none', _t(self::class . '.NONE', 'None'));
    }
}
