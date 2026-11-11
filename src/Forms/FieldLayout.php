<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;

/**
 * Arranges the fields of a record in groups and gives every field an access
 * level: write (editable), read (shown read-only) or hidden (left out).
 *
 * The same layout is used for the edit form, the record view and saving: on
 * save the controller resets every change to a field that is not writable, so
 * a manipulated POST cannot change a locked field.
 *
 *   FieldLayout::create()
 *       ->group('Basics', ['Title', 'Date'])
 *       ->group('Internal', ['Note'])
 *       ->setLevels(['Title' => FieldLayout::READ, 'Note' => FieldLayout::HIDDEN])
 *       ->setDefaultLevel(FieldLayout::WRITE);
 *
 * Field names are form field names (e.g. "VenueID" for a has_one). Fields that
 * belong to no group are shown after the groups.
 */
class FieldLayout
{
    use Injectable;

    public const string WRITE = 'write';
    public const string READ = 'read';
    public const string HIDDEN = 'hidden';

    /**
     * Fields that are never reset on save (managed by the ORM).
     */
    private const array SYSTEM_FIELDS = ['ID', 'ClassName', 'RecordClassName', 'Created', 'LastEdited'];

    /**
     * @var array<int, array{title: string, fields: array<int, string>, open: bool, key: string}>
     */
    private array $groups = [];

    /**
     * @var array<string, string>
     */
    private array $levels = [];

    private string $defaultLevel = self::WRITE;

    /**
     * @param array<int, string> $fields
     * @param string|null $key Stable key of the group (default: its title), e.g. to show it on a record page
     */
    public function group(string $title, array $fields, bool $open = true, ?string $key = null): static
    {
        $this->groups[] = [
            'title' => $title,
            'fields' => array_values($fields),
            'open' => $open,
            'key' => $key ?? $title,
        ];
        return $this;
    }

    /**
     * Copy of this layout that only shows the given groups: fields of other groups
     * and fields in no group become hidden (so they are left out of the form and a
     * change to them is reset on save). Groups keep their order.
     *
     * @param array<int, string> $keys
     */
    public function onlyGroups(array $keys): static
    {
        $layout = clone $this;
        $layout->groups = array_values(array_filter(
            $this->groups,
            fn (array $group) => in_array($group['key'], $keys, true)
        ));
        $keep = [];
        foreach ($layout->groups as $group) {
            foreach ($group['fields'] as $name) {
                $keep[$name] = true;
            }
        }
        $layout->defaultLevel = self::HIDDEN;
        foreach (array_keys($this->levels) as $name) {
            if (!isset($keep[$name])) {
                $layout->levels[$name] = self::HIDDEN;
            }
        }
        foreach (array_keys($keep) as $name) {
            $layout->levels[$name] = $this->levelFor($name);
        }
        return $layout;
    }

