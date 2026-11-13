<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Forms\FormField;

/**
 * Hierarchical checkbox tree for assigning records from a tree structure
 * (e.g. categories). Nodes are independent: checking a parent does not
 * check its children.
 *
 * The submitted value is a JSON-encoded array of selected IDs, posted via
 * a hidden input. The tree widget itself is rendered client-side by the
 * fdk checkbox-tree component in the kit's JS bundle.
 */
class CheckboxTreeField extends FormField
{
    protected string $titleField;
    protected string $childrenField;

    public function __construct(
        $name,
        $title = null,
        protected $items = null,
        $value = null,
        string $titleField = 'Title',
        string $childrenField = 'Children',
    ) {
        parent::__construct($name, $title, $value);
        $this->titleField = $titleField;
        $this->childrenField = $childrenField;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function ItemsJSON(): string
    {
        $items = [];
        foreach ($this->items as $item) {
            $items[] = $this->buildNode($item);
        }
        return json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    protected function buildNode($item): array
    {
        $name = $item->hasMethod($this->titleField)
            ? $item->{$this->titleField}()
            : $item->{$this->titleField};

        $children = [];
        $childItems = $item->hasMethod($this->childrenField)
            ? $item->{$this->childrenField}()
            : ($item->{$this->childrenField} ?? []);
        foreach ($childItems as $child) {
            $children[] = $this->buildNode($child);
        }

        return [
            'value' => $item->ID,
            'name' => (string) $name,
            'children' => $children,
        ];
    }

    /**
     * The hidden input value: always a JSON array of IDs.
     */
    public function FieldValue(): string
    {
        if (is_array($this->value)) {
            return (string) json_encode($this->value);
        }
        $value = trim((string) $this->value);
        if ($value === '' || $value[0] === '[') {
            return $value ?: '[]';
        }
        // A single value (e.g. from a view saved when the filter was a single choice).
        return (string) json_encode([is_numeric($value) ? (int) $value : $value]);
    }
}
