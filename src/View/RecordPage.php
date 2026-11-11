<?php

namespace Atwx\SilverstripeFrontdeskKit\View;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;

/**
 * One page of a record view ("sub view"), listed in the record's sidebar and
 * shown at <segment>/view/<ID>/<page>. A page is made of blocks, shown in the
 * order they were added:
 *
 *   RecordPage::create('placement', 'Vermittlung')
 *       ->groups(['placement', 'feedback'])          // field groups of the record, editable on this page
 *       ->sublist(ApplicationSubController::class)   // a sub-controller list
 *       ->related(fn ($r) => $r->Child(), ChildController::class)  // view of a related record
 *       ->timeline()                                 // the record's timeline
 *       ->content(fn ($r) => '<p>…</p>');            // own HTML
 *
 * The "Edit" button of a page edits only the fields of its groups.
 */
class RecordPage
{
    use Injectable;

    public const string GROUPS = 'groups';
    public const string SUBLIST = 'sublist';
    public const string RELATED = 'related';
    public const string TIMELINE = 'timeline';
    public const string CONTENT = 'content';

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $blocks = [];

    public function __construct(private string $segment, private string $title)
    {
    }

    public function getSegment(): string
    {
        return $this->segment;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Field groups of the record (keys of its FieldLayout groups).
     *
     * @param array<int, string> $keys
     */
    public function groups(array $keys): static
    {
        $this->blocks[] = ['type' => self::GROUPS, 'keys' => array_values($keys)];
        return $this;
    }

    /**
     * List of one of the controller's sub-controllers.
     *
     * @param class-string $subController
     */
    public function sublist(string $subController, ?string $title = null): static
    {
        $this->blocks[] = ['type' => self::SUBLIST, 'class' => $subController, 'title' => $title];
        return $this;
    }

    /**
     * View of a related record, shown and edited through its own controller
     * (permissions, field layout and sub-lists of that controller apply).
     *
     * @param callable(DataObject): ?DataObject $resolver
     * @param class-string $controller
     */
    public function related(callable $resolver, string $controller, bool $withSublists = true): static
    {
        $this->blocks[] = [
            'type' => self::RELATED,
            'resolver' => $resolver,
            'class' => $controller,
            'sublists' => $withSublists,
        ];
        return $this;
    }

    public function timeline(): static
    {
        $this->blocks[] = ['type' => self::TIMELINE];
        return $this;
    }

    /**
     * Own content; the callable returns HTML.
     *
     * @param callable(DataObject): string $renderer
     */
    public function content(callable $renderer): static
    {
        $this->blocks[] = ['type' => self::CONTENT, 'renderer' => $renderer];
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
     * Keys of all field groups shown on this page.
     *
     * @return array<int, string>
     */
    public function groupKeys(): array
    {
        $keys = [];
        foreach ($this->blocks as $block) {
            if ($block['type'] === self::GROUPS) {
                $keys = array_merge($keys, $block['keys']);
            }
        }
        return array_values(array_unique($keys));
    }
}
