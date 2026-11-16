<?php

namespace Atwx\SilverstripeFrontdeskKit\View;

use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ModelData;

/**
 * Switch between related records in the sidebar of a record view, e.g. the
 * cases of the same child. Shown between the record pages where it was added.
 */
class RecordSwitcher extends ModelData
{
    /**
     * From this number of options on, the switcher gets a search box (unless
     * searchable() says otherwise).
     */
    private static int $searchable_from = 15;

    private ?bool $searchable = null;

    /**
     * @var array<int, array{title: string, link: string, active: bool}>
     */
    private array $options = [];

    private ?string $addLink = null;

    private string $addTitle = '+';

    public function option(string $title, string $link, bool $active = false): static
    {
        $this->options[] = ['title' => $title, 'link' => $link, 'active' => $active];
        return $this;
    }

    /**
     * Link to add another related record (e.g. a new case for the same child).
     */
    public function withAddLink(string $link, string $title = '+'): static
    {
        $this->addLink = $link;
        $this->addTitle = $title;
        return $this;
    }

    /**
     * Search box above the options: true or false, null = from searchable_from
     * options on.
     */
    public function searchable(?bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }

    public function IsSearchable(): bool
    {
        return $this->searchable ?? count($this->options) >= (int) static::config()->get('searchable_from');
    }

    public function Options(): ArrayList
    {
        return ArrayList::create(array_map(fn (array $o) => ArrayData::create([
            'Title' => $o['title'],
            'Link' => $o['link'],
            'Active' => $o['active'],
            'SearchText' => mb_strtolower($o['title']),
        ]), $this->options));
    }

    public function ActiveTitle(): string
    {
        foreach ($this->options as $option) {
            if ($option['active']) {
                return $option['title'];
            }
        }
        return '';
    }

    public function AddLink(): ?string
    {
        return $this->addLink;
    }

    public function AddTitle(): string
    {
        return $this->addTitle;
    }
}
