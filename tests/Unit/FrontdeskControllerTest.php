<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;

// ─── Test-Stubs ───────────────────────────────────────────────────────────────

class TestFdkModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_CtrlTestModel';
    private static array $db = ['Name' => 'Varchar'];
    private static array $summary_fields = ['Name' => 'Name'];
    private static string $singular_name = 'Test Item';
    private static string $plural_name = 'Test Items';
}

class TestFdkController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = TestFdkModel::class;
    private static string $url_segment = 'test-items';
    private static string $title = 'Test Items';

    private bool $editAllowed = false;

    public function setCanEdit(bool $v): void
    {
        $this->editAllowed = $v;
    }

    public function canEdit($member = null): bool
    {
        return $this->editAllowed;
    }

    // canView() not overridden → tests real behavior (false when no user logged in)

    public function exposeDefineRowActions(DataObject $record): array
    {
        return $this->defineRowActions($record);
    }

    public function exposeDefineFilters(): FilterCollection
    {
        return $this->defineFilters();
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

class FrontdeskControllerTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function makeController(array $vars = []): TestFdkController
    {
        $ctrl = TestFdkController::create();
        $req  = new HTTPRequest('GET', '/test-items', $vars);
        $req->setSession(new Session([]));
        $ctrl->setRequest($req);
        return $ctrl;
    }

    private function makeDummyRecord(int $id = 42): DataObject
    {
        $record = TestFdkModel::create();
        $record->ID = $id;
        return $record;
    }

    // ─── Config-Getter ───────────────────────────────────────────────────────

    public function testGetManagedModel(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame(TestFdkModel::class, $ctrl->getManagedModel());
    }

    public function testTitle(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('Test Items', $ctrl->Title());
    }

    // ─── Link ────────────────────────────────────────────────────────────────

    public function testLinkReturnsUrlSegment(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('test-items', $ctrl->Link());
    }

    public function testLinkWithAction(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('test-items/add', $ctrl->Link('add'));
    }

    // ─── CurrentQuery ────────────────────────────────────────────────────────

    public function testCurrentQueryKeepsRegularVars(): void
    {
        $ctrl = $this->makeController(['Q' => 'foo']);
        $this->assertStringContainsString('Q=foo', $ctrl->CurrentQuery());
    }

    public function testCurrentQueryStripsSecurityID(): void
    {
        $ctrl = $this->makeController(['SecurityID' => 'abc', 'Q' => 'bar']);
        $query = $ctrl->CurrentQuery();
        $this->assertStringNotContainsString('SecurityID', $query);
        $this->assertStringContainsString('Q=bar', $query);
    }

    public function testCurrentQueryStripsActionSearch(): void
    {
        $ctrl = $this->makeController(['action_search' => '1', 'Q' => 'baz']);
        $query = $ctrl->CurrentQuery();
        $this->assertStringNotContainsString('action_search', $query);
        $this->assertStringContainsString('Q=baz', $query);
    }

    public function testCurrentQueryEmptyRequest(): void
    {
        $ctrl = $this->makeController();
        $this->assertSame('', $ctrl->CurrentQuery());
    }

    // ─── ContentLocale ───────────────────────────────────────────────────────

    public function testContentLocaleReturnsString(): void
    {
        $ctrl   = $this->makeController();
        $locale = $ctrl->ContentLocale();
        $this->assertIsString($locale);
        $this->assertNotEmpty($locale);
    }

    // ─── defineFilters ───────────────────────────────────────────────────────

    public function testDefineFiltersDefaultReturnsEmptyCollection(): void
    {
        $ctrl       = $this->makeController();
        $collection = $ctrl->exposeDefineFilters();
        $this->assertInstanceOf(FilterCollection::class, $collection);
        $this->assertSame([], $collection->getFilters());
    }

    // ─── defineColumns ───────────────────────────────────────────────────────

    public function testDefineColumnsDefaultReflectsSummaryFields(): void
    {
        $ctrl    = $this->makeController();
        $columns = $ctrl->Columns();
        $this->assertInstanceOf(ColumnCollection::class, $columns);
        $this->assertGreaterThan(0, $columns->count());
        $names = [];
        foreach ($columns as $col) {
            $names[] = $col->getName();
        }
        $this->assertContains('Name', $names);
    }

    // ─── defineRowActions ────────────────────────────────────────────────────

    public function testDefineRowActionsWithoutEditReturnsOnlyView(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setCanEdit(false);
        $actions = $ctrl->exposeDefineRowActions($this->makeDummyRecord());
        $this->assertCount(1, $actions);
    }

    public function testDefineRowActionsWithEditReturnsThreeActions(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setCanEdit(true);
        $actions = $ctrl->exposeDefineRowActions($this->makeDummyRecord());
        $this->assertCount(3, $actions);
    }

    public function testDefineRowActionsEditActionIsHtmx(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setCanEdit(true);
        $actions = $ctrl->exposeDefineRowActions($this->makeDummyRecord());
        // Edit is the second action (index 1)
        $this->assertTrue($actions[1]->isHtmxAction());
    }

    public function testDefineRowActionsDeleteActionIsDeleteType(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setCanEdit(true);
        $actions = $ctrl->exposeDefineRowActions($this->makeDummyRecord());
        // Delete is the third action (index 2)
        $this->assertTrue($actions[2]->isDeleteAction());
    }

    public function testDefineRowActionsUrlsContainRecordId(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setCanEdit(true);
        $actions = $ctrl->exposeDefineRowActions($this->makeDummyRecord(42));
        foreach ($actions as $action) {
            $this->assertStringContainsString('42', $action->getUrl());
        }
    }

    // ─── FilterForm ──────────────────────────────────────────────────────────

    public function testFilterFormNullWithNoFilters(): void
    {
        $ctrl = $this->makeController();
        $this->assertNull($ctrl->FilterForm());
    }

    // ─── Actions ─────────────────────────────────────────────────────────────

    public function testActionsAlwaysContainsExport(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setCanEdit(false);
        $actions = $ctrl->Actions();
        $this->assertInstanceOf(ArrayList::class, $actions);
        $titles = [];
        foreach ($actions as $action) {
            $titles[] = $action->Title;
        }
        $this->assertContains('Export', $titles);
    }

    public function testActionsContainsNewWhenCanEdit(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setCanEdit(true);
        $titles = [];
        foreach ($ctrl->Actions() as $action) {
            $titles[] = $action->Title;
        }
        $this->assertContains('New', $titles);
    }

    public function testActionsDoesNotContainNewWhenCantEdit(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setCanEdit(false);
        $titles = [];
        foreach ($ctrl->Actions() as $action) {
            $titles[] = $action->Title;
        }
        $this->assertNotContains('New', $titles);
    }

    // ─── RowActionsFor ───────────────────────────────────────────────────────

    public function testRowActionsForProducesCorrectArrayData(): void
    {
        $ctrl   = $this->makeController();
        $ctrl->setCanEdit(true);
        $record = $this->makeDummyRecord(99);
        $list   = $ctrl->RowActionsFor($record);

        $this->assertInstanceOf(ArrayList::class, $list);
        $this->assertGreaterThan(0, $list->count());

        $first = $list->first();
        $this->assertInstanceOf(ArrayData::class, $first);
        $this->assertNotEmpty($first->Label);
        $this->assertNotEmpty($first->Url);
        $this->assertIsBool($first->IsDelete);
        $this->assertIsBool($first->IsHtmx);
        $this->assertEquals(99, $first->RowId);
    }

    // ─── canView ─────────────────────────────────────────────────────────────

    public function testCanViewReturnsFalseWithoutLoggedInUser(): void
    {
        $ctrl = $this->makeController();
        $this->assertFalse($ctrl->canView());
    }
}
