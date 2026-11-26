<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\SelectFilter;
use Atwx\SilverstripeFrontdeskKit\Filter\TextFilter;
use Atwx\SilverstripeFrontdeskKit\Search\GlobalSearch;
use Atwx\SilverstripeFrontdeskKit\Search\SearchController;
use Atwx\SilverstripeFrontdeskKit\Search\SearchDefinition;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class SearchTestTown extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_SearchTestTown';
    private static array $db = ['Name' => 'Varchar'];
}

class SearchTestPerson extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_SearchTestPerson';
    private static array $db = ['FirstName' => 'Varchar', 'Surname' => 'Varchar', 'Street' => 'Varchar',
        'Number' => 'Int', 'Status' => 'Varchar'];
    private static array $has_one = ['Town' => SearchTestTown::class];
    private static string $default_sort = 'Surname ASC';

    public function getTitle(): string
    {
        return trim($this->FirstName . ' ' . $this->Surname);
    }
}

class SearchTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = SearchTestPerson::class;
    private static string $url_segment = 'search-people';
    private static string $title = 'People';
    private static bool $filter_panel = true;
    private static array $suggested_filters = ['Status'];

    public static bool $allowed = true;

    public function canView($member = null): bool
    {
        return self::$allowed;
    }

    protected function defineSearch(): ?SearchDefinition
    {
        return SearchDefinition::create()
            ->field('Surname', 10)
            ->field('FirstName', 8)
            ->field('Street', 1)
            ->field('Town.Name', 2)
            ->scope('name', 'Name', ['FirstName', 'Surname'])
            ->scope('town', 'Town', ['Town.Name'])
            ->rule(fn (string $word) => ctype_digit($word) ? ['Number' => 10] : null);
    }

    protected function defineSearchHit(DataObject $record): array
    {
        return array_merge(parent::defineSearchHit($record), ['Text' => 'Street ' . $record->Street]);
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()
            ->add(SelectFilter::create('Status', 'Status')->options(['active' => 'Active', 'old' => 'Former'])
                ->apply(fn (DataList $list, $value) => $list->filter('Status', $value)))
            ->add(TextFilter::create('Street', 'Street')
                ->apply(fn (DataList $list, $value) => $list->filter('Street:PartialMatch', $value)))
            ->add(\Atwx\SilverstripeFrontdeskKit\Filter\MultiSelectFilter::create('Towns', 'Towns')
                ->options(['1' => 'One', '2' => 'Two'])->field('TownID')->choosableMatch());
    }

    protected function defineListViews(): array
    {
        return [ListView::create('active', 'Active')->filters(['Status' => 'active'])];
    }

    /**
     * @return array<int, string>
     */
    public function titles(): array
    {
        return array_map(fn ($person) => $person->getTitle(), $this->getQuery()->toArray());
    }
}

/**
 * The same list with the classic filter bar and a rule that changes the word.
 */
class SearchTestClassicController extends SearchTestController implements TestOnly
{
    private static string $url_segment = 'search-people-classic';
    private static bool $filter_panel = false;

    protected function defineSearch(): ?SearchDefinition
    {
        return parent::defineSearch()->rule(
            fn (string $word) => preg_match('/^\d+-\d+$/', $word)
                ? [
                    // the number without dashes, and as typed in the street (e.g. a phone number)
                    ['word' => str_replace('-', '', $word), 'fields' => ['Number:ExactMatch' => 10]],
                    ['word' => $word, 'fields' => ['Street' => 3]],
                ]
                : null
        );
    }
}

/**
 * A second list of the same model (e.g. another layout): not twice in the global search.
 */
class SearchTestSubclassController extends SearchTestController implements TestOnly
{
    private static string $url_segment = 'search-people-2';
}

class SearchTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [SearchTestTown::class, SearchTestPerson::class];

    protected function setUp(): void
    {
        parent::setUp();
        SearchTestController::$allowed = true;
        $hamburg = SearchTestTown::create(['Name' => 'Hamburg']);
        $hamburg->write();
        $berlin = SearchTestTown::create(['Name' => 'Berlin']);
        $berlin->write();
        foreach ([
            ['Anna', 'Bergmann', 'Hauptstraße', 11, 'active', $berlin],
            ['Berg', 'Schulz', 'Ringweg', 12, 'old', $hamburg],
            ['Carl', 'Berg', 'Am Markt', 13, 'active', $hamburg],
            ['Dora', 'Klein', 'Bergstraße', 14, 'active', $berlin],
            ['Emil', 'Weiss', 'Lange Berg-Allee', 15, 'old', $berlin],
            ['Fritz', 'Hamburger', 'Am Hafen', 2026, 'active', $hamburg],
        ] as [$first, $last, $street, $number, $status, $town]) {
            SearchTestPerson::create([
                'FirstName' => $first, 'Surname' => $last, 'Street' => $street, 'Number' => $number,
                'Status' => $status, 'TownID' => $town->ID,
            ])->write();
        }
    }

    private function controller(array $get = []): SearchTestController
    {
        $controller = SearchTestController::create();
        $request = new HTTPRequest('GET', 'search-people', $get);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        return $controller;
    }

    public function testRankExactBeforeWordStartBeforeContainedTimesWeight(): void
    {
        $this->assertSame(
            ['Carl Berg', 'Berg Schulz', 'Anna Bergmann', 'Dora Klein', 'Emil Weiss'],
            $this->controller(['Query' => 'berg'])->titles(),
            'surname exact (30) > first name exact (24) > surname start (20) > street word start (2 each, then by surname)'
        );
    }

    public function testAllWordsMustMatch(): void
    {
        $this->assertSame(['Anna Bergmann'], $this->controller(['Query' => 'berg anna'])->titles());
        $this->assertSame([], $this->controller(['Query' => 'berg nobody'])->titles());
    }

    public function testScopeAndRelationField(): void
    {
        $this->assertSame(['Carl Berg', 'Berg Schulz', 'Anna Bergmann'], $this->controller(['Query' => 'berg', 'Query_Scope' => 'name'])->titles());
        $this->assertSame(['Fritz Hamburger', 'Carl Berg', 'Berg Schulz'], $this->controller(['Query' => 'hamburg'])->titles(),
            'surname starts with it (20) before the town (exact, 6); equal scores by default sort');
        $this->assertSame(['Carl Berg', 'Fritz Hamburger', 'Berg Schulz'], $this->controller(['Query' => 'hamburg', 'Query_Scope' => 'town'])->titles());
    }

    public function testRuleForDigits(): void
    {
        $this->assertSame(['Carl Berg'], $this->controller(['Query' => '13'])->titles(), 'numbers search the number only');
        $this->assertSame(['Carl Berg'], $this->controller(['Query' => 'berg 13'])->titles());
    }

    public function testColumnSortWinsOverScore(): void
    {
        $titles = $this->controller(['Query' => 'berg', 'sort' => 'NotSortable'])->titles();
        $this->assertCount(5, $titles);
    }

    public function testSearchWithFiltersAndPanelData(): void
    {
        $controller = $this->controller(['Query' => 'berg', 'Status' => 'active']);
        $this->assertSame(['Carl Berg', 'Anna Bergmann', 'Dora Klein'], $controller->titles());
        $this->assertSame(1, $controller->ActiveFilterCount());
        $chip = $controller->ActiveFilterChips()->first();
        $this->assertSame('Status', $chip->Label);
        $this->assertSame('Active', $chip->Value);
        $this->assertStringContainsString('Query=berg', $chip->RemoveLink);
        $this->assertStringNotContainsString('Status=', $chip->RemoveLink);
        $this->assertStringContainsString('filtered=1', $chip->RemoveLink);

        $panel = $controller->PanelFilters();
        $this->assertSame(['Status', 'Street', 'Towns'], $panel->column('Name'), 'also a multiple choice with its match field');
        $this->assertSame([true, false, false], $panel->column('Shown'), 'active or suggested; the others are added by hand');
        $this->assertSame('berg', $controller->SearchBox()->Value);
        $this->assertSame(['', 'name', 'town'], $controller->SearchBox()->Scopes->column('Key'));
    }

    public function testViewPresetIsShownAndCanBeRemoved(): void
    {
        $controller = $this->controller(['view' => 'active']);
        $this->assertSame(1, $controller->ActiveFilterCount(), 'preset of the view');
        $this->assertCount(4, $controller->titles());
        $removed = $this->controller(['view' => 'active', 'filtered' => '1']);
        $this->assertSame(0, $removed->ActiveFilterCount(), 'removed: the preset is not applied again');
        $this->assertCount(6, $removed->titles());
    }

    public function testToolbarRendersSearchViewsAndPanel(): void
    {
        $controller = $this->controller(['Query' => 'berg', 'Status' => 'active']);
        $html = (string) $controller->renderWith('Atwx\\SilverstripeFrontdeskKit\\Controller\\Layout\\FrontdeskController');
        $this->assertStringContainsString('type="search" name="Query" value="berg"', $html);
        $this->assertStringContainsString('name="Query_Scope"', $html);
        $this->assertStringContainsString('data-fdk-navigate', $html, 'views as a select field');
        $this->assertStringContainsString('data-fdk-filter-name="Street" hidden', $html);
        $this->assertStringContainsString('<option value="Street">Street</option>', $html, 'add filter');
        $this->assertStringContainsString('fdk-filter-panel basis-full order-last lg-open', $html);
        $this->assertStringContainsString('data-fdk-active-filters="1"', $html);
        $this->assertStringNotContainsString('class="fdk-filter-bar"', $html, 'no bar with all filters');
    }

    public function testGlobalSearchGroupsHitsAndJson(): void
    {
        $this->logInWithPermission('ADMIN');
        $controllers = GlobalSearch::singleton()->controllers();
        $classes = array_map(fn ($c) => $c::class, $controllers);
        $this->assertContains(SearchTestController::class, $classes);
        $this->assertNotContains(SearchTestSubclassController::class, $classes, 'same model: the general list only');

        $groups = array_values(array_filter(
            GlobalSearch::singleton()->search('berg', 2),
            fn ($group) => $group['key'] === 'search-people'
        ));
        $this->assertCount(1, $groups);
        $this->assertSame(5, $groups[0]['count']);
        $this->assertSame(['Carl Berg', 'Berg Schulz'], array_column($groups[0]['hits'], 'Title'));
        $this->assertSame('Street Am Markt', $groups[0]['hits'][0]['Text']);
        $this->assertStringContainsString('search-people?Query=berg', $groups[0]['link']);

        $request = new HTTPRequest('GET', 'fdk-search', ['q' => 'berg', 'format' => 'json']);
        $request->setSession(new Session([]));
        $search = SearchController::create();
        $search->setRequest($request);
        $json = json_decode((string) $search->index($request)->getBody(), true);
        $people = array_values(array_filter($json['groups'], fn ($group) => $group['key'] === 'search-people'));
        $this->assertSame('Carl Berg', $people[0]['hits'][0]['title']);
        $this->assertSame('search-people/view/' . SearchTestPerson::get()->find('Surname', 'Berg')->ID, $people[0]['hits'][0]['link']);

        Config::modify()->set(GlobalSearch::class, 'limit', 2);
        $request = new HTTPRequest('GET', 'fdk-search', ['q' => 'berg']);
        $request->setSession(new Session([]));
        $search->setRequest($request);
        $html = (string) $search->index($request)->getBody();
        $this->assertStringContainsString('data-fdk-search-group="search-people"', $html);
        $this->assertStringContainsString('More results (3)', $html);

        SearchTestController::$allowed = false;
        $this->assertNotContains(SearchTestController::class, array_map(fn ($c) => $c::class, GlobalSearch::singleton()->controllers()));
    }

    public function testGlobalSearchNeedsLogin(): void
    {
        $this->logOut();
        $this->assertSame([], GlobalSearch::singleton()->search('berg'));
        Config::modify()->set(FrontdeskController::class, 'search_overlay', true);
        $this->assertFalse(\Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskTemplateProvider::FrontdeskSearchOverlay());
    }

    public function testClassicBarGetsTheSearchField(): void
    {
        $controller = SearchTestClassicController::create();
        $request = new HTTPRequest('GET', 'search-people-classic', ['Query' => 'berg', 'Query_Scope' => 'name']);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $fields = $controller->FilterForm()->Fields();
        $this->assertSame('berg', $fields->dataFieldByName('Query')?->getValue(), 'kept without a filter value');
        $this->assertSame('name', $fields->dataFieldByName('Query_Scope')?->getValue());
        $this->assertSame(['Query', 'Query_Scope', 'Status'], array_slice(array_map(fn ($f) => $f->getName(), $fields->toArray()), 0, 3));
        $this->assertSame(['Carl Berg', 'Berg Schulz', 'Anna Bergmann'], $controller->titles());

        $this->assertNull($this->controller()->FilterForm()->Fields()->dataFieldByName('Query'), 'the toolbar has its own field');
    }

    public function testRuleMayChangeTheWord(): void
    {
        $controller = SearchTestClassicController::create();
        $request = new HTTPRequest('GET', 'search-people-classic', ['Query' => '20-26']);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $this->assertSame(['Fritz Hamburger'], $controller->titles(), '"20-26" finds the number 2026');
    }

    public function testRuleAlternativesAreOred(): void
    {
        SearchTestPerson::create(['FirstName' => 'Gina', 'Surname' => 'Tel', 'Street' => 'Phone 0044-20-26 99'])->write();
        $controller = SearchTestClassicController::create();
        $request = new HTTPRequest('GET', 'search-people-classic', ['Query' => '20-26']);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $this->assertSame(['Fritz Hamburger', 'Gina Tel'], $controller->titles(), 'number exact (30) before the street (3)');
    }

    public function testWordsAreLimited(): void
    {
        $this->assertSame(['a', 'b'], SearchDefinition::words("  a \n b "));
        $this->assertCount(10, SearchDefinition::words(str_repeat('x ', 20)));
    }
}
