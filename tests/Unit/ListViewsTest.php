<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\SelectFilter;
use Atwx\SilverstripeFrontdeskKit\Model\SavedListView;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;

class ListViewsTestModel extends \SilverStripe\ORM\DataObject implements TestOnly
{
    private static string $table_name = 'FDK_ListViewsTestModel';
    private static array $db = ['Name' => 'Varchar', 'Kind' => 'Varchar', 'Score' => 'Int', 'City' => 'Varchar'];
}

class ListViewsTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = ListViewsTestModel::class;
    private static string $url_segment = 'list-views';

    public bool $editor = false;

    public function canView($member = null): bool
    {
        return true;
    }

    public function canEdit($member = null): bool
    {
        return $this->editor;
    }

    public function names(): array
    {
        return $this->getQuery()->column('Name');
    }

    public function chosenView(): ?ListView
    {
        return $this->currentListView();
    }

    protected function defineColumns(): ColumnCollection
    {
        return ColumnCollection::create()
            ->make('Name', 'Name')->end()
            ->make('Kind', 'Kind')->end()
            ->make('Score', 'Score')->optional()->end()
            ->make('City', 'City')->exportOnly()->end();
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()->add(
            SelectFilter::create('Kind', 'Kind')
                ->options(['a' => 'A', 'b' => 'B'])
                ->apply(fn ($list, $value) => $list->filter('Kind', $value))
        );
    }

    protected function defineListViews(): array
    {
        return [
            ListView::create('kind-a', 'Kind A')->filters(['Kind' => 'a']),
            ListView::create('top', 'Top')
                ->query(fn (DataList $list) => $list->filter('Score:GreaterThan', 5))
                ->sort('Score DESC')
                ->columns(['Name', 'Score']),
        ];
    }
}

class ListViewsTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [ListViewsTestModel::class];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([['x', 'a', 3], ['y', 'b', 9], ['z', 'a', 7]] as [$name, $kind, $score]) {
            ListViewsTestModel::create(['Name' => $name, 'Kind' => $kind, 'Score' => $score, 'City' => 'K'])->write();
        }
    }

    private function controller(array $get = [], string $method = 'GET', array $post = [], array $params = []): ListViewsTestController
    {
        $controller = ListViewsTestController::create();
        $request = new HTTPRequest($method, 'list-views', $get, $post);
        $request->setRouteParams($params);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        return $controller;
    }

    private function names(ListViewsTestController $controller): array
    {
        $names = $controller->names();
        sort($names);
        return $names;
    }

    public function testListWithoutViewHidesOptionalAndExportOnlyColumns(): void
    {
        $controller = $this->controller();
        $this->assertSame(['Name', 'Kind'], array_map(fn ($c) => $c->getName(), $controller->Columns()->toArray()));
        $this->assertSame(['x', 'y', 'z'], $this->names($controller));
    }

    public function testViewAppliesItsFilterValues(): void
    {
        $controller = $this->controller(['view' => 'kind-a']);
        $this->assertSame(['x', 'z'], $this->names($controller));
        $this->assertSame('a', $controller->FilterForm()->Fields()->dataFieldByName('Kind')->getValue());
    }

    public function testFilterValuesInTheRequestWinOverTheView(): void
    {
        $this->assertSame(['y'], $this->names($this->controller(['view' => 'kind-a', 'Kind' => 'b'])));
    }

    public function testViewQuerySortAndColumns(): void
    {
        $controller = $this->controller(['view' => 'top']);
        $this->assertSame(['y', 'z'], $controller->names(), 'sorted by score descending');
        $this->assertSame(['Name', 'Score'], array_map(fn ($c) => $c->getName(), $controller->Columns()->toArray()));
    }

    public function testUnknownViewIsIgnored(): void
    {
        $this->assertNull($this->controller(['view' => 'nope'])->chosenView());
    }

    public function testSaveViewKeepsOnlyFilterValuesAndKnownColumns(): void
    {
        $member = Member::create(['Email' => 'views@example.com']);
        $member->write();
        $this->logInAs($member);
        SecurityToken::disable();

        $response = $this->controller([], 'POST', [
            'Title' => 'Mine',
            'Query' => 'Kind=a&view=top&start=30',
            'Columns' => ['Name', 'City', 'Unknown'],
            'Shared' => '1',
        ])->saveview($this->controller([], 'POST', [
            'Title' => 'Mine',
            'Query' => 'Kind=a&view=top&start=30',
            'Columns' => ['Name', 'City', 'Unknown'],
            'Shared' => '1',
        ])->getRequest());

        $saved = SavedListView::get()->first();
        $this->assertNotNull($saved);
        $this->assertSame('Kind=a', $saved->FilterQuery);
        $this->assertSame(['Name', 'City'], $saved->columnNames());
        $this->assertFalse((bool) $saved->Shared, 'only editors may share');
        $this->assertStringContainsString('view=saved-' . $saved->ID, $response->getHeader('Location'));

        $controller = $this->controller(['view' => 'saved-' . $saved->ID]);
        $this->assertSame(['x', 'z'], $this->names($controller));
        $this->assertSame(['Name', 'City'], array_map(fn ($c) => $c->getName(), $controller->Columns()->toArray()));
    }

    public function testOtherMembersPersonalViewsAreNeitherListedNorDeletable(): void
    {
        $owner = Member::create(['Email' => 'owner@example.com']);
        $owner->write();
        $view = SavedListView::create(['ListKey' => 'list-views', 'Title' => 'Private', 'OwnerID' => $owner->ID]);
        $view->write();

        $other = Member::create(['Email' => 'other@example.com']);
        $other->write();
        $this->logInAs($other);
        SecurityToken::disable();

        $this->assertNull($this->controller(['view' => 'saved-' . $view->ID])->chosenView());
        $controller = $this->controller([], 'POST', [], ['ID' => $view->ID]);
        try {
            $controller->deleteview($controller->getRequest());
            $this->fail('expected 404');
        } catch (HTTPResponse_Exception $e) {
            $this->assertSame(404, $e->getResponse()->getStatusCode());
        }
        $this->assertNotNull(SavedListView::get()->byID($view->ID));
    }

    public function testSharedViewsAreListedForEveryone(): void
    {
        $owner = Member::create(['Email' => 'sharer@example.com']);
        $owner->write();
        $view = SavedListView::create([
            'ListKey' => 'list-views',
            'Title' => 'Shared',
            'OwnerID' => $owner->ID,
            'Shared' => true,
            'FilterQuery' => 'Kind=b',
        ]);
        $view->write();
        $this->logInAs(Member::create(['Email' => 'reader@example.com']));

        $controller = $this->controller(['view' => 'saved-' . $view->ID]);
        $this->assertSame(['y'], $this->names($controller));
        $titles = $controller->ListViews()->column('Title');
        $this->assertContains('Shared', $titles);
        $this->assertNull($controller->ListViews()->find('Title', 'Shared')->DeleteLink);
    }
}
