<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use SilverStripe\Forms\FormField;
use SilverStripe\Forms\TextField;

class TextFilter extends Filter
{
    protected ?string $placeholder = null;

    protected ?int $size = null;

    public static function create(string $name, string $label): static
    {
        return new static($name, $label);
    }

    public function placeholder(string $text): static
    {
        $this->placeholder = $text;
        return $this;
    }

    public function size(int $chars): static
    {
        $this->size = $chars;
        return $this;
    }

    public function renderField(): FormField
    {
        $field = TextField::create($this->name, $this->label);
        if ($this->placeholder !== null) {
            $field->setAttribute('placeholder', $this->placeholder);
        }
        if ($this->size !== null) {
            $field->setAttribute('size', (string) $this->size);
            // `w-full` (applied by FrontdeskFormFieldExtension) overrides the size
            // attribute visually, so pin the CSS width too.
            $field->setAttribute('style', sprintf('width: %drem;', max(4, (int) ceil($this->size * 0.7))));
        }
        return $field;
    }
}
