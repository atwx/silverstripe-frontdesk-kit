<?php

namespace Atwx\SilverstripeFrontdeskKit\View;

use SilverStripe\Core\Injector\Injectable;

/**
 * The pages of a record view in sidebar order, with optional switchers and
 * separators between them (see FrontdeskController::defineRecordPages()).
 */
class RecordPages
{
    use Injectable;

    /**
     * @var array<int, RecordPage|RecordSwitcher|string>
     */
    private array $items = [];

    public function page(RecordPage $page): static
    {
        $this->items[] = $page;
        return $this;
    }

    public function switcher(?RecordSwitcher $switcher): static
    {
        if ($switcher) {
            $this->items[] = $switcher;
        }
        return $this;
    }

    public function separator(): static
    {
        $this->items[] = 'separator';
        return $this;
    }

    /**
     * @return array<int, RecordPage|RecordSwitcher|string>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<int, RecordPage>
     */
    public function getPages(): array
    {
        return array_values(array_filter($this->items, fn ($item) => $item instanceof RecordPage));
    }

    public function find(string $segment): ?RecordPage
    {
        foreach ($this->getPages() as $page) {
            if ($page->getSegment() === $segment) {
                return $page;
            }
        }
        return null;
    }
}
