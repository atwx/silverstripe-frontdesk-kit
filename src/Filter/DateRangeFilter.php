<?php

namespace Atwx\SilverstripeFrontdeskKit\Filter;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\DataList;

/**
 * Date range "from – to" (request vars <Name>_From, <Name>_To).
 *
 * With relative() the filter also offers periods relative to today
 * (<Name>_Rel), which stay relative in saved views:
 *
 *   -12w..1w      12 weeks ago to in 1 week (units d, w, m, y; empty side: open)
 *   -4w..0d       last 4 weeks;   0d..13w   next 13 weeks
 *   schoolyear    this school year (start month: config school_year_start_month)
 *   schoolyear-1  last school year; year, year-1: calendar years
 *
 * A relative period wins over dates entered.
 */
class DateRangeFilter extends Filter
{
    use Configurable;

    /**
     * First month of the school year (1–12).
     */
    private static int $school_year_start_month = 8;

    /**
     * @var array<string, string>|null value => label; null: no relative periods
     */
    private ?array $relativeOptions = null;

    public static function create(string $name, string $label): static
    {
        return new static($name, $label);
    }

    /**
     * Offers relative periods: the given ones (value => label, label ''
     * = described automatically) or a default set.
     *
     * @param array<int|string, string>|null $options values, or value => label
     */
    public function relative(?array $options = null): static
    {
        $options ??= ['-4w..0d', '-12w..0d', '0d..4w', '0d..13w', 'schoolyear', 'schoolyear-1', 'year', 'year-1'];
        $this->relativeOptions = [];
        foreach ($options as $key => $label) {
            $value = is_int($key) ? (string) $label : (string) $key;
            if (self::resolve($value) === null) {
                throw new \InvalidArgumentException("Invalid relative period '{$value}'");
            }
            $this->relativeOptions[$value] = is_int($key) || $label === '' ? self::describeRelative($value) : $label;
        }
        return $this;
    }

    public function hasRelative(): bool
    {
        return $this->relativeOptions !== null;
    }

    /**
     * @return array<string, string>
     */
    public function getRelativeOptions(): array
    {
        return $this->relativeOptions ?? [];
    }

    /**
     * Request vars of the filter.
     *
     * @return array<int, string>
     */
    public function paramNames(): array
    {
        $names = [$this->name . '_From', $this->name . '_To'];
        if ($this->hasRelative()) {
            $names[] = $this->name . '_Rel';
        }
        return $names;
    }

    public function renderField(): FormField
    {
        $fields = [];
        if ($this->hasRelative()) {
            $fields[] = RelativeRangeField::create(
                $this->name . '_Rel',
                _t(self::class . '.PERIOD', 'Period'),
                $this->relativeOptions
            )->setEmptyString(_t(self::class . '.FIXED_DATES', 'Dates'));
        }
        $fields[] = DateField::create($this->name . '_From', _t(self::class . '.FROM', 'From'));
        $fields[] = DateField::create($this->name . '_To', _t(self::class . '.TO', 'To'));
        return FieldGroup::create($this->label, ...$fields)->setName($this->name);
    }

    public function applyToList(DataList $list, mixed $value): DataList
    {
        // Handled by FilterCollection with the _From/_To/_Rel request vars.
        return $list;
    }

    /**
     * The range in the request: ['from' => ?Y-m-d, 'to' => ?Y-m-d,
     * 'relative' => ?string], null without one.
     *
     * @param callable(string): mixed $get request var by name
     * @return array{from: ?string, to: ?string, relative: ?string}|null
     */
    public function rangeFrom(callable $get): ?array
    {
        $relative = $this->hasRelative() ? trim((string) $get($this->name . '_Rel')) : '';
        if ($relative !== '') {
            $range = self::resolve($relative);
            if ($range !== null) {
                return ['from' => $range[0], 'to' => $range[1], 'relative' => $relative];
            }
        }
        $from = (string) $get($this->name . '_From');
        $to = (string) $get($this->name . '_To');
        if ($from === '' && $to === '') {
            return null;
        }
        return ['from' => $from !== '' ? $from : null, 'to' => $to !== '' ? $to : null, 'relative' => null];
    }

    /**
     * "01.03.2026 – 31.03.2026", "from 01.03.2026", "to 31.03.2026", or the
     * relative period ("last 12 weeks").
     *
     * @param array{from?: ?string, to?: ?string, relative?: ?string}|mixed $value
     */
    public function describe(mixed $value): string
    {
        if (is_array($value) && !empty($value['relative'])) {
            return $this->relativeOptions[$value['relative']] ?? self::describeRelative((string) $value['relative']);
        }
        $format = fn (?string $date) => $date ? date('d.m.Y', (int) strtotime($date)) : '';
        $from = $format(is_array($value) ? ($value['from'] ?? null) : null);
        $to = $format(is_array($value) ? ($value['to'] ?? null) : null);
        if ($from !== '' && $to !== '') {
            return $from . ' – ' . $to;
        }
        return $from !== ''
            ? _t(self::class . '.FROM_DATE', 'from {date}', ['date' => $from])
            : _t(self::class . '.TO_DATE', 'to {date}', ['date' => $to]);
    }

    public function applyRange(DataList $list, ?string $from, ?string $to): DataList
    {
        if ($this->applyFn) {
            return ($this->applyFn)($list, ['from' => $from, 'to' => $to]);
        }

        return $list;
    }

