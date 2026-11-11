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

    public function Options(): ArrayList
    {
        return ArrayList::create(array_map(fn (array $o) => ArrayData::create([
            'Title' => $o['title'],
            'Link' => $o['link'],
            'Active' => $o['active'],
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
