<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use SilverStripe\Control\Director;

/**
 * Message of an action with links, e.g. "3 schools added" with a link to each:
 *
 *   return ActionResult::create('3 Schulen hinzugefügt.')
 *       ->link('Eton', $eton->Link())
 *       ->link('Harrow', $harrow->Link());
 *
 * Returned by a record action handler instead of a string; shown once on the
 * next page (FlashMessage, with Links).
 */
class ActionResult
{
    /**
     * @var array<int, array{Title: string, Link: string}>
     */
    private array $links = [];

    public function __construct(private string $text, private string $type = 'success')
    {
    }

    public static function create(string $text, string $type = 'success'): static
    {
        return new static($text, $type);
    }

    /**
     * A link below the message; only links within the site are kept.
     */
    public function link(string $title, string $url): static
    {
        if ($url !== '' && Director::is_site_url($url)) {
            $this->links[] = ['Title' => $title, 'Link' => $url];
        }
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<int, array{Title: string, Link: string}>
     */
    public function getLinks(): array
    {
        return $this->links;
    }
}
