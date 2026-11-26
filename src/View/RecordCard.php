<?php

namespace Atwx\SilverstripeFrontdeskKit\View;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;

/**
 * A collapsible card in the left column of a record view with the layout
 * "tabs" (RecordPages::layout()). It holds the same blocks as a record page:
 *
 *   RecordCard::create('family', 'Familie')
 *       ->related(fn ($r) => $r->Family(), FamilyController::class)   // compact view
 *       ->open(false)                                                 // closed at first
 *       ->summary(fn ($r) => $r->Family()->Title);                    // line shown while closed
 *
 * A card with groups() gets an edit link that edits only its groups
 * (edit/<ID>?page=<key>); keys of cards and pages share one namespace.
 * A card without any visible content is left out.
 */
class RecordCard
{
    use Injectable;
    use RecordBlocks;

    /**
     * @var bool|callable(DataObject): bool
     */
    private $open = true;

    /**
     * @var callable(DataObject): string|null
     */
    private $summary = null;

    public function __construct(private string $key, private string $title)
    {
    }

    public function getSegment(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Whether the card is open when the member has not toggled it yet (the
     * browser remembers the member's choice per card). A callable decides per
     * record, e.g. by its status.
     *
     * @param bool|callable(DataObject): bool $open
     */
    public function open(bool|callable $open = true): static
    {
        $this->open = $open;
        return $this;
    }

    public function isOpenFor(DataObject $record): bool
    {
        return is_callable($this->open) ? (bool) ($this->open)($record) : (bool) $this->open;
    }

    /**
     * Short text shown under the title while the card is closed (plain text,
     * escaped), e.g. "geb. 03.2010 · Klasse 10".
     *
     * @param callable(DataObject): ?string $summary
     */
    public function summary(callable $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function summaryFor(DataObject $record): string
    {
        return $this->summary ? trim((string) ($this->summary)($record)) : '';
    }
}
