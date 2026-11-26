<?php

namespace Atwx\SilverstripeFrontdeskKit\View;

use SilverStripe\ORM\DataObject;

/**
 * Blocks of a record page (tab) or card, shown in the order they were added.
 * Any block can be placed on a page or on a card (RecordPage, RecordCard).
 */
trait RecordBlocks
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $blocks = [];

    /**
     * Field groups of the record (keys of its FieldLayout groups).
     *
     * @param array<int, string> $keys
     */
    public function groups(array $keys): static
    {
        $this->blocks[] = ['type' => RecordPage::GROUPS, 'keys' => array_values($keys)];
        return $this;
    }

    /**
     * List of one of the controller's sub-controllers.
     *
     * @param class-string $subController
     */
    public function sublist(string $subController, ?string $title = null): static
    {
        $this->blocks[] = ['type' => RecordPage::SUBLIST, 'class' => $subController, 'title' => $title];
        return $this;
    }

    /**
     * View of a related record, shown and edited through its own controller
     * (permissions, field layout and sub-lists of that controller apply). On
     * a card it is shown compact (FrontdeskController::defineCompactFields()).
     *
     * @param callable(DataObject): ?DataObject $resolver
     * @param class-string $controller
     */
    public function related(callable $resolver, string $controller, bool $withSublists = true): static
    {
        $this->blocks[] = [
            'type' => RecordPage::RELATED,
            'resolver' => $resolver,
            'class' => $controller,
            'sublists' => $withSublists,
        ];
        return $this;
    }

    public function timeline(): static
    {
        $this->blocks[] = ['type' => RecordPage::TIMELINE];
        return $this;
    }

    /**
     * Own content; the callable returns HTML (an empty string hides the block).
     *
     * @param callable(DataObject): string $renderer
     */
    public function content(callable $renderer): static
    {
        $this->blocks[] = ['type' => RecordPage::CONTENT, 'renderer' => $renderer];
        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Keys of all field groups shown in these blocks.
     *
     * @return array<int, string>
     */
    public function groupKeys(): array
    {
        $keys = [];
        foreach ($this->blocks as $block) {
            if ($block['type'] === RecordPage::GROUPS) {
                $keys = array_merge($keys, $block['keys']);
            }
        }
        return array_values(array_unique($keys));
    }
}
