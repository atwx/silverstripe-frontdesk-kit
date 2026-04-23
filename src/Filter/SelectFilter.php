<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use Atwx\SilverstripeFrontdeskKit\Forms\SearchableDropdownField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormField;

class SelectFilter extends Filter
{
    protected mixed $optionSource = [];

    protected bool $searchable = false;

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

    public function searchable(bool $flag = true): static
    {
        $this->searchable = $flag;
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
        $class = $this->searchable ? SearchableDropdownField::class : DropdownField::class;
        $field = $class::create($this->name, $this->label, $this->resolveOptions())
            ->setEmptyString('Alle');
        if ($this->default !== null) {
            $field->setValue($this->default);
        }
        return $field;
    }
}
