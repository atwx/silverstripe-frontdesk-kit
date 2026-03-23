<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormField;

class SelectFilter extends Filter
{
    protected mixed $optionSource = [];

    public static function create(string $name, string $label): static
    {
        return new static($name, $label);
    }

    /**
     * Set options as an array or a callable that returns an array/SS_Map.
     */
    public function options(array|callable $opts): static
    {
        $this->optionSource = $opts;
        return $this;
    }

    protected function resolveOptions(): mixed
    {
        if (is_callable($this->optionSource)) {
            return ($this->optionSource)();
        }
        return $this->optionSource;
    }

    public function renderField(): FormField
    {
        $field = DropdownField::create($this->name, $this->label, $this->resolveOptions())
            ->setEmptyString('Alle');
        if ($this->default !== null) {
            $field->setValue($this->default);
        }
        return $field;
    }
}
