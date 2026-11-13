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
     * The chosen values of a request value: the field's JSON array, an array,
     * or a single value (e.g. from a link or a view saved when the filter
     * was a single choice). Empty values are dropped.
     *
     * @return array<int, string>
     */
    public static function values(mixed $value): array
    {
        if (is_array($value)) {
            $values = $value;
        } else {
            $string = trim((string) $value);
            if ($string !== '' && $string[0] === '[') {
                // The field's JSON array; broken JSON chooses nothing.
                $decoded = json_decode($string, true);
                $values = is_array($decoded) ? $decoded : [];
            } else {
                $values = [$string];
            }
        }
        $values = array_map(fn ($one) => is_scalar($one) ? (string) $one : '', $values);
        return array_values(array_filter($values, fn (string $one) => $one !== ''));
    }

    /**
     * Decode the JSON value and skip empty selections — note that the
     * field posts the truthy string "[]" when nothing is selected.
     */
    public function applyToList(DataList $list, mixed $value): DataList
    {
        $ids = static::values($value);
        if ($ids === []) {
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
