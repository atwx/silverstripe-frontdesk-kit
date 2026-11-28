<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use SilverStripe\Forms\DropdownField;

/**
 * Select of relative periods (DateRangeFilter::relative()); a valid period
 * that is not among the options (e.g. set by a view) is added to them.
 */
class RelativeRangeField extends DropdownField
{
    public function setValue($value, $data = null): static
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value !== '' && !array_key_exists($value, $this->getSource()) && DateRangeFilter::resolve($value) !== null) {
            $this->setSource($this->getSource() + [$value => DateRangeFilter::describeRelative($value)]);
        }
        return parent::setValue($value, $data);
    }
}
