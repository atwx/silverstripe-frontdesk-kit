<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\SelectFilter;
use Atwx\SilverstripeFrontdeskKit\Model\SavedExport;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\Export;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;

class SavedExportTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_SavedExportTestModel';
    private static array $db = ['Name' => 'Varchar', 'City' => 'Varchar', 'Status' => 'Varchar', 'Secret' => 'Varchar'];
    private static string $default_sort = 'Name ASC';
}

class SavedExportTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = SavedExportTestModel::class;
    private static string $url_segment = 'saved-export-people';
    private static string $title = 'People';

    public static bool $withCodeExport = false;

    public function canView($member = null): bool
    {
        return true;
    }

    protected function defineColumns(): ColumnCollection
    {
        return ColumnCollection::create()
            ->make('Name', 'Name')->end()
            ->make('City', 'City')->end()
            ->make('Status', 'Status')->optional()->end()
            ->make('Secret', 'Secret')->exportOnly()->end();
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()->add(SelectFilter::create('Status', 'Status')
            ->options(['a' => 'Active', 'o' => 'Old'])
            ->apply(fn (DataList $list, $value) => $list->filter('Status', $value)));
    }

    protected function defineListViews(): array
    {
        return [ListView::create('active', 'Active')->filters(['Status' => 'a'])];
    }

    protected function defineExports(): array
    {
        return self::$withCodeExport ? [Export::create('mailing', 'Mailing')->columns(['Name'])] : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function savedData(int $id): ?array
    {
        return $this->savedExportData($id);
    }
}

class SavedExportTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [SavedExportTestModel::class];

    private Member $me;

    protected function setUp(): void
    {
        parent::setUp();
        SavedExportTestController::$withCodeExport = false;
        foreach ([['Anna', 'Berlin', 'a', 's1'], ['Bert', 'Hamburg', 'o', 's2'], ['Cleo', 'Berlin', 'a', 's3']] as [$n, $c, $s, $x]) {
            SavedExportTestModel::create(['Name' => $n, 'City' => $c, 'Status' => $s, 'Secret' => $x])->write();
        }
        $this->logInWithPermission('FDK_SAVED_EXPORT_PEOPLE_EXPORT');
        $this->me = \SilverStripe\Security\Security::getCurrentUser();
    }

    private function controller(array $get = [], string $method = 'GET', array $post = [], array $params = []): SavedExportTestController
    {
        $controller = SavedExportTestController::create();
        $request = new HTTPRequest($method, 'saved-export-people', $get, $post);
        $request->setSession(new Session([SecurityToken::inst()->getName() => 'tok']));
        $request->setRouteParams($params);
        $controller->setRequest($request);
        return $controller;
    }

    private function export(array $data): SavedExport
    {
        $export = SavedExport::create(array_merge([
            'ListKey' => 'saved-export-people',
            'Title' => 'Cities',
            'OwnerID' => $this->me->ID,
        ], $data));
        $export->write();
        return $export;
    }

    public function testOneButtonWithoutMoreExportsOrRight(): void
    {
        $this->logInWithPermission('SOMETHING');
        $action = $this->controller()->Actions()->last();
        $this->assertNull($action->MenuItems);
        $this->assertStringContainsString('saved-export-people/export', $action->Link);
    }

    public function testMenuWithCodeSavedAndCreate(): void
    {
        SavedExportTestController::$withCodeExport = true;
        $this->export(['Columns' => '[{"name":"City"}]']);
        $this->export(['Title' => 'Foreign', 'OwnerID' => 999, 'Columns' => '[{"name":"Name"}]']);
        $this->export(['Title' => 'Shared', 'OwnerID' => 999, 'Shared' => true, 'Columns' => '[{"name":"Name"}]']);
        $this->export(['Title' => 'Only old', 'Views' => '["old"]', 'Columns' => '[{"name":"Name"}]']);

        $menu = $this->controller(['Status' => 'a'])->Actions()->last();
        $this->assertSame(['Export', 'Mailing'], $menu->MenuItems->column('Title'));
        $this->assertSame(['Cities', 'Shared'], $menu->SavedItems->column('Title'), 'own and shared, in this view');
        $this->assertStringContainsString('Status=a', $menu->SavedItems->first()->Link);
        $this->assertStringContainsString('saved=', $menu->SavedItems->first()->Link);
        $this->assertStringContainsString('savedexport/new?from=', $menu->CreateLink);

        $html = (string) $this->controller()->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\PageActions');
        $this->assertStringContainsString('fdk-export-menu', $html);
        $this->assertStringContainsString('Set up export …', $html);
    }

    public function testSavedExportColumnsWidthsAndStoredFilters(): void
    {
        $export = $this->export([
            'Columns' => '[{"name":"Secret","width":12},{"name":"Name"},{"name":"Gone"}]',
            'FilterQuery' => 'Status=a',
        ]);
        $data = $this->controller()->savedData((int) $export->ID);
        $this->assertSame(['Secret', 'Name'], array_map(fn ($c) => $c->getName(), $data['columns']->toArray()), 'export-only columns too; unknown ones left out');
        $this->assertEquals(12, $data['columns']->toArray()[0]->getWidth());
        $this->assertSame(['Anna', 'Cleo'], $data['items']->column('Name'), 'its filters on an unfiltered list');
        $this->assertStringStartsWith('Cities ', $data['fileName']);

        $data = $this->controller(['Status' => 'o'])->savedData((int) $export->ID);
        $this->assertSame(['Bert'], $data['items']->column('Name'), 'a filtered list as shown');

        $foreign = $this->export(['OwnerID' => 999, 'Columns' => '[{"name":"Name"}]']);
        $this->assertNull($this->controller()->savedData((int) $foreign->ID), 'not shared');
    }

    public function testSaveFormKeepsKnownColumnsAndFilters(): void
    {
        $controller = $this->controller([], 'POST');
        $form = $controller->SavedExportForm();
        $this->assertInstanceOf(Form::class, $form);
        $controller->doSaveExport([
            'Title' => 'Mailing 2026',
            'FileName' => 'mailing',
            'Columns' => '[{"name":"City","width":20},{"name":"Nope"},{"name":"Name"}]',
            'Views' => ['active', 'bogus'],
            'KeepFilters' => '1',
            'FilterQuery' => 'Status=a&view=x&Other=1',
            'Shared' => '1',
        ], $form);
        $export = SavedExport::get()->find('Title', 'Mailing 2026');
        $this->assertNotNull($export);
        $this->assertSame([['name' => 'City', 'width' => 20], ['name' => 'Name', 'width' => null]], $export->columnList());
        $this->assertSame(['active'], $export->viewKeys());
        $this->assertSame('Status=a', $export->FilterQuery, 'only filter and search parameters');
        $this->assertTrue((bool) $export->Shared);
        $this->assertSame($this->me->ID, (int) $export->OwnerID);
    }

    public function testRightsCopyAndDelete(): void
    {
        $own = $this->export(['Columns' => '[{"name":"Name"}]']);
        $shared = $this->export(['Title' => 'Shared', 'OwnerID' => 999, 'Shared' => true, 'Columns' => '[{"name":"Name"}]']);

        SecurityToken::enable();
        try {
            $post = ['SecurityID' => 'tok'];
            $call = function (string $action, int $id) use ($post): int {
                $controller = $this->controller([], 'POST', $post, ['ID' => $id]);
                $controller->pushCurrent();
                try {
                    return $this->statusOf(fn () => $controller->$action($controller->getRequest()));
                } finally {
                    $controller->popCurrent();
                }
            };
            $this->assertSame(404, $call('savedexportdelete', (int) $shared->ID), 'not mine');
            $call('savedexportcopy', (int) $shared->ID);
            $copy = SavedExport::get()->find('Title', 'Copy of Shared');
            $this->assertNotNull($copy);
            $this->assertSame($this->me->ID, (int) $copy->OwnerID);
            $this->assertFalse((bool) $copy->Shared);
            $call('savedexportdelete', (int) $own->ID);
            $this->assertNull(SavedExport::get()->byID($own->ID));
        } finally {
            SecurityToken::disable();
        }

        $this->logInWithPermission('SOMETHING');
        $this->assertFalse($this->controller()->canManageExports());
        $this->assertSame(403, $this->statusOf(fn () => $this->controller()->savedexports($this->controller()->getRequest())));
    }

    private function statusOf(callable $call): int
    {
        try {
            $call();
        } catch (HTTPResponse_Exception $e) {
            return $e->getResponse()->getStatusCode();
        }
        return 200;
    }
}
