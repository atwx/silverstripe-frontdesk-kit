<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Controller\ReportsController;
use Atwx\SilverstripeFrontdeskKit\Filter\DateRangeFilter;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\SelectFilter;
use Atwx\SilverstripeFrontdeskKit\Model\SavedListView;
use Atwx\SilverstripeFrontdeskKit\Search\SearchDefinition;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

class ReportsTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_ReportsTestModel';
    private static array $db = ['Name' => 'Varchar', 'Kind' => 'Varchar', 'Form' => 'Int', 'OutDate' => 'Date'];
}

class ReportsTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = ReportsTestModel::class;
    private static string $url_segment = 'reports-people';
    private static string $title = 'People';

    public static bool $visible = true;

    public bool $editor = false;

    public function canView($member = null): bool
    {
        return self::$visible;
    }

    public function canEdit($member = null): bool
    {
        return $this->editor;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->getQuery()->column('Name');
    }

    protected function defineColumns(): ColumnCollection
    {
        return ColumnCollection::create()
            ->make('Name', 'Name')->sortable()->end()
            ->make('Kind', 'Kind')->end()
            ->make('Form', 'Form')->sortable()->end();
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()
            ->add(SelectFilter::create('Kind', 'Kind')
                ->options(['a' => 'A', 'b' => 'B'])
                ->apply(fn (DataList $list, $value) => $list->filter('Kind', $value)))
            ->add(DateRangeFilter::create('OutDate', 'Out')->relative()
                ->apply(function (DataList $list, array $range) {
                    if ($range['from']) {
                        $list = $list->filter('OutDate:GreaterThanOrEqual', $range['from']);
                    }
                    return $range['to'] ? $list->filter('OutDate:LessThanOrEqual', $range['to']) : $list;
                }));
    }

    protected function defineSearch(): ?SearchDefinition
    {
        return SearchDefinition::create()->field('Name');
    }

    protected function defineListViews(): array
    {
        return [
            ListView::create('kind-a', 'Kind A')->filters(['Kind' => 'a']),
            ListView::create('returners', 'Returners')
                ->report('Placement')
                ->description('Out in the last 12 weeks')
                ->filters(['OutDate_Rel' => '-12w..1w'])
                ->groupBy('Form'),
            ListView::create('cleanup', 'Clean-up')->report('Data')->query(fn (DataList $list) => $list->filter('Kind', '')),
        ];
    }
}

class ReportsTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [ReportsTestModel::class];

    protected function setUp(): void
    {
        parent::setUp();
        DBDatetime::set_mock_now('2026-11-28 10:00:00');
        ReportsTestController::$visible = true;
        $rows = [
            ['Anna', 'a', 10, '2026-10-01'],
            ['Bert', 'b', 11, '2026-11-30'],
            ['Cleo', 'a', 10, '2025-01-01'],
            ['Dora', '', 11, '2026-09-10'],
            ['Emil', 'a', 10, null],
        ];
        foreach ($rows as [$name, $kind, $form, $out]) {
            ReportsTestModel::create(['Name' => $name, 'Kind' => $kind, 'Form' => $form, 'OutDate' => $out])->write();
        }
        $this->logInWithPermission('SOMETHING');
    }

    protected function tearDown(): void
    {
        DBDatetime::clear_mock_now();
        parent::tearDown();
    }

    private function controller(array $get = [], string $method = 'GET', array $post = []): ReportsTestController
    {
        $controller = ReportsTestController::create();
        $request = new HTTPRequest($method, 'reports-people', $get, $post);
        $request->setSession(new Session([SecurityToken::inst()->getName() => 'tok']));
        $controller->setRequest($request);
        return $controller;
    }

    private function save(ReportsTestController $controller): void
    {
        SecurityToken::enable();
        $controller->pushCurrent();
        try {
            $controller->saveview($controller->getRequest());
        } finally {
            $controller->popCurrent();
            SecurityToken::disable();
        }
    }

    /**
     * @return array<int, string>
     */
    private function sorted(array $names): array
    {
        sort($names);
        return $names;
    }

    public function testRelativePeriods(): void
    {
        $today = (int) strtotime('2026-11-28');
        $this->assertSame(['2026-09-05', '2026-12-05'], DateRangeFilter::resolve('-12w..1w', $today));
        $this->assertSame([null, '2027-02-27'], DateRangeFilter::resolve('..13w', $today));
        $this->assertSame(['2026-08-01', '2027-07-31'], DateRangeFilter::resolve('schoolyear', $today));
        Config::modify()->set(DateRangeFilter::class, 'school_year_start_month', 9);
        $this->assertSame(['2025-09-01', '2026-08-31'], DateRangeFilter::resolve('schoolyear-1', $today));
        $this->assertSame(['2026-09-01', '2027-08-31'], DateRangeFilter::resolve('schoolyear', (int) strtotime('2026-09-01')));
        $this->assertSame(['2025-09-01', '2026-08-31'], DateRangeFilter::resolve('schoolyear', (int) strtotime('2026-08-31')));
        $this->assertSame(['2025-01-01', '2025-12-31'], DateRangeFilter::resolve('year-1', $today));
        $this->assertNull(DateRangeFilter::resolve('soon', $today));
        $this->assertNull(DateRangeFilter::resolve('..', $today));

        $this->assertSame('last 12 weeks', DateRangeFilter::describeRelative('-12w..0d'));
        $this->assertSame('next 1 month', DateRangeFilter::describeRelative('0d..1m'));
        $this->assertSame('today − 12 weeks to today + 1 week', DateRangeFilter::describeRelative('-12w..1w'));
        $this->assertSame('until today + 13 weeks', DateRangeFilter::describeRelative('..13w'));
        $this->assertSame('last school year', DateRangeFilter::describeRelative('schoolyear-1'));
    }

    public function testRelativeFilterInTheRequestAndAsChip(): void
    {
        $controller = $this->controller(['OutDate_Rel' => '-12w..1w']);
        $this->assertSame(['Anna', 'Bert', 'Dora'], $this->sorted($controller->names()));
        $chip = $controller->ActiveFilterChips()->first();
        $this->assertSame('today − 12 weeks to today + 1 week', $chip->Value);
        $this->assertStringNotContainsString('OutDate_Rel', $chip->RemoveLink);

        $controller = $this->controller(['OutDate_Rel' => 'year-1']);
        $this->assertSame(['Cleo'], $controller->names(), 'calendar year 2025');
        $controller = $this->controller(['OutDate_From' => '2026-09-01', 'OutDate_To' => '2026-09-30']);
        $this->assertSame(['Dora'], $controller->names(), 'fixed dates as before');
        $controller = $this->controller(['OutDate_Rel' => 'bogus', 'OutDate_From' => '2026-09-01']);
        $this->assertSame(['Anna', 'Bert', 'Dora'], $this->sorted($controller->names()), 'invalid period: dates');
    }

    public function testRelativeFieldOffersPeriodsAndTheValueOfAView(): void
    {
        $field = $this->controller(['view' => 'returners'])->FilterForm()->Fields()->dataFieldByName('OutDate_Rel');
        $this->assertNotNull($field);
        $this->assertSame('-12w..1w', $field->getValue());
        $this->assertSame('today − 12 weeks to today + 1 week', $field->getSource()['-12w..1w']);
        $this->assertArrayHasKey('schoolyear', $field->getSource());
    }

    public function testReportsAreNotInTheBarButInTheMenu(): void
    {
        $controller = $this->controller();
        $this->assertSame(['All', 'Kind A'], $controller->ListViews()->column('Title'));
        $groups = $controller->ListReports();
        $this->assertSame(['Placement', 'Data'], $groups->column('Title'));
        $this->assertSame('Out in the last 12 weeks', $groups->first()->Reports->first()->Description);

        $controller = $this->controller(['view' => 'returners']);
        $this->assertSame(['All', 'Kind A', 'Returners'], $controller->ListViews()->column('Title'), 'current report shown');
        $this->assertTrue((bool) $controller->ListViews()->last()->Active);
        $this->assertSame('Out in the last 12 weeks', $controller->CurrentListViewDescription());
        $this->assertSame(['Anna', 'Bert', 'Dora'], $this->sorted($controller->names()));

        $html = (string) $controller->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\ListViews');
        $this->assertStringContainsString('fdk-reports-menu', $html);
        $this->assertStringContainsString('Out in the last 12 weeks', $html);
    }

    public function testGroupingByAViewWithCounts(): void
    {
        $controller = $this->controller(['view' => 'returners', 'OutDate_Rel' => '', 'filtered' => '1']);
        $rows = $controller->ItemRows();
        $this->assertSame(
            ['Anna', 'Cleo', 'Emil', 'Bert', 'Dora'],
            array_map(fn ($row) => $row->Record->Name, $rows->toArray()),
            'sorted by the group column first'
        );
        $this->assertSame(['10', null, null, '11', null], array_map(fn ($row) => $row->GroupTitle, $rows->toArray()));
        $this->assertSame([3, null, null, 2, null], array_map(fn ($row) => $row->GroupCount, $rows->toArray()));
    }

    public function testSaveUpdateAndPinReports(): void
    {
        $controller = $this->controller([], 'POST', [
            'SecurityID' => 'tok',
            'Title' => 'A by form',
            'Description' => 'Kind A, newest form first',
            'Query' => 'Kind=a&Query=an&sort=Form&dir=desc&OutDate_Rel=..1w&view=x',
            'GroupBy' => 'Kind',
        ]);
        $this->save($controller);
        $saved = SavedListView::get()->find('Title', 'A by form');
        $this->assertNotNull($saved);
        $this->assertFalse((bool) $saved->Pinned, 'not pinned by default');
        $this->assertSame('Kind=a&OutDate_Rel=..1w', $saved->FilterQuery, 'relative period stays relative');
        $this->assertSame(['an', '', 'Form', 'desc', 'Kind'], [
            $saved->Search, (string) $saved->SearchScope, $saved->SortColumn, $saved->SortDir, $saved->GroupBy,
        ]);

        $key = 'saved-' . $saved->ID;
        $this->assertSame(['All', 'Kind A'], $this->controller()->ListViews()->column('Title'));
        $reports = $this->controller()->ListReports();
        $this->assertSame('My reports', $reports->last()->Title);

        $controller = $this->controller(['view' => $key]);
        $this->assertSame(['Anna'], $controller->names(), 'filters, relative period and search');
        $this->assertSame('an', $controller->SearchBox()->Value);
        $this->assertSame($saved->ID, $controller->UpdatableListView()?->ID);
        $this->assertSame('desc', $controller->ColumnHeaders()->find('Title', 'Form')->SortDir);
        $controller = $this->controller(['view' => $key, 'Query' => '']);
        $this->assertSame(['Anna', 'Cleo'], $this->sorted($controller->names()), 'search of the request wins');

        // Update: pinned now, other filters
        $controller = $this->controller(['view' => $key], 'POST', [
            'SecurityID' => 'tok',
            'UpdateID' => (string) $saved->ID,
            'Title' => 'B only',
            'Pinned' => '1',
            'Query' => 'Kind=b',
        ]);
        $this->save($controller);
        $this->assertSame(1, SavedListView::get()->count());
        $saved = SavedListView::get()->byID($saved->ID);
        $this->assertSame(['B only', 'Kind=b', ''], [$saved->Title, $saved->FilterQuery, (string) $saved->Search]);
        $this->assertSame(['All', 'Kind A', 'B only'], $this->controller()->ListViews()->column('Title'));
    }

    public function testSavingFromACodeReportKeepsItsValues(): void
    {
        $controller = $this->controller(['view' => 'returners'], 'POST', [
            'SecurityID' => 'tok', 'Title' => 'Copy', 'Query' => 'view=returners', 'GroupBy' => 'Form',
        ]);
        $this->save($controller);
        $saved = SavedListView::get()->find('Title', 'Copy');
        $this->assertSame('OutDate_Rel=-12w..1w', $saved->FilterQuery);
        $this->assertSame('Form', $saved->GroupBy);

        $controller = $this->controller(['view' => 'returners'], 'POST', [
            'SecurityID' => 'tok', 'Title' => 'Narrowed', 'Query' => 'view=returners&Kind=a&filtered=1',
        ]);
        $this->save($controller);
        $this->assertSame('Kind=a', SavedListView::get()->find('Title', 'Narrowed')->FilterQuery, 'chosen filters win');
    }

    public function testOthersCannotUpdateAReport(): void
    {
        $foreign = SavedListView::create([
            'ListKey' => 'reports-people', 'Title' => 'Shared', 'Shared' => true, 'OwnerID' => 999, 'Pinned' => false,
        ]);
        $foreign->write();
        $controller = $this->controller(['view' => 'saved-' . $foreign->ID], 'POST', [
            'SecurityID' => 'tok', 'UpdateID' => (string) $foreign->ID, 'Title' => 'Mine now', 'Query' => '',
        ]);
        $this->assertNull($controller->UpdatableListView(), 'shared, but no editor');
        try {
            $this->save($controller);
            $this->fail('404 expected');
        } catch (HTTPResponse_Exception $e) {
            $this->assertSame(404, $e->getResponse()->getStatusCode());
        }
        $this->assertSame('Shared', SavedListView::get()->byID($foreign->ID)->Title);
        $this->assertSame('Shared reports', $this->controller()->ListReports()->last()->Title);
    }

    public function testReportsPage(): void
    {
        SavedListView::create([
            'ListKey' => 'reports-people', 'Title' => 'Mine', 'OwnerID' => Security::getCurrentUser()->ID,
        ])->write();
        SavedListView::create(['ListKey' => 'reports-people', 'Title' => 'Foreign private', 'OwnerID' => 999])->write();
        $page = ReportsController::create();
        $request = new HTTPRequest('GET', 'reports');
        $request->setSession(new Session([]));
        $page->setRequest($request);
        $list = $page->reportLists()->find('Title', 'People');
        $this->assertNotNull($list);
        $this->assertSame('reports-people', $list->Link);
        $titles = [];
        foreach ($list->Groups as $group) {
            foreach ($group->Reports as $report) {
                $titles[] = $report->Title;
            }
        }
        $this->assertSame(['Returners', 'Clean-up', 'Mine'], $titles);
        $this->assertStringContainsString('reports-people?view=returners', $list->Groups->first()->Reports->first()->Link);

        ReportsTestController::$visible = false;
        $this->assertNull($page->reportLists()->find('Title', 'People'), 'lists the member cannot see are left out');
        $this->logOut();
        $this->assertFalse($page->canView());
    }
}
