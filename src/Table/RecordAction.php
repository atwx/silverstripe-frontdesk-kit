<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DataObject;

/**
 * An action on a single record, shown on the record view. It opens a dialog in
 * which the user picks one of the options and may enter a comment, e.g.
 * "Change status" with the reachable statuses as options.
 *
 *   RecordAction::create('status', 'Change status')
 *       ->options(['review' => 'Send to review', 'done' => 'Close'])
 *       ->withComment('Comment')
 *       ->handle(function (DataObject $record, ?string $option, string $comment) {
 *           // ... throw ActionException to refuse
 *           return 'Status changed.';
 *       });
 *
 * Without options the dialog just confirms the action. The handler may return a
 * message (shown after the redirect), an HTTPResponse, or null. Only actions
 * returned by defineRecordActions() for the record can be executed, and only
 * with one of their options.
 */
class RecordAction
{
    /**
     * @var array<string, string>
     */
    private array $options = [];

    private ?string $commentLabel = null;

    private bool $commentRequired = false;

    private string $submitLabel = '';

    private string $description = '';

    /**
     * @var callable|null
     */
    private $handler = null;

    public function __construct(private readonly string $name, private readonly string $label)
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new \InvalidArgumentException("Invalid record action name '{$name}'");
        }
    }

    public static function create(string $name, string $label): static
    {
        return new static($name, $label);
    }

    /**
     * @param array<int|string, string> $options value => label (values are used as strings)
     */
    public function options(array $options): static
    {
        $this->options = [];
        foreach ($options as $value => $label) {
            $this->options[(string) $value] = (string) $label;
        }
        return $this;
    }

    public function withComment(string $label, bool $required = false): static
    {
        $this->commentLabel = $label;
        $this->commentRequired = $required;
        return $this;
    }

    public function submitLabel(string $label): static
    {
        $this->submitLabel = $label;
        return $this;
    }

    public function description(string $text): static
    {
        $this->description = $text;
        return $this;
    }

    /**
     * @param callable(DataObject, ?string, string): (string|HTTPResponse|null) $handler
     */
    public function handle(callable $handler): static
    {
        $this->handler = $handler;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function hasOptions(): bool
    {
        return $this->options !== [];
    }

    public function getCommentLabel(): ?string
    {
        return $this->commentLabel;
    }

    public function isCommentRequired(): bool
    {
        return $this->commentRequired;
    }

    public function getSubmitLabel(): string
    {
        return $this->submitLabel ?: $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Run the action. Validates option and comment; throws ActionException.
     */
    public function run(DataObject $record, ?string $option, string $comment): string|HTTPResponse|null
    {
        if ($this->options !== [] && ($option === null || !array_key_exists($option, $this->options))) {
            throw new ActionException(_t(self::class . '.CHOOSE_OPTION', 'Please choose an option.'));
        }
        if ($this->options === []) {
            $option = null;
        }
        $comment = trim($comment);
        if ($this->commentRequired && $comment === '') {
            throw new ActionException(_t(self::class . '.COMMENT_REQUIRED', 'Please enter a comment.'));
        }
        if (!$this->handler) {
            return null;
        }
        return ($this->handler)($record, $option, $comment);
    }
}
