<?php

namespace Atwx\SilverstripeFrontdeskKit\View;

use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use SilverStripe\Core\Injector\Injectable;

/**
 * The pages of a record view in sidebar order, with optional switchers and
 * separators between them (see FrontdeskController::defineRecordPages()).
 *
 * With layout('tabs') the pages are tabs (the first one is the start page),
 * switchers are select fields above the cards, and cards (card(),
 * groupCards()) form a collapsible column on the left. Without cards there
 * is no such column.
 */
class RecordPages
{
    use Injectable;

    public const string LAYOUT_SIDEBAR = 'sidebar';
    public const string LAYOUT_TABS = 'tabs';

    /**
     * @var array<int, RecordPage|RecordSwitcher|string>
     */
    private array $items = [];

    /**
     * Cards and placeholders for groupCards() in their order.
     *
     * @var array<int, RecordCard|array{keys: ?array<int, string>, exclude: array<int, string>}>
     */
    private array $cards = [];

    private string $layout = self::LAYOUT_SIDEBAR;

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
     * 'sidebar' (default: pages listed in a sidebar) or 'tabs' (pages as tabs,
     * cards on the left).
     */
    public function layout(string $layout): static
    {
        if (!in_array($layout, [self::LAYOUT_SIDEBAR, self::LAYOUT_TABS], true)) {
            throw new \InvalidArgumentException("Unknown record page layout '{$layout}'");
        }
        $this->layout = $layout;
        return $this;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function isTabs(): bool
    {
        return $this->layout === self::LAYOUT_TABS;
    }

    /**
     * A card in the left column (layout 'tabs' only).
     */
    public function card(RecordCard $card): static
    {
        $this->cards[] = $card;
        return $this;
    }

    /**
     * One card per group of the record's FieldLayout, at this position: the
     * given group keys (null: all groups) without those in $exclude and
     * without groups that are on an explicit card already. Card key and title
     * are those of the group; it is open as the group is (FieldLayout::group()).
     *
     * @param array<int, string>|null $keys
     * @param array<int, string> $exclude
     */
    public function groupCards(?array $keys = null, array $exclude = []): static
    {
        $this->cards[] = ['keys' => $keys === null ? null : array_values($keys), 'exclude' => array_values($exclude)];
        return $this;
    }

    /**
     * The cards with groupCards() resolved against the record's field layout.
     *
     * @return array<int, RecordCard>
     */
    public function getCards(?FieldLayout $layout): array
    {
        $taken = [];
        foreach ($this->cards as $card) {
            if ($card instanceof RecordCard) {
                $taken = array_merge($taken, $card->groupKeys());
            }
        }
        $cards = [];
        foreach ($this->cards as $card) {
            if ($card instanceof RecordCard) {
                $cards[] = $card;
                continue;
            }
            foreach ($layout ? $layout->getGroups() : [] as $group) {
                $key = $group['key'];
                if (($card['keys'] !== null && !in_array($key, $card['keys'], true))
                    || in_array($key, $card['exclude'], true)
                    || in_array($key, $taken, true)
                ) {
                    continue;
                }
                $taken[] = $key;
                $cards[] = RecordCard::create($key, $group['title'])->groups([$key])->open($group['open']);
            }
        }
        return $cards;
    }

    public function hasCards(): bool
    {
        return $this->cards !== [];
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

    /**
     * @return array<int, RecordSwitcher>
     */
    public function getSwitchers(): array
    {
        return array_values(array_filter($this->items, fn ($item) => $item instanceof RecordSwitcher));
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

    /**
     * A page or card by its key (pages first), e.g. to edit its groups.
     */
    public function findEditable(string $segment, ?FieldLayout $layout): RecordPage|RecordCard|null
    {
        $page = $this->find($segment);
        if ($page) {
            return $page;
        }
        foreach ($this->getCards($layout) as $card) {
            if ($card->getSegment() === $segment) {
                return $card;
            }
        }
        return null;
    }
}
