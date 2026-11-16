<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\TextFilter;
use Atwx\SilverstripeFrontdeskKit\Table\BulkAction;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\SecurityToken;

class BulkSelectionTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_BulkSelectionTestModel';
    private static array $db = ['Title' => 'Varchar', 'Area' => 'Varchar', 'Status' => 'Varchar'];
}

class BulkSelectionTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = BulkSelectionTestModel::class;
    private static string $url_segment = 'bulk-selection';

    /** @var array<int, array{ids: array<int>, arg: mixed}> */
    public array $calls = [];

    public function canView($member = null): bool
    {
        return true;
    }

    public function canEdit($member = null): bool
    {
        return true;
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()->add(
            TextFilter::create('Query', 'Suche')->apply(fn ($list, $value) => $list->filter('Title:PartialMatch', $value))
        );
    }

    protected function defineBulkActions(): array
    {
        $record = function (DataList $records, $arg) {
            $this->calls[] = ['ids' => $records->sort('ID')->column('ID'), 'arg' => $arg];
            return $records->count() . ' done';
        };
        return [
            BulkAction::create('status', 'Status', $record)->options(['a' => 'A', 'b' => 'B']),
            BulkAction::create('assign', 'Zuteilen', $record)
                ->fields(fn () => FieldList::create(
                    DropdownField::create('Area', 'Bereich', ['north' => 'Nord', 'south' => 'Süd'])
                        ->setHasEmptyDefault(true),
                    DropdownField::create('Status', 'Status', ['new' => 'Neu', 'invited' => 'Eingeladen'])
                        ->setValue('invited')
                ), ['Area']),
        ];
    }
}

/**
 * Bulk actions on all matches of the filter (all=1) and with fields in a dialog.
 */
class BulkSelectionFieldsTest extends SapphireTest
{
    protected static $extra_dataobjects = [BulkSelectionTestModel::class];

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        SecurityToken::disable();
        BulkSelectionTestModel::get()->removeAll();
        foreach (['Anna', 'Anton', 'Bert'] as $title) {
            $record = BulkSelectionTestModel::create(['Title' => $title]);
            $record->write();
            $this->ids[$title] = (int) $record->ID;
        }
    }

    protected function tearDown(): void
    {
        SecurityToken::enable();
        parent::tearDown();
    }

    /**
     * @return array{0: BulkSelectionTestController, 1: mixed, 2: Session}
     */
    private function post(string $action, array $post, array $get = [], bool $htmx = false): array
    {
        $controller = BulkSelectionTestController::create();
        $request = new HTTPRequest('POST', 'bulk-selection/' . $action, $get, $post + ['BackURL' => '/bulk-selection']);
        if ($htmx) {
            $request->addHeader('HX-Request', 'true');
        }
        $session = new Session([]);
        $request->setSession($session);
        $controller->setRequest($request);
        $controller->pushCurrent();
        try {
            $response = $controller->$action($request);
        } finally {
            $controller->popCurrent();
        }
        return [$controller, $response, $session];
    }

    public function testAllMatchesOfTheFilter(): void
    {
        [$controller, , $session] = $this->post('bulk', ['BulkAction' => 'status:a', 'all' => '1'], ['Query' => 'An']);
        $this->assertSame([[
            'ids' => [$this->ids['Anna'], $this->ids['Anton']],
            'arg' => 'a',
        ]], $controller->calls, 'all records matching the filter, not only the checked ones');
        $this->assertSame('2 done', $session->get('FrontdeskBulkMessage'));

        [$controller] = $this->post('bulk', ['BulkAction' => 'status:b', 'ids' => [(string) $this->ids['Bert']]]);
        $this->assertSame([$this->ids['Bert']], $controller->calls[0]['ids'], 'ids as before');
    }

    public function testFieldsActionDoesNotRunWithoutDialog(): void
    {
        $this->expectException(\SilverStripe\Control\HTTPResponse_Exception::class);
        $this->post('bulk', ['BulkAction' => 'assign', 'ids' => [(string) $this->ids['Bert']]]);
    }

    public function testDialogShowsFieldsDefaultsAndCount(): void
    {
        [$controller, $response] = $this->post(
            'bulkdialog',
            ['BulkAction' => 'assign', 'all' => '1'],
            ['Query' => 'An'],
            true
        );
        $html = (string) $response->getBody();
        $this->assertSame([], $controller->calls);
        $this->assertStringContainsString('For 2 entries', $html);
        $this->assertMatchesRegularExpression('/<option value="invited"[^>]*selected/', $html, 'default value');
        $this->assertStringContainsString('hx-post="bulk-selection/bulkdialog?Query=An"', $html);
        $this->assertStringContainsString('name="all" value="1"', $html);
    }

    public function testDialogValidatesAndRuns(): void
    {
        $ids = [(string) $this->ids['Anna'], (string) $this->ids['Bert']];
        [$controller, $response] = $this->post(
            'bulkdialog',
            ['BulkAction' => 'assign', 'BulkSubmit' => '1', 'ids' => $ids, 'Status' => 'new'],
            [],
            true
        );
        $this->assertSame([], $controller->calls, 'required field missing');
        $this->assertStringContainsString('For 2 entries', (string) $response->getBody());
        $this->assertStringContainsString('required', strtolower((string) $response->getBody()));

        [$controller, $response, $session] = $this->post(
            'bulkdialog',
            ['BulkAction' => 'assign', 'BulkSubmit' => '1', 'ids' => $ids, 'Area' => 'south', 'Status' => 'new'],
            [],
            true
        );
        $this->assertSame([[
            'ids' => [$this->ids['Anna'], $this->ids['Bert']],
            'arg' => ['Area' => 'south', 'Status' => 'new'],
        ]], $controller->calls);
        $this->assertInstanceOf(HTTPResponse::class, $response);
        $this->assertSame('/bulk-selection', $response->getHeader('HX-Redirect'));
        $this->assertSame('2 done', $session->get('FrontdeskBulkMessage'));
    }

    public function testDialogAsPageWithoutHtmx(): void
    {
        [$controller, $data] = $this->post('bulkdialog', ['BulkAction' => 'assign', 'ids' => [(string) $this->ids['Anna']]]);
        $this->assertIsArray($data);
        $controller->pushCurrent();
        try {
            $controller->doInit();
            $html = (string) $controller->getViewer('bulkdialog')->process($controller->customise($data));
        } finally {
            $controller->popCurrent();
        }
        $this->assertStringContainsString('For 1 entries', $html);
        $this->assertStringContainsString('Form_BulkActionForm', $html);
        $this->assertStringNotContainsString('fdk-bulk-bar', $html, 'the form page, not the list');
    }

    public function testInvalidOptionValueIsRefused(): void
    {
        [$controller, $response] = $this->post(
            'bulkdialog',
            ['BulkAction' => 'assign', 'BulkSubmit' => '1', 'ids' => [(string) $this->ids['Anna']], 'Area' => 'west'],
            [],
            true
        );
        $this->assertSame([], $controller->calls);
        $this->assertStringContainsString('For 1 entries', (string) $response->getBody());
    }
}