    /**
     * Whether any field of the given groups is visible (or, with $writable, writable).
     *
     * @param array<int, string> $keys
     */
    public function hasFieldsIn(array $keys, bool $writable = false): bool
    {
        foreach ($this->groups as $group) {
            if (!in_array($group['key'], $keys, true)) {
                continue;
            }
            foreach ($group['fields'] as $name) {
                if ($writable ? $this->isWritable($name) : $this->isVisible($name)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function setLevel(string $field, string $level): static
    {
        $this->levels[$field] = self::assertLevel($level);
        return $this;
    }

    /**
     * @param array<string, string> $levels
     */
    public function setLevels(array $levels): static
    {
        foreach ($levels as $field => $level) {
            $this->setLevel((string) $field, $level);
        }
        return $this;
    }

    /**
     * Level of fields without an explicit level (default: write).
     */
    public function setDefaultLevel(string $level): static
    {
        $this->defaultLevel = self::assertLevel($level);
        return $this;
    }

    public function levelFor(string $field): string
    {
        return $this->levels[$field] ?? $this->defaultLevel;
    }

    public function isWritable(string $field): bool
    {
        return $this->levelFor($field) === self::WRITE;
    }

    public function isVisible(string $field): bool
    {
        return $this->levelFor($field) !== self::HIDDEN;
    }

    /**
     * @return array<int, array{title: string, fields: array<int, string>, open: bool, key: string}>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Apply levels and groups to (scaffolded) form fields: hidden fields are
     * removed, read fields become read-only, grouped fields are moved into
     * collapsible groups. Hidden form fields (e.g. ID) are left alone.
     */
    public function applyTo(FieldList $fields): FieldList
    {
        $byName = [];
        foreach ($fields->dataFields() as $field) {
            $name = $field->getName();
            if ($field instanceof HiddenField) {
                continue;
            }
            $level = $this->levelFor($name);
            if ($level === self::HIDDEN) {
                $fields->removeByName($name, true);
                continue;
            }
            if ($level === self::READ) {
                $readonly = $field->performReadonlyTransformation();
                $fields->replaceField($name, $readonly);
                $field = $readonly;
            }
            $byName[$name] = $field;
        }

        $result = FieldList::create();
        $grouped = [];
        foreach ($this->groups as $group) {
            $children = [];
            foreach ($group['fields'] as $name) {
                if (isset($byName[$name]) && !isset($grouped[$name])) {
                    $children[] = $byName[$name];
                    $grouped[$name] = true;
                }
            }
            if ($children) {
                $result->push(
                    CollapsibleFieldGroup::create($group['title'], $children)->setOpen($group['open'])
                );
            }
        }
        foreach ($fields as $field) {
            /** @var FormField $field */
            if (!isset($grouped[$field->getName()])) {
                $result->push($field);
            }
        }
        return $result;
    }

    /**
     * Undo changes to fields that are not writable (call after saveInto()).
     * Covers database fields and other values set on the record (e.g. virtual
     * fields a form saves into).
     *
     * @return array<int, string> Names of the fields that were reset
     */
    public function resetLockedChanges(DataObject $record): array
    {
        $reset = [];
        foreach ($record->getChangedFields(false, DataObject::CHANGE_VALUE) as $name => $change) {
            if (in_array($name, self::SYSTEM_FIELDS, true) || $this->isWritable($name)) {
                continue;
            }
            $record->setField($name, $change['before']);
            $reset[] = $name;
        }
        return $reset;
    }

    /**
     * Groups of view rows for the record view. $rows maps field names to rows
     * (ArrayData with Label, Value, Type); hidden fields and empty groups are
     * left out, rows of ungrouped visible fields end up in a last group without
     * a title.
     *
     * @param array<string, ArrayData> $rows
     * @return ArrayList<ArrayData>
     */
    public function viewGroups(array $rows): ArrayList
    {
        $groups = ArrayList::create();
        $grouped = [];
        foreach ($this->groups as $group) {
            $groupRows = ArrayList::create();
            foreach ($group['fields'] as $name) {
                if (isset($rows[$name]) && !isset($grouped[$name]) && $this->isVisible($name)) {
                    $groupRows->push($rows[$name]);
                }
                $grouped[$name] = true;
            }
            if ($groupRows->count()) {
                $groups->push(ArrayData::create(['Title' => $group['title'], 'Rows' => $groupRows]));
            }
        }
        $other = ArrayList::create();
        foreach ($rows as $name => $row) {
            if (!isset($grouped[$name]) && $this->isVisible($name)) {
                $other->push($row);
            }
        }
        if ($other->count()) {
            $groups->push(ArrayData::create(['Title' => '', 'Rows' => $other]));
        }
        return $groups;
    }

    private static function assertLevel(string $level): string
    {
        if (!in_array($level, [self::WRITE, self::READ, self::HIDDEN], true)) {
            throw new \InvalidArgumentException("Unknown field level '{$level}'");
        }
        return $level;
    }
}
