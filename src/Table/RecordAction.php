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
 * Selects ask for several values at once, e.g. one person per responsibility:
 *
 *   RecordAction::create('assign', 'Responsibility')
 *       ->select('lead', 'Lead', [3 => 'Ann', 5 => 'Bob'], '3')
 *       ->select('deputy', 'Deputy', [3 => 'Ann', 5 => 'Bob'])
 *       ->handle(fn (DataObject $record, ?string $option, string $comment, array $values) => …);
 *
 * Only declared selects reach the handler, each with one of its options or ''
 * (nothing chosen).
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
     * @var array<string, array{label: string, options: array<string, string>, value: string, empty: string}>
     */
    private array $selects = [];

    /**
     * @var array<string, array{label: string, options: array<string, string>, values: array<int, string>}>
     */
    private array $multiSelects = [];

    /**
     * @var array<string, array{label: string, type: string, value: string, required: bool}>
     */
    private array $inputs = [];

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

    /**
     * A select in the dialog (value '' = nothing chosen).
     *
     * @param array<int|string, string> $options value => label
     */
    public function select(string $name, string $label, array $options, ?string $value = null, string $emptyLabel = '–'): static
    {
        $this->assertFreeName($name);
        $map = [];
        foreach ($options as $key => $optionLabel) {
            $map[(string) $key] = (string) $optionLabel;
        }
        $this->selects[$name] = ['label' => $label, 'options' => $map, 'value' => (string) $value, 'empty' => $emptyLabel];
        return $this;
    }

    /**
     * @return array<string, array{label: string, options: array<string, string>, value: string, empty: string}>
     */
    public function getSelects(): array
    {
        return $this->selects;
    }

    /**
     * Checkboxes in the dialog: several of the options (e.g. several schools
     * at once). The handler gets the chosen values as a list.
     *
     * @param array<int|string, string> $options value => label
     * @param array<int, int|string> $values Checked at first
     */
    public function multiSelect(string $name, string $label, array $options, array $values = []): static
    {
        $this->assertFreeName($name);
        $map = [];
        foreach ($options as $key => $optionLabel) {
            $map[(string) $key] = (string) $optionLabel;
        }
        $this->multiSelects[$name] = [
            'label' => $label,
            'options' => $map,
            'values' => array_map('strval', array_values($values)),
        ];
        return $this;
    }

    /**
     * @return array<string, array{label: string, options: array<string, string>, values: array<int, string>}>
     */
    public function getMultiSelects(): array
    {
        return $this->multiSelects;
    }

    /**
     * An input in the dialog: text, date (YYYY-MM-DD), datetime-local
     * (YYYY-MM-DDTHH:MM), time (HH:MM) or number. The handler gets the value
     * as a string ('' = empty).
     */
    public function input(
        string $name,
        string $label,
        string $type = 'text',
        ?string $value = null,
        bool $required = false
    ): static {
        $this->assertFreeName($name);
        if (!in_array($type, ['text', 'date', 'datetime-local', 'time', 'number'], true)) {
            throw new \InvalidArgumentException("Invalid input type '{$type}'");
        }
        $this->inputs[$name] = [
            'label' => $label,
            'type' => $type,
            'value' => (string) $value,
            'required' => $required,
        ];
        return $this;
    }

    /**
     * @return array<string, array{label: string, type: string, value: string, required: bool}>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    private function assertFreeName(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new \InvalidArgumentException("Invalid field name '{$name}'");
        }
        if (isset($this->selects[$name]) || isset($this->multiSelects[$name]) || isset($this->inputs[$name])) {
            throw new \InvalidArgumentException("Field name '{$name}' is used twice");
        }
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
     * The handler gets the record, the chosen option, the comment and the
     * values of the selects and inputs (strings) and multi-selects (lists).
     *
     * @param callable(DataObject, ?string, string, array<string, string|array<int, string>>): (string|ActionResult|HTTPResponse|null) $handler
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
     * Run the action. Validates option, selects and comment; throws ActionException.
     *
     * @param array<string, mixed> $values Submitted values of the selects
     */
    public function run(
        DataObject $record,
        ?string $option,
        string $comment,
        array $values = []
    ): string|ActionResult|HTTPResponse|null
    {
        $selected = [];
        foreach ($this->selects as $name => $select) {
            $value = (string) ($values[$name] ?? '');
            if ($value !== '' && !array_key_exists($value, $select['options'])) {
                throw new ActionException(_t(self::class . '.CHOOSE_OPTION', 'Please choose an option.'));
            }
            $selected[$name] = $value;
        }
        foreach ($this->multiSelects as $name => $multi) {
            $chosen = array_values(array_unique(array_map('strval', array_filter(
                is_array($values[$name] ?? null) ? $values[$name] : [],
                'is_scalar'
            ))));
            foreach ($chosen as $value) {
                if (!array_key_exists($value, $multi['options'])) {
                    throw new ActionException(_t(self::class . '.CHOOSE_OPTION', 'Please choose an option.'));
                }
            }
            $selected[$name] = $chosen;
        }
        foreach ($this->inputs as $name => $input) {
            $value = is_scalar($values[$name] ?? null) ? trim((string) $values[$name]) : '';
            if ($value === '' && $input['required']) {
                throw new ActionException(_t(
                    self::class . '.FIELD_REQUIRED',
                    'Please fill in "{field}".',
                    ['field' => $input['label']]
                ));
            }
            if ($value !== '' && !self::validInput($input['type'], $value)) {
                throw new ActionException(_t(
                    self::class . '.FIELD_INVALID',
                    '"{field}" is not valid.',
                    ['field' => $input['label']]
                ));
            }
            $selected[$name] = $value;
        }
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
        return ($this->handler)($record, $option, $comment, $selected);
    }

    private static function validInput(string $type, string $value): bool
    {
        $formats = [
            'date' => ['Y-m-d'],
            'datetime-local' => ['Y-m-d\\TH:i', 'Y-m-d\\TH:i:s'],
            'time' => ['H:i', 'H:i:s'],
        ];
        if ($type === 'number') {
            return is_numeric($value);
        }
        foreach ($formats[$type] ?? [] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return true;
            }
        }
        return !isset($formats[$type]);
    }
}
