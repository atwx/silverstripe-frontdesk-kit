<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use SilverStripe\Forms\FormField;
use SilverStripe\Forms\TextField;

class TextFilter extends Filter
{
    public static function create(string $name, string $label): static
    {
        return new static($name, $label);
    }

    public function renderField(): FormField
    {
        return TextField::create($this->name, $this->label);
    }
}
