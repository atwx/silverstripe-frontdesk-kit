<?php

namespace Atwx\SilverstripeFrontdeskKit\Search;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Security;

/**
 * Endpoint of the global search: fdk-search?q=<text>.
 *
 * - HTML (default, for the overlay): Includes/SearchResults with one group
 *   template per type and one hit template per hit (config
 *   search_group_template / search_hit_template on the list's controller).
 * - JSON (?format=json or Accept: application/json): {query, groups: [{key,
 *   type, count, link, hits: [{title, text, link, image, icon}]}]} for an
 *   own front end.
 *
 * Only for logged-in members; the lists take part as GlobalSearch decides.
 */
class SearchController extends Controller
{
    private static array $allowed_actions = ['index'];

    /**
     * Template of the whole result (groups), replaceable per project.
     */
    private static string $results_template = 'Atwx\\SilverstripeFrontdeskKit\\Includes\\SearchResults';

    private static string $group_template = 'Atwx\\SilverstripeFrontdeskKit\\Includes\\SearchGroup';

    private static string $hit_template = 'Atwx\\SilverstripeFrontdeskKit\\Includes\\SearchHit';

    public function index(HTTPRequest $request): HTTPResponse
    {
        if (!Security::getCurrentUser()) {
            return $this->httpError(403);
        }
        $query = trim((string) $request->getVar('q'));
        $groups = GlobalSearch::singleton()->search($query, null, $request);
        $response = HTTPResponse::create()
            ->addHeader('Cache-Control', 'no-store')
            ->addHeader('X-Robots-Tag', 'noindex');
        if ($request->getVar('format') === 'json' || str_contains((string) $request->getHeader('Accept'), 'application/json')) {
            return $response
                ->addHeader('Content-Type', 'application/json')
                ->setBody((string) json_encode(['query' => $query, 'groups' => $this->jsonGroups($groups)]));
        }
        return $response
            ->addHeader('Content-Type', 'text/html; charset=utf-8')
            ->setBody((string) $this->renderResults($query, $groups));
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     */
    public function renderResults(string $query, array $groups): DBHTMLText
    {
        $rendered = ArrayList::create();
        foreach ($groups as $group) {
            $hits = ArrayList::create();
            foreach ($group['hits'] as $hit) {
                $data = ArrayData::create($hit);
                $hits->push(ArrayData::create([
                    'Hit' => $data,
                    'Html' => $data->renderWith(array_filter([
                        $group['hit_template'] ?: null,
                        (string) static::config()->get('hit_template'),
                    ])),
                ]));
            }
            $data = ArrayData::create([
                'Key' => $group['key'],
                'Type' => $group['type'],
                'Count' => $group['count'],
                'MoreLink' => $group['link'],
                'More' => max(0, $group['count'] - count($group['hits'])),
                'Hits' => $hits,
            ]);
            $rendered->push(ArrayData::create([
                'Group' => $data,
                'Html' => $data->renderWith(array_filter([
                    $group['group_template'] ?: null,
                    (string) static::config()->get('group_template'),
                ])),
            ]));
        }
        return ArrayData::create([
            'Query' => $query,
            'HasQuery' => SearchDefinition::words($query) !== [],
            'Groups' => $rendered,
        ])->renderWith((string) static::config()->get('results_template'));
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     * @return array<int, array<string, mixed>>
     */
    private function jsonGroups(array $groups): array
    {
        return array_map(fn (array $group) => [
            'key' => $group['key'],
            'type' => $group['type'],
            'count' => $group['count'],
            'link' => $group['link'],
            'hits' => array_map(fn (array $hit) => [
                'title' => $hit['Title'] ?? '',
                'text' => $hit['Text'] ?? '',
                'link' => $hit['Link'] ?? '',
                'image' => $hit['Image'] ?? null,
                'icon' => $hit['Icon'] ?? null,
            ], $group['hits']),
        ], $groups);
    }
}
