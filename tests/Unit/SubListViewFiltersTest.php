<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\TextFilter;
use Atwx\SilverstripeFrontdeskKit\Model\SavedListView;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class SubListViewTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_SubListViewTestModel';
    private static array $db = ['Title' => 'Varchar'];
}

class SubListViewTestController extends FrontdeskSubController implements TestOnly
{
    private static string $managed_model = SubListViewTestModel::class;
    private static string $url_segment = 'sub-views';

    public function canView($member = null): bool
    {
        return true;
    }

    public function canEdit($member = null): bool
    {
        return true;
    }

    protected function getBaseQuery(): DataList
    {
        return SubListViewTestModel::get();
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()->add(
            TextFilter::create('Query', 'Suche')->apply(fn ($list, $value) => $list->filter('Title:PartialMatch', $value))
        );
    }
}

/**
 * A saved view chosen in a sub-list (e.g. a tab) filters the list right away,
 * not only once the filter form is sent.
 */
class SubListViewFiltersTest extends SapphireTest
{
    protected static $extra_dataobjects = [SubListViewTestModel::class];

    private function sub(array $get): SubListViewTestController
    {
        $parent = TestFdkController::create();
        $sub = SubListViewTestController::create();
        $request = new HTTPRequest('GET', 'test-items/sub-views/1', $get);
        $request->setSession(new Session([]));
        $parent->setRequest($request);
        $sub->setRequest($request);
        $sub->setParentContext($parent, 1);
        return $sub;
    }

    public function testChosenViewFiltersTheSubList(): void
    {
        SubListViewTestModel::get()->removeAll();
        foreach (['Anna', 'Anton', 'Bert'] as $title) {
            SubListViewTestModel::create(['Title' => $title])->write();
        }
        $this->logInWithPermission('ADMIN');
        $sub = $this->sub([]);
        $view = SavedListView::create([
            'ListKey' => $this->callListKey($sub),
            'Title' => 'An',
            'FilterQuery' => 'Query=An',
            'OwnerID' => (int) \SilverStripe\Security\Security::getCurrentUser()?->ID,
        ]);
        $view->write();

        $this->assertSame(3, $this->sub([])->Items()->getTotalItems());
        $sub = $this->sub(['view' => 'saved-' . $view->ID]);
        $this->assertSame(['Anna', 'Anton'], $sub->Items()->column('Title'), 'filtered by the view');
        $this->assertTrue($sub->FilterIsActive());
        $this->assertSame(
            ['Bert'],
            $this->sub(['view' => 'saved-' . $view->ID, 'Query' => 'Be'])->Items()->column('Title'),
            'own filter values win'
        );
    }

    private function callListKey(SubListViewTestController $sub): string
    {
        $method = new \ReflectionMethod($sub, 'listKey');
        return (string) $method->invoke($sub);
    }
}
