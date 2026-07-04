<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use Atwx\SilverstripeFrontdeskKit\Forms\CheckboxTreeField;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\DataList;

/**
 * Filter rendered as a CheckboxTreeField. The request value is the field's
 * JSON-encoded ID array; the apply callable receives the decoded array:
 *
 *   TreeFilter::create('Categories-1', 'Gliederung 1')
 *       ->options(fn() => Category::get()->filter('ParentID', 0))
 *       ->apply(fn($list, array $ids) => $list->filter('Categories.ID', $ids))
 */
class TreeFilter extends Filter
{
    protected mixed $optionSource = [];
    protected string $titleField = 'Title';
    protected string $childrenField = 'Children';

    public static function create(string $name, string $label): static
    {
        return new static($name, $label);
    }

    /**
     * Set the tree's top-level items: an iterable of DataObjects
     * or a callable returning one.
     */
    public function options(mixed $opts): static
    {
        $this->optionSource = $opts;
        return $this;
    }

    public function titleField(string $field): static
    {
        $this->titleField = $field;
        return $this;
    }

    public function childrenField(string $field): static
    {
        $this->childrenField = $field;
        return $this;
    }

    protected function resolveOptions(): mixed
    {
        if (is_callable($this->optionSource)) {
            return ($this->optionSource)();
        }
        return $this->optionSource;
    }

    /**
     * Decode the JSON value and skip empty selections — note that the
     * field posts the truthy string "[]" when nothing is selected.
     */
    public function applyToList(DataList $list, mixed $value): DataList
    {
        $ids = is_array($value) ? $value : json_decode((string) $value, true);
        if (!is_array($ids) || $ids === []) {
            return $list;
        }

        if ($this->applyFn) {
            return ($this->applyFn)($list, $ids);
        }

        return $list;
    }

    public function renderField(): FormField
    {
        $field = CheckboxTreeField::create(
            $this->name,
            $this->label,
            $this->resolveOptions(),
            null,
            $this->titleField,
            $this->childrenField
        );
        if ($this->default !== null) {
            $field->setValue($this->default);
        }
        return $field;
    }
}
