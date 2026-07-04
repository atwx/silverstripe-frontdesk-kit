<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Forms\FieldGroup;

/**
 * A FieldGroup rendered as a collapsible DaisyUI panel (native <details>).
 * Use together with a [data-fdk-toggle-all] trigger to expand/collapse all
 * groups on a page at once.
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
        return $this->open;
    }
}
