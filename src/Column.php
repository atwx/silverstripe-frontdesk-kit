<?php

namespace Atwx\SilverstripeFrontdeskKit;

use SilverStripe\ORM\DataObject;

class Column
{
    protected string $name;
    protected string $label = '';
    protected ?string $linkPattern = null;
    protected bool $sortable = false;
    protected string $type = 'text';
    protected $formatter = null;
    protected bool $visibleInExport = true;

    public function __construct(string $name, string $label = '')
    {
        $this->name = $name;
        $this->label = $label ?: $name;
    }

    public static function create(string $name, string $label = ''): static
    {
        return new static($name, $label);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set a link pattern. Use {FieldName} for record field interpolation.
     * Example: 'view/{ID}'
     */
    public function link(string $pattern): static
    {
        $this->linkPattern = $pattern;
        return $this;
    }

    public function getLinkPattern(): ?string
    {
        return $this->linkPattern;
    }

    public function sortable(bool $v = true): static
    {
        $this->sortable = $v;
        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set a custom formatter. Callable receives ($value, $record) and should return a string.
     */
    public function format(callable $fn): static
    {
        $this->formatter = $fn;
        return $this;
    }

    public function visibleInExport(bool $v): static
    {
        $this->visibleInExport = $v;
        return $this;
    }

    public function isVisibleInExport(): bool
    {
        return $this->visibleInExport;
    }

    public function renderValue(DataObject $record): string
    {
        $name = $this->name;

        if ($this->formatter) {
            $rawValue = $record->hasMethod($name) ? $record->$name() : $record->$name;
            return (string) ($this->formatter)($rawValue, $record);
        }

        if ($record->hasMethod($name)) {
            $value = $record->$name();
        } else {
            $value = $record->$name;
        }

        return (string) $value;
    }

    public function renderLink(DataObject $record): string
    {
        if (!$this->linkPattern) {
            return '';
        }

        $link = $this->linkPattern;
        // Replace {FieldName} placeholders with record field values
        preg_match_all('/\{(\w+)\}/', $link, $matches);
        foreach ($matches[1] as $field) {
            $fieldValue = $record->hasMethod($field) ? $record->$field() : $record->$field;
            $link = str_replace('{' . $field . '}', (string) $fieldValue, $link);
        }

        return $link;
    }

    public function Title(): string
    {
        return $this->label;
    }

    public function Field(): string
    {
        return $this->name;
    }
}
