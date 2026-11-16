<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Model\ListColumnChoice;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;

class ColumnChoiceTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_ColumnChoiceTestModel';
    private static array $db = ['Title' => 'Varchar', 'Note' => 'Varchar', 'Answer' => 'Varchar'];
}

class ColumnChoiceTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = ColumnChoiceTestModel::class;
    private static string $url_segment = 'column-choice';

    public bool $editable = true;

    public bool $shared = false;

    /** @var array<int, string> data-driven columns, e.g. form questions */
    public array $questions = ['Answer'];

    public function canView($member = null): bool
    {
        return true;
    }

    public function canEdit($member = null): bool
    {
        return $this->editable;
    }

    protected function defineColumns(): ColumnCollection
    {
        $columns = ColumnCollection::create()
            ->make('Title', 'Titel')->end()
            ->make('Note', 'Notiz')->end()
            ->make('ID', 'Nr.')->exportOnly()->end();
        foreach ($this->questions as $question) {
            $columns->make($question, 'Frage ' . $question)->optional()->end();
        }
        return $columns;
    }

    protected function defineListViews(): array
    {
        return [ListView::create('short', 'Kurz')->columns(['Title'])];
    }

    protected function columnChoiceKey(): string
    {
        return $this->shared ? 'campaign-7' : parent::columnChoiceKey();
    }

    protected function columnChoiceShared(): bool
    {
        return $this->shared;
    }
}

/**
 * "Spalten" menu: per member or shared, optional and data-driven columns,
 * reset, views with own columns.
 */
class ColumnChoiceTest extends SapphireTest
{
    protected static $extra_dataobjects = [ColumnChoiceTestModel::class];

    protected function setUp(): void
    {
        parent::setUp();
        SecurityToken::disable();
        ListColumnChoice::get()->removeAll();
    }

    protected function tearDown(): void
    {
        SecurityToken::enable();
        parent::tearDown();
    }

    private function controller(array $get = [], array $post = [], bool $shared = false): ColumnChoiceTestController
    {
        $controller = ColumnChoiceTestController::create();
        $controller->shared = $shared;
        $request = new HTTPRequest($post ? 'POST' : 'GET', 'column-choice', $get, $post);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        return $controller;
    }

    /**
     * @return array<int, string>
     */
    private function shown(ColumnChoiceTestController $controller): array
    {
        return array_map(fn ($column) => $column->getName(), $controller->Columns()->toArray());
    }

    private function member(string $email): Member
    {
        $member = Member::get()->find('Email', $email) ?? Member::create(['Email' => $email]);
        $member->write();
        return $member;
    }

    public function testMembersChooseTheirOwnColumns(): void
    {
        $anna = $this->member('anna@example.org');
        $bert = $this->member('bert@example.org');
        $this->logInAs($anna);
        $controller = $this->controller();
        $this->assertSame(['Title', 'Note'], $this->shown($controller), 'default: no optional, no export-only');
        $this->assertTrue($controller->CanChooseColumns());
        $this->assertSame(['Title', 'Note', 'Answer'], $controller->ColumnChoice()->column('Name'), 'optional offered');

        // Order of the definition; unknown and export-only names are ignored.
        $post = ['Columns' => ['Answer', 'Title', 'ID', 'Nonsense'], 'BackURL' => '/column-choice?Query=x'];
        $controller = $this->controller([], $post);
        $response = $controller->choosecolumns($controller->getRequest());
        $this->assertStringEndsWith('/column-choice?Query=x', (string) $response->getHeader('Location'));
        $this->assertSame(['Title', 'Answer'], $this->shown($this->controller()));

        $this->logInAs($bert);
        $this->assertSame(['Title', 'Note'], $this->shown($this->controller()), 'not for other members');

        $this->logInAs($anna);
        $this->assertSame(['Title'], $this->shown($this->controller(['view' => 'short'])), 'a view with columns wins');
        $this->assertFalse($this->controller(['view' => 'short'])->CanChooseColumns());

        $controller = $this->controller([], ['reset' => '1', 'Columns' => ['Title']]);
        $controller->choosecolumns($controller->getRequest());
        $this->assertSame(['Title', 'Note'], $this->shown($this->controller()), 'back to the default');
    }

    public function testSharedChoiceForEditorsWithDataDrivenColumns(): void
    {
        $anna = $this->member('anna@example.org');
        $bert = $this->member('bert@example.org');
        $this->logInAs($anna);
        $controller = $this->controller([], ['Columns' => ['Title', 'Answer']], true);
        $controller->choosecolumns($controller->getRequest());
        $this->assertSame(0, (int) ListColumnChoice::get()->first()?->MemberID, 'stored for everyone');
        $this->assertSame('campaign-7', ListColumnChoice::get()->first()?->ChoiceKey);

        $this->logInAs($bert);
        $this->assertSame(['Title', 'Answer'], $this->shown($this->controller([], [], true)));
        $this->assertSame(['Title', 'Note'], $this->shown($this->controller()), 'other key unaffected');

        $reader = $this->controller([], [], true);
        $reader->editable = false;
        $this->assertFalse($reader->CanChooseColumns(), 'only editors change a shared choice');
        $reader = $this->controller([], ['Columns' => ['Note']], true);
        $reader->editable = false;
        try {
            $reader->choosecolumns($reader->getRequest());
            $this->fail('refused');
        } catch (HTTPResponse_Exception $e) {
            $this->assertSame(400, $e->getResponse()->getStatusCode());
        }

        // The question was removed from the campaign: back to the default columns.
        $controller = $this->controller([], [], true);
        $controller->questions = [];
        $this->assertSame(['Title'], $this->shown($controller), 'chosen columns that no longer exist are left out');
    }
}
