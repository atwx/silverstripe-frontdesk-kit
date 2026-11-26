<?php

namespace Atwx\SilverstripeFrontdeskKit\Search;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * Weighted free-text search of a list, declared by its controller
 * (FrontdeskController::defineSearch()):
 *
 *   SearchDefinition::create()
 *       ->field('Surname', 10)                    // field of the model, weight
 *       ->field('Family.City', 2)                 // over relations (has_one, has_many, many_many)
 *       ->scope('name', 'Name', ['FirstName', 'Surname'])    // search area to choose
 *       ->rule(fn (string $word) => ctype_digit($word)       // words of a special form
 *           ? ['Family.Number' => 10, 'InDate:StartsWith' => 5]
 *           : null);
 *
 * Every word must match one of the fields (AND over words, OR over fields).
 * Rank: per word the best field counts, exact match 3, start of a word 2,
 * contained 1, times the field's weight; the scores of the words are added.
 * The matching IDs and their tiers are found with database queries (ORM
 * filters, so relations work); the list is ordered by score in SQL.
 */
class SearchDefinition
{
    use Injectable;

    public const int EXACT = 3;
    public const int WORD_START = 2;
    public const int CONTAINS = 1;

    /**
     * @var array<string, int> field path => weight
     */
    private array $fields = [];

    /**
     * @var array<string, array{title: string, fields: array<int, string>}>
     */
    private array $scopes = [];

    /**
     * @var array<int, array{callback: callable, scopes: array<int, string>}>
     */
    private array $rules = [];

    /**
     * A field (path from the model, e.g. "Family.City") with its weight.
     */
    public function field(string $path, int $weight = 1): static
    {
        $this->fields[$path] = max(1, $weight);
        return $this;
    }

    /**
     * A search area the member can choose ("Name", "City"): its fields must
     * have been added with field() (their weights apply).
     *
     * @param array<int, string> $fields
     */
    public function scope(string $key, string $title, array $fields): static
    {
        $this->scopes[$key] = ['title' => $title, 'fields' => array_values($fields)];
        return $this;
    }

    /**
     * A rule for words of a special form: the callable gets the word and
     * returns null (no rule) or the fields to search instead, with weights;
     * or ['word' => …, 'fields' => […]] to search for a changed word, e.g.
     * "12-345" as 12345. Fields with the weight null are left out.
     * A key may carry an ORM modifier ("InDate:StartsWith"); such a field
     * only matches with that modifier (counted as exact). By default the rule
     * applies when searching all fields; $scopes lists further search areas.
     *
     * @param callable(string): (array<string, int>|null) $callback
     * @param array<int, string> $scopes
     */
    public function rule(callable $callback, array $scopes = []): static
    {
        $this->rules[] = ['callback' => $callback, 'scopes' => $scopes];
        return $this;
    }

    /**
     * @return array<string, string> scope key => title
     */
    public function getScopes(): array
    {
        return array_map(fn (array $scope) => $scope['title'], $this->scopes);
    }

