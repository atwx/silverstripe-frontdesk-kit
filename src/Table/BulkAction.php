<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use SilverStripe\Control\HTTPResponse;
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
 */
class BulkAction
{
    protected array $options = [];

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

    public function handle(DataList $records, ?string $option): HTTPResponse|string|null
    {
        return ($this->handler)($records, $option);
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
            $choices->push(ArrayData::create(['Value' => $this->name, 'Title' => $this->label]));
        }
        foreach ($this->options as $value => $label) {
            $choices->push(ArrayData::create(['Value' => $this->name . ':' . $value, 'Title' => $label]));
        }
        return $choices;
    }
}
