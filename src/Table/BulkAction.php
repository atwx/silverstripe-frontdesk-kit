<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;

/**
 * An action applied to the records selected in a list (checkbox column).
 *
 * The handler receives the selected records (already restricted to the
 * controller's base list) and the chosen option, if any. It may return an
 * HTTPResponse (e.g. a file download), a message shown after the redirect back
 * to the list, or null.
 *
 *   BulkAction::create('status', 'Status ändern', fn (DataList $records, ?string $status) => …)
 *       ->options(['confirmed' => 'Bestätigt', 'cancelled' => 'Storniert'])
 *
 * With fields(), choosing the action opens a dialog with these fields ("for N
 * entries"); the handler then receives the records and the field values:
 *
 *   BulkAction::create('assign', 'Zuteilen', fn (DataList $records, array $data) => …)
 *       ->fields(fn () => FieldList::create(
 *           DropdownField::create('Area', 'Bereich', $areas),
 *           DropdownField::create('Status', 'Status', $statuses)->setValue('invited')
 *       ), ['Area', 'Status'])
 */
class BulkAction
{
    protected array $options = [];

    protected bool $newWindow = false;

    protected string $confirmText = '';

    /** @var FieldList|callable|null */
    protected $fields = null;

    /** @var array<int, string> */
    protected array $requiredFields = [];

    protected string $submitLabel = '';

    /** @var callable */
    protected $handler;

    public function __construct(protected string $name, protected string $label, callable $handler)
    {
        $this->handler = $handler;
    }

    public static function create(string $name, string $label, callable $handler): static
    {
        return new static($name, $label, $handler);
    }

    /**
     * Choices offered for this action (value => label), e.g. target statuses.
     */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Fields asked for in a dialog before the action runs (a FieldList or a
     * callable returning one, built when the dialog opens; field values are
     * the defaults). The handler then gets (DataList $records, array $data)
     * with the values of these fields. $required: names of required fields.
     *
     * @param array<int, string> $required
     */
    public function fields(FieldList|callable $fields, array $required = []): static
    {
        $this->fields = $fields;
        $this->requiredFields = $required;
        return $this;
    }

    public function hasFields(): bool
    {
        return $this->fields !== null;
    }

    /**
     * A fresh field list for the dialog (empty without fields()).
     */
    public function getFields(): FieldList
    {
        $fields = is_callable($this->fields) ? ($this->fields)() : $this->fields;
        if (!$fields instanceof FieldList) {
            return FieldList::create();
        }
        // A FieldList given directly is shared: copy its fields for each form.
        if (is_callable($this->fields)) {
            return $fields;
        }
        return FieldList::create(array_map(fn ($field) => clone $field, $fields->toArray()));
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredFields(): array
    {
        return $this->requiredFields;
    }

    /**
     * Label of the dialog's submit button (default: the action's label).
     */
    public function submitLabel(string $label): static
    {
        $this->submitLabel = $label;
        return $this;
    }

    public function getSubmitLabel(): string
    {
        return $this->submitLabel !== '' ? $this->submitLabel : $this->label;
    }

    /**
     * Submit into a new tab, for responses shown in the browser (e.g. a PDF),
     * so the list stays open.
     */
    public function newWindow(bool $newWindow = true): static
    {
        $this->newWindow = $newWindow;
        return $this;
    }

    /**
     * Asks before running the action (e.g. deleting), with this text.
     */
    public function confirm(string $text): static
    {
        $this->confirmText = $text;
        return $this;
    }

    public function getConfirm(): string
    {
        return $this->confirmText;
    }

    public function opensNewWindow(): bool
    {
        return $this->newWindow;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Runs the handler: with fields() the second argument is the array of field
     * values, otherwise the chosen option (or null).
     *
     * @param string|array<string, mixed>|null $optionOrData
     */
    public function handle(DataList $records, string|array|null $optionOrData): HTTPResponse|string|null
    {
        return ($this->handler)($records, $optionOrData);
    }

    // Template accessors

    public function Label(): string
    {
        return $this->label;
    }

    public function HasOptions(): bool
    {
        return (bool) $this->options;
    }

    /**
     * Select entries: "<name>" or "<name>:<option>".
     */
    public function Choices(): ArrayList
    {
        $choices = ArrayList::create();
        if (!$this->options) {
            $choices->push(ArrayData::create([
                'Value' => $this->name,
                'Title' => $this->label,
                'NewWindow' => $this->newWindow,
                'Confirm' => $this->confirmText,
                'HasFields' => $this->hasFields(),
            ]));
        }
        foreach ($this->options as $value => $label) {
            $choices->push(ArrayData::create([
                'Value' => $this->name . ':' . $value,
                'Title' => $label,
                'NewWindow' => $this->newWindow,
                'Confirm' => $this->confirmText,
            ]));
        }
        return $choices;
    }
}
