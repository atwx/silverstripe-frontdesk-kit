<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Forms\FormField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;

/**
 * Columns of an export in their order, each with an optional width: a list
 * of chosen columns (move up/down or drag, width, remove) and a select to
 * add more. The value is JSON [{name, width}]; without JavaScript it is a
 * text field with one column name per line.
 */
class ExportColumnsField extends FormField
{
    /**
     * @param array<string, string> $choices column name => label
     */
    public function __construct(string $name, ?string $title = null, private array $choices = [])
    {
        parent::__construct($name, $title);
    }

    /**
     * @return array<string, string>
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Columns of a submitted value: JSON, or names one per line.
     *
     * @return array<int, array{name: string, width: ?int}>
     */
    public static function decode(string $value): array
    {
        $decoded = json_decode($value, true);
        $entries = is_array($decoded) ? $decoded : preg_split('/\R/', $value);
        $columns = [];
        $seen = [];
        foreach ($entries ?: [] as $entry) {
            $name = trim(is_array($entry) ? (string) ($entry['name'] ?? '') : (string) $entry);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $width = is_array($entry) && isset($entry['width']) && (int) $entry['width'] > 0 ? (int) $entry['width'] : null;
            $columns[] = ['name' => $name, 'width' => $width];
        }
        return $columns;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function ChoicesJson(): string
    {
        $choices = [];
        foreach ($this->choices as $name => $label) {
            $choices[] = ['name' => (string) $name, 'title' => $label];
        }
        return (string) json_encode($choices);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function ValueJson(): string
    {
        return (string) json_encode(self::decode((string) $this->getValue()));
    }

    /**
     * The chosen columns for the template without JavaScript.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- template method
    public function ChosenNames(): string
    {
        return implode("\n", array_column(self::decode((string) $this->getValue()), 'name'));
    }

    public function Type(): string
    {
        return 'exportcolumns';
    }
}
