<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;

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

    public function renderValue(DataObject $record): string|DBHTMLText
    {
        $name = $this->name;

        if ($this->formatter) {
            $rawValue = $this->resolveField($record, $name);
            $result = (string) ($this->formatter)($rawValue, $record);
        } else {
            $result = (string) $this->resolveField($record, $name);
        }

        if ($this->type === 'html') {
            return DBHTMLText::create()->setValue($result);
        }

        return $result;
    }

    protected function resolveField(DataObject $record, string $name): mixed
    {
        if (str_contains($name, '.')) {
            return $record->relField($name);
        }

        if ($record->hasMethod($name)) {
            try {
                $ref = new \ReflectionMethod($record, $name);
                if ($ref->getNumberOfRequiredParameters() === 0) {
                    return $record->$name();
                }
            } catch (\ReflectionException $e) {
                // fall through to property access
            }
        }

        return $record->$name;
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