    /**
     * @return array<string, int>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Words of a search text (whitespace separated, at most 10).
     *
     * @return array<int, string>
     */
    public static function words(string $text): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        return array_slice(array_values(array_filter($words, fn ($word) => $word !== '')), 0, 10);
    }

    /**
     * Scores of the records of $list that match the text in the scope
     * ('' = all fields), best first: [ID => score].
     *
     * @param DataList<DataObject> $list
     * @return array<int, int>
     */
    public function scores(DataList $list, string $text, string $scope = ''): array
    {
        $words = self::words($text);
        if (!$words) {
            return [];
        }
        $candidates = null;
        $scores = [];
        foreach ($words as $word) {
            [$fields, $word] = $this->fieldsFor($word, $scope);
            if (!$fields || $word === '') {
                return [];
            }
            $matching = $this->matchingIDs($list, $fields, $word, self::CONTAINS, $candidates);
            $candidates = $candidates === null ? $matching : array_values(array_intersect($candidates, $matching));
            if (!$candidates) {
                return [];
            }
            $best = array_fill_keys($candidates, 0);
            foreach ($fields as $path => $weight) {
                foreach ([self::EXACT, self::WORD_START, self::CONTAINS] as $tier) {
                    if (str_contains($path, ':') && $tier !== self::EXACT) {
                        continue;
                    }
                    foreach ($this->matchingIDs($list, [$path => $weight], $word, $tier, $candidates) as $id) {
                        $best[$id] = max($best[$id], $tier * $weight);
                    }
                }
            }
            foreach ($best as $id => $score) {
                $scores[$id] = ($scores[$id] ?? 0) + $score;
            }
        }
        $scores = array_intersect_key($scores, array_flip($candidates ?? []));
        arsort($scores);
        return $scores;
    }

    /**
     * The list limited to the matching records, ordered by score (best
     * first) unless $order is false.
     *
     * @template T of DataObject
     * @param DataList<T> $list
     * @return DataList<T>
     */
    public function apply(DataList $list, string $text, string $scope = '', bool $order = true): DataList
    {
        if (!self::words($text)) {
            return $list;
        }
        $scores = $this->scores($list, $text, $scope);
        $list = $list->filter('ID', array_keys($scores) ?: [0]);
        return $order ? self::orderByScore($list, $scores) : $list;
    }

    /**
     * Orders a list by the given scores (best first), then by the model's
     * default sort.
     *
     * @template T of DataObject
     * @param DataList<T> $list
     * @param array<int, int> $scores
     * @return DataList<T>
     */
    public static function orderByScore(DataList $list, array $scores): DataList
    {
        if (!$scores) {
            return $list;
        }
        $table = DataObject::getSchema()->baseDataTable($list->dataClass());
        $cases = '';
        foreach ($scores as $id => $score) {
            $cases .= ' WHEN ' . (int) $id . ' THEN ' . (int) $score;
        }
        $rest = (string) DataObject::singleton($list->dataClass())->config()->get('default_sort');
        $rest = $rest !== '' ? $rest : '"' . $table . '"."ID" ASC';
        return $list->orderBy("CASE \"{$table}\".\"ID\"{$cases} ELSE 0 END DESC, {$rest}");
    }

    /**
     * Fields to search a word in (a rule's fields, else the scope's or all)
     * and the word to search for (a rule may change it).
     *
     * @return array{0: array<string, int>, 1: string}
     */
    private function fieldsFor(string $word, string $scope): array
    {
        foreach ($this->rules as $rule) {
            if ($scope !== '' && !in_array($scope, $rule['scopes'], true)) {
                continue;
            }
            $result = ($rule['callback'])($word);
            if (!is_array($result) || !$result) {
                continue;
            }
            // ['word' => …, 'fields' => […]]: the rule also changes the word
            $fields = isset($result['fields']) && is_array($result['fields']) ? $result['fields'] : $result;
            $search = isset($result['fields']) && is_array($result['fields'])
                ? trim((string) ($result['word'] ?? $word))
                : $word;
            $fields = array_filter($fields, fn ($weight) => $weight !== null);
            if ($fields) {
                return [array_map(fn ($weight) => max(1, (int) $weight), $fields), $search];
            }
        }
        if ($scope !== '' && isset($this->scopes[$scope])) {
            return [array_intersect_key($this->fields, array_flip($this->scopes[$scope]['fields'])), $word];
        }
        return [$this->fields, $word];
    }

    /**
     * IDs of the records of $list whose fields match the word in the tier.
     *
     * @param DataList<DataObject> $list
     * @param array<string, int> $fields
     * @param array<int, int>|null $within
     * @return array<int, int>
     */
    private function matchingIDs(DataList $list, array $fields, string $word, int $tier, ?array $within): array
    {
        $any = [];
        foreach (array_keys($fields) as $path) {
            if (str_contains($path, ':')) {
                $any[$path] = $word;
                continue;
            }
            switch ($tier) {
                case self::EXACT:
                    $any[$path . ':ExactMatch:nocase'] = $word;
                    break;
                case self::WORD_START:
                    $any[$path . ':StartsWith:nocase'] = $word;
                    $any[$path . ':PartialMatch:nocase'] = [' ' . $word, '-' . $word, '(' . $word];
                    break;
                default:
                    $any[$path . ':PartialMatch:nocase'] = $word;
            }
        }
        if ($within !== null) {
            if (!$within) {
                return [];
            }
            $list = $list->filter('ID', $within);
        }
        return array_map('intval', array_unique($list->filterAny($any)->column('ID')));
    }
}
