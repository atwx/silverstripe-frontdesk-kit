<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 * @property int $Progress
 */
class SortTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_SortTestModel';
    private static array $db = ['Title' => 'Varchar', 'Progress' => 'Int', 'Note' => 'Varchar'];
}

class SortTestController extends FrontdeskSubController implements TestOnly
{
    private static string $managed_model = SortTestModel::class;
    private static string $url_segment = 'sort-test';

    public function canView($member = null): bool
    {
        return true;
    }

    protected function getBaseQuery(): DataList
    {
        return SortTestModel::get()->sort('ID');
    }

    protected function defineColumns(): ColumnCollection
    {
        return ColumnCollection::create()
            ->make('Title', 'Titel')->sortable()->end()
            ->make('ProgressLabel', 'Fortschritt')->sortable('Progress')->end()
            ->make('Length', 'Länge')
                ->sortable(fn (DataList $list, string $dir) => $list->orderBy('LENGTH("Title") ' . $dir))
                ->end()
            ->make('Note', 'Notiz')->end();
    }

    public function query(): DataList
    {
        return $this->getQuery();
    }
}

class ColumnSortTest extends SapphireTest
{
    protected static $extra_dataobjects = [SortTestModel::class];

    public function testSortByFieldOtherFieldAndCallback(): void
    {
        foreach ([['Bb', 2], ['A', 10], ['Ccc', 5]] as [$title, $progress]) {
            SortTestModel::create(['Title' => $title, 'Progress' => $progress])->write();
        }
        $this->assertSame(['Bb', 'A', 'Ccc'], $this->sub([])->query()->column('Title'), 'unsorted: base order');
        $this->assertSame(['A', 'Bb', 'Ccc'], $this->sub(['sort' => 'Title'])->query()->column('Title'));
        $this->assertSame(
            ['A', 'Ccc', 'Bb'],
            $this->sub(['sort' => 'ProgressLabel', 'dir' => 'desc'])->query()->column('Title')
        );
        $this->assertSame(['Ccc', 'Bb', 'A'], $this->sub(['sort' => 'Length', 'dir' => 'desc'])->query()->column('Title'));
        $this->assertSame(['Bb', 'A', 'Ccc'], $this->sub(['sort' => 'Note'])->query()->column('Title'), 'not sortable');

        $headers = $this->sub(['sort' => 'Title', 'q' => 'x'])->ColumnHeaders();
        $title = $headers->first();
        $this->assertSame('asc', $title->SortDir);
        $this->assertStringContainsString('sort=Title', (string) $title->SortLink);
        $this->assertStringContainsString('dir=desc', (string) $title->SortLink);
        $this->assertStringContainsString('q=x', (string) $title->SortLink);
        $this->assertStringContainsString('dir=asc', (string) $headers->offsetGet(1)->SortLink);
        $this->assertNull($headers->last()->SortLink);
    }

    /**
     * @param array<string, string> $vars
     */
    private function sub(array $vars): SortTestController
    {
        $parent = TestFdkController::create();
        $sub = SortTestController::create();
        $request = new HTTPRequest('GET', 'test-items/sort-test/1', $vars);
        $request->setSession(new Session([]));
        $parent->setRequest($request);
        $sub->setRequest($request);
        $sub->setParentContext($parent, 1);
        return $sub;
    }
}
