<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskTemplateProvider;
use Atwx\SilverstripeFrontdeskKit\Model\RecentView;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * @property ?string $Name
 * @property bool $Hidden
 * @property int $HouseholdID
 */
class RecentPupil extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RecentPupil';
    private static array $db = ['Name' => 'Varchar', 'Hidden' => 'Boolean'];
    private static array $has_one = ['Household' => RecentHousehold::class];

    public function getTitle(): string
    {
        return (string) $this->Name;
    }

    public function canView($member = null): bool
    {
        return !$this->Hidden;
    }

    public function canDelete($member = null): bool
    {
        return true;
    }
}

/**
 * @property ?string $Name
 */
class RecentHousehold extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RecentHousehold';
    private static array $db = ['Name' => 'Varchar'];

    public function getTitle(): string
    {
        return (string) $this->Name;
    }

    public function canView($member = null): bool
    {
        return true;
    }
}

class RecentPupilController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = RecentPupil::class;
    private static string $url_segment = 'pupils';
    private static $recent_views = 2;
    private static $check_record_permissions = true;

    public function canView($member = null): bool
    {
        return true;
    }

    public function canDelete($member = null): bool
    {
        return true;
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
        return ColumnCollection::create()->make('Name', 'Name')->end();
    }

    protected function recentViewRecords(DataObject $record): array
    {
        return $record instanceof RecentPupil && $record->HouseholdID ? [$record->Household()] : [];
    }
}

class RecentHouseholdController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = RecentHousehold::class;
    private static string $url_segment = 'households';

    public function canView($member = null): bool
    {
        return true;
    }

    protected function defineColumns(): ColumnCollection
    {
        return ColumnCollection::create()->make('Name', 'Name')->end();
    }
}

class RecentViewsTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [RecentPupil::class, RecentHousehold::class];

    /**
     * @var array<string, RecentPupil>
     */
    private array $pupils = [];

    private RecentHousehold $household;

    protected function setUp(): void
    {
        parent::setUp();
        $this->household = RecentHousehold::create(['Name' => 'Familie Berg']);
        $this->household->write();
        foreach (['Anna', 'Ben', 'Cem'] as $name) {
            $pupil = RecentPupil::create(['Name' => $name]);
            $pupil->write();
            $this->pupils[$name] = $pupil;
        }
        $this->pupils['Anna']->HouseholdID = $this->household->ID;
        $this->pupils['Anna']->write();
        $this->logInAs(Member::create(['Email' => 'olga@example.com', 'FirstName' => 'Olga']));
    }

    /**
     * @param class-string<FrontdeskController> $class
     * @param array<string, string> $get
     * @param array<string, mixed> $params
     */
    private function controller(
        string $class = RecentPupilController::class,
        array $get = [],
        array $params = [],
        string $method = 'GET'
    ): FrontdeskController {
        $controller = $class::create();
        $request = new HTTPRequest($method, 'pupils', $get);
        $request->setRouteParams($params);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        return $controller;
    }

    private function viewOf(string $name): void
    {
        $controller = $this->controller(RecentPupilController::class, [], ['ID' => $this->pupils[$name]->ID]);
        $controller->view($controller->getRequest());
    }

    public function testViewsAreNotedLatestFirstAndCappedPerType(): void
    {
        $this->viewOf('Ben');
        $this->viewOf('Anna');
        $this->viewOf('Ben');
        $this->assertSame(
            [(int) $this->pupils['Ben']->ID, (int) $this->pupils['Anna']->ID],
            RecentView::idsFor(RecentPupil::class),
            'repeated view moves to the top'
        );

        $this->viewOf('Cem');
        $this->assertSame(
            [(int) $this->pupils['Cem']->ID, (int) $this->pupils['Ben']->ID],
            RecentView::idsFor(RecentPupil::class),
            'capped at recent_views: 2'
        );
        $this->assertSame([(int) $this->household->ID], RecentView::idsFor(RecentHousehold::class), 'related record');

        $recent = $this->controller(RecentPupilController::class, ['view' => 'recent']);
        $this->assertSame(['Cem', 'Ben'], $recent->names());
    }

    public function testBlockAcrossTypesWithLinks(): void
    {
        $this->viewOf('Anna');
        $this->viewOf('Ben');
        $items = FrontdeskTemplateProvider::FrontdeskRecentViews();
        $this->assertSame(['Ben', 'Familie Berg', 'Anna'], $items->column('Title'));
        $this->assertStringEndsWith('pupils/view/' . $this->pupils['Ben']->ID, (string) $items->first()->Link);
        $this->assertStringEndsWith(
            'households/view/' . $this->household->ID,
            (string) $items->offsetGet(1)->Link,
            'related record links to its own controller'
        );

        $this->pupils['Ben']->Hidden = true;
        $this->pupils['Ben']->write();
        $this->assertSame(['Familie Berg', 'Anna'], RecentView::recent()->column('Title'), 'not visible: skipped');
    }

    public function testDeletedRecordsAndOtherMembers(): void
    {
        $this->viewOf('Anna');
        $this->viewOf('Ben');

        $controller = $this->controller(RecentPupilController::class, [], ['ID' => $this->pupils['Ben']->ID], 'DELETE');
        $controller->delete($controller->getRequest());
        $this->assertNull(RecentPupil::get()->byID($this->pupils['Ben']->ID));
        $this->assertSame(
            [(int) $this->pupils['Anna']->ID],
            RecentView::idsFor(RecentPupil::class),
            'deleted: forgotten'
        );

        $this->pupils['Anna']->delete();
        $titles = RecentView::recent()->column('Title');
        $this->assertSame(['Familie Berg'], $titles, 'deleted elsewhere: dropped on read');
        $this->assertSame(1, RecentView::get()->count());

        $this->logInAs(Member::create(['Email' => 'lena@example.com', 'FirstName' => 'Lena']));
        $this->assertSame(0, RecentView::recent()->count(), 'per member');
    }

    public function testControllersWithoutRecentViewsNoteNothing(): void
    {
        $controller = $this->controller(RecentHouseholdController::class, [], ['ID' => $this->household->ID]);
        $controller->view($controller->getRequest());
        $this->assertSame(0, RecentView::get()->count());
        $this->assertStringNotContainsString('view=recent', implode(' ', $controller->ListViews()->column('Link')));
        $pupils = $this->controller();
        $this->assertStringContainsString('view=recent', implode(' ', $pupils->ListViews()->column('Link')));
    }
}