    // ─── Relative periods ─────────────────────────────────────────────────────

    /**
     * Dates of a relative period as of today: [from, to] (Y-m-d or null for
     * an open side); null when the value is not valid.
     *
     * @return array{0: ?string, 1: ?string}|null
     */
    public static function resolve(string $value, ?int $today = null): ?array
    {
        $today ??= (int) strtotime(date('Y-m-d', \SilverStripe\ORM\FieldType\DBDatetime::now()->getTimestamp()));
        $value = trim($value);
        if (preg_match('/^(schoolyear|year)([+-]\d{1,2})?$/', $value, $m)) {
            $shift = (int) ($m[2] ?? 0);
            $year = (int) date('Y', $today);
            if ($m[1] === 'schoolyear') {
                $start = max(1, min(12, (int) static::config()->get('school_year_start_month')));
                if ((int) date('n', $today) < $start) {
                    $year--;
                }
                $from = mktime(0, 0, 0, $start, 1, $year + $shift);
                $to = (int) strtotime('-1 day', (int) mktime(0, 0, 0, $start, 1, $year + $shift + 1));
            } else {
                $from = mktime(0, 0, 0, 1, 1, $year + $shift);
                $to = mktime(0, 0, 0, 12, 31, $year + $shift);
            }
            return [date('Y-m-d', (int) $from), date('Y-m-d', $to)];
        }
        if (!preg_match('/^([+-]?\d{1,3}[dwmy])?\.\.([+-]?\d{1,3}[dwmy])?$/', $value, $m)) {
            return null;
        }
        $side = function (string $offset) use ($today): ?string {
            if ($offset === '') {
                return null;
            }
            $units = ['d' => 'days', 'w' => 'weeks', 'm' => 'months', 'y' => 'years'];
            $number = (int) substr($offset, 0, -1);
            return date('Y-m-d', (int) strtotime(sprintf('%+d %s', $number, $units[substr($offset, -1)]), $today));
        };
        $from = $side($m[1] ?? '');
        $to = $side($m[2] ?? '');
        return $from === null && $to === null ? null : [$from, $to];
    }

    /**
     * "last 12 weeks", "next 4 weeks", "this school year",
     * "today − 12 weeks to today + 1 week".
     */
    public static function describeRelative(string $value): string
    {
        if (preg_match('/^(schoolyear|year)([+-]\d{1,2})?$/', $value, $m)) {
            $shift = (int) ($m[2] ?? 0);
            $kind = strtoupper($m[1]);
            return match (true) {
                $shift === 0 => _t(self::class . '.THIS_' . $kind, $m[1] === 'year' ? 'this year' : 'this school year'),
                $shift === -1 => _t(self::class . '.LAST_' . $kind, $m[1] === 'year' ? 'last year' : 'last school year'),
                $shift === 1 => _t(self::class . '.NEXT_' . $kind, $m[1] === 'year' ? 'next year' : 'next school year'),
                default => _t(
                    self::class . '.SHIFTED_' . $kind,
                    $m[1] === 'year' ? 'year {shift}' : 'school year {shift}',
                    ['shift' => sprintf('%+d', $shift)]
                ),
            };
        }
        if (!preg_match('/^([+-]?\d{1,3}[dwmy])?\.\.([+-]?\d{1,3}[dwmy])?$/', $value, $m)) {
            return $value;
        }
        $from = $m[1] ?? '';
        $to = $m[2] ?? '';
        $number = fn (string $offset) => (int) substr($offset, 0, -1);
        if ($from !== '' && $number($from) < 0 && $to !== '' && $number($to) === 0) {
            return _t(self::class . '.LAST_N', 'last {amount}', ['amount' => self::amount(-$number($from), substr($from, -1))]);
        }
        if ($from !== '' && $number($from) === 0 && $to !== '' && $number($to) > 0) {
            return _t(self::class . '.NEXT_N', 'next {amount}', ['amount' => self::amount($number($to), substr($to, -1))]);
        }
        $side = function (string $offset): string {
            $n = $number = (int) substr($offset, 0, -1);
            if ($n === 0) {
                return _t(self::class . '.TODAY', 'today');
            }
            return _t(
                self::class . ($number < 0 ? '.TODAY_MINUS' : '.TODAY_PLUS'),
                $number < 0 ? 'today − {amount}' : 'today + {amount}',
                ['amount' => self::amount(abs($number), substr($offset, -1))]
            );
        };
        if ($from === '') {
            return _t(self::class . '.UNTIL', 'until {date}', ['date' => $side($to)]);
        }
        if ($to === '') {
            return _t(self::class . '.SINCE', 'since {date}', ['date' => $side($from)]);
        }
        return _t(self::class . '.RANGE', '{from} to {to}', ['from' => $side($from), 'to' => $side($to)]);
    }

    private static function amount(int $number, string $unit): string
    {
        $names = [
            'd' => [_t(self::class . '.DAY', 'day'), _t(self::class . '.DAYS', 'days')],
            'w' => [_t(self::class . '.WEEK', 'week'), _t(self::class . '.WEEKS', 'weeks')],
            'm' => [_t(self::class . '.MONTH', 'month'), _t(self::class . '.MONTHS', 'months')],
            'y' => [_t(self::class . '.YEAR', 'year'), _t(self::class . '.YEARS', 'years')],
        ];
        return $number . ' ' . $names[$unit][$number === 1 ? 0 : 1];
    }
}
