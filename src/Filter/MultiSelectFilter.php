<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FormField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;

/**
 * Filter with several choosable fixed options (value => label), rendered as a
 * checkbox list. The records match any of the chosen values (OR) or all of
 * them (AND); with choosableMatch() the member picks "oder"/"und" in a second
 * field (request var "<Name>_Match" = any|all).
 *
 *   MultiSelectFilter::create('Sports', 'Sportarten')
 *       ->options(['tennis' => 'Tennis', 'golf' => 'Golf'])
 *       ->field('Sports.Code')                 // or ->apply(fn ($list, array $values, bool $all) => ...)
 *       ->choosableMatch();
 */
class MultiSelectFilter extends TreeFilter
{
    public const string MATCH_ANY = 'any';
    public const string MATCH_ALL = 'all';

    protected bool $matchAll = false;

    protected bool $choosableMatch = false;

    protected ?string $field = null;

    protected ?string $requestMatch = null;

    /**
     * Options as value => label, or a callable returning that array.
     */
    public function options(mixed $opts): static
    {
        return parent::options($opts);
    }

    /**
     * Default combination of the chosen values: all of them (AND) instead of
     * any of them (OR).
     */
    public function matchAll(bool $all = true): static
    {
        $this->matchAll = $all;
        return $this;
    }

    /**
     * Let the member choose OR/AND next to the options.
     */
    public function choosableMatch(bool $flag = true): static
    {
        $this->choosableMatch = $flag;
        return $this;
    }

    /**
     * Filter on this field or relation path (e.g. "Sports.Code") when no
     * apply callable is set.
     */
    public function field(string $field): static
    {
        $this->field = $field;
        return $this;
    }

    public function matchParamName(): string
    {
        return $this->name . '_Match';
    }

    public function hasChoosableMatch(): bool
    {
        return $this->choosableMatch;
    }

    /**
     * The OR/AND choice from the request (FilterCollection sets it).
     */
    public function setRequestMatch(?string $match): static
    {
        $this->requestMatch = $match;
        return $this;
    }

    public function matchesAll(): bool
    {
        if ($this->choosableMatch && in_array($this->requestMatch, [self::MATCH_ANY, self::MATCH_ALL], true)) {
            return $this->requestMatch === self::MATCH_ALL;
        }
        return $this->matchAll;
    }

    protected function resolveOptions(): mixed
    {
        $options = parent::resolveOptions();
        $items = ArrayList::create();
        foreach ((array) $options as $value => $label) {
            $items->push(ArrayData::create(['ID' => $value, 'Title' => (string) $label, 'Children' => []]));
        }
        return $items;
    }

    /**
     * The apply callable gets fn(DataList $list, array $values, bool $all).
     *
     * @param DataList<\SilverStripe\ORM\DataObject> $list
     * @return DataList<\SilverStripe\ORM\DataObject>
     */
    public function applyToList(DataList $list, mixed $value): DataList
    {
        $values = static::values($value);
        if ($values === []) {
            return $list;
        }
        $all = $this->matchesAll();
        if ($this->applyFn) {
            return ($this->applyFn)($list, $values, $all);
        }
        if ($this->field === null) {
            return $list;
        }
        if (!$all) {
            return $list->filter($this->field, $values);
        }
        foreach ($values as $one) {
            $ids = DataList::create($list->dataClass())->filter($this->field, $one)->column('ID');
            $list = $list->filter('ID', $ids ?: [0]);
        }
        return $list;
    }

    public function renderField(): FormField
    {
        $field = parent::renderField();
        if (!$this->choosableMatch) {
            return $field;
        }
        $match = DropdownField::create($this->matchParamName(), _t(self::class . '.MATCH', 'Combine'), [
            self::MATCH_ANY => _t(self::class . '.MATCH_ANY', 'any (or)'),
            self::MATCH_ALL => _t(self::class . '.MATCH_ALL', 'all (and)'),
        ])->setValue($this->matchAll ? self::MATCH_ALL : self::MATCH_ANY);
        return FieldGroup::create($this->label, [$field->setTitle(''), $match])
            ->setName($this->name . '_Group')
            ->addExtraClass('fdk-multi-select-filter');
    }
}
