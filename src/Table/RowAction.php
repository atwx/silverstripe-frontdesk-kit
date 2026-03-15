<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

class RowAction
{
    protected string $label;
    protected string $url;
    protected string $method = 'get';
    protected string $icon = '';
    protected ?string $confirmMessage = null;
    protected bool $isDelete = false;
    protected bool $isHtmx = false;
    protected string $target = '';
    protected $enabledCondition = true;

    public function __construct(string $label, string $url)
    {
        $this->label = $label;
        $this->url = $url;
    }

    public static function link(string $label, string $url): static
    {
        return new static($label, $url);
    }

    public static function delete(string $url): static
    {
        $action = new static(
            _t('Atwx\\SilverstripeFrontdeskKit\\Controller\\FrontdeskController.ACTION_DELETE', 'Delete'),
            $url
        );
        $action->isDelete = true;
        $action->confirmMessage = _t('Atwx\\SilverstripeFrontdeskKit\\Controller\\FrontdeskController.CONFIRM_DELETE', 'Are you sure you want to delete this record?');
        $action->icon = 'trash';
        return $action;
    }

    public static function htmx(string $label, string $url, string $method = 'get'): static
    {
        $action = new static($label, $url);
        $action->isHtmx = true;
        $action->method = $method;
        return $action;
    }

    public function withIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function enabled(callable|bool $condition): static
    {
        $this->enabledCondition = $condition;
        return $this;
    }

    public function withTarget(string $target): static
    {
        $this->target = $target;
        return $this;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function withConfirm(string $message): static
    {
        $this->confirmMessage = $message;
        return $this;
    }

    public function isEnabled(): bool
    {
        if (is_callable($this->enabledCondition)) {
            return (bool) ($this->enabledCondition)();
        }
        return (bool) $this->enabledCondition;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getConfirmMessage(): ?string
    {
        return $this->confirmMessage;
    }

    public function isDeleteAction(): bool
    {
        return $this->isDelete;
    }

    public function isHtmxAction(): bool
    {
        return $this->isHtmx;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    // Template accessors
    public function Label(): string { return $this->label; }
    public function Url(): string { return $this->url; }
    public function Icon(): string { return $this->icon; }
    public function ConfirmMessage(): ?string { return $this->confirmMessage; }
    public function IsDelete(): bool { return $this->isDelete; }
    public function IsHtmx(): bool { return $this->isHtmx; }
    public function Method(): string { return $this->method; }
    public function HasConfirm(): bool { return $this->confirmMessage !== null; }
    public function HasIcon(): bool { return $this->icon !== ''; }
    public function Target(): string { return $this->target; }
    public function HasTarget(): bool { return $this->target !== ''; }
}
