<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Forms\FieldGroup;

/**
 * A FieldGroup rendered as a collapsible DaisyUI panel (native <details>).
 * Use together with a [data-fdk-toggle-all] trigger to expand/collapse all
 * groups on a page at once.
 *
 * A closed group opens by itself when one of its fields (also in nested
 * groups) carries a message, e.g. a validation error after a failed save.
 */
class CollapsibleFieldGroup extends FieldGroup
{
    protected bool $open = false;

    public function setOpen(bool $open = true): static
    {
        $this->open = $open;
        return $this;
    }

    public function IsOpen(): bool
    {
        if ($this->open) {
            return true;
        }
        $fields = [];
        $this->collateDataFields($fields, true);
        foreach ($fields as $field) {
            if ((string) $field->getMessage() !== '') {
                return true;
            }
        }
        return false;
    }
}
