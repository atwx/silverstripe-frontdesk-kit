<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Forms\CheckboxSetSelectAll;
use Atwx\SilverstripeFrontdeskKit\Table\BulkAction;
use Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\SecurityToken;

class FilterSwitcherTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_FilterSwitcherTestModel';
    private static array $db = ['Title' => 'Varchar'];
}

class FilterSwitcherTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = FilterSwitcherTestModel::class;
    private static string $url_segment = 'filter-switcher';

    /** @var array<int, array<int>> IDs passed to the bulk action, per call */
    public array $calls = [];

    public bool $editable = true;

    public function canView($member = null): bool
    {
        return true;
    }

    public function canEdit($member = null): bool
    {
        return $this->editable;
    }

    protected function defineBulkActions(): array
    {
        return [
            BulkAction::create('done', 'Done', function ($records) {
                $this->calls[] = $records->column('ID');
                return count($this->calls[array_key_last($this->calls)]) . ' done';
            }),
        ];
    }
}

/**
 * Filter bar triggers, bulk actions without a selection, the searchable record
 * switcher and "select all" for checkbox sets.
 */
class FilterSwitcherSelectAllTest extends SapphireTest
{
    protected static $extra_dataobjects = [FilterSwitcherTestModel::class];

    protected function setUp(): void
    {
        parent::setUp();
        SecurityToken::disable();
    }

    protected function tearDown(): void
    {
        SecurityToken::enable();
        parent::tearDown();
    }

    private function bulk(array $post): array
    {
        $controller = FilterSwitcherTestController::create();
        $post += ['BulkAction' => 'done', 'BackURL' => '/filter-switcher'];
        $request = new HTTPRequest('POST', 'filter-switcher/bulk', [], $post);
        $session = new Session([]);
        $request->setSession($session);
        $controller->setRequest($request);
        $controller->bulk($request);
        return [$controller->calls, $session->get('FrontdeskBulkMessage')];
    }

    public function testFilterBarDoesNotReloadOnChangeOfTextFields(): void
    {
        $template = (string) file_get_contents(
            dirname(__DIR__, 2) . '/templates/Atwx/SilverstripeFrontdeskKit/Controller/Layout/FrontdeskController.ss'
        );
        $this->assertMatchesRegularExpression('/hx-trigger="change\[fdkFilterChange\(target\)\]/', $template);
        $this->assertStringContainsString('input[fdkFilterInput(target)] delay:400ms', $template);
        $this->assertStringContainsString('hx-sync="this:replace"', $template);
        $script = (string) file_get_contents(dirname(__DIR__, 2) . '/client/dist/frontdesk.js');
        $this->assertStringContainsString('fdkFilterChange', $script, 'the built bundle defines the trigger filters');
    }

    public function testBulkActionWithoutSelectionDoesNotRun(): void
    {
        [$calls, $message] = $this->bulk([]);
        $this->assertSame([], $calls);
        $this->assertSame('No records selected.', $message);

        $record = FilterSwitcherTestModel::create(['Title' => 'a']);
        $record->write();
        [$calls, $message] = $this->bulk(['ids' => [(string) $record->ID]]);
        $this->assertSame([[$record->ID]], $calls);
        $this->assertSame('1 done', $message);
    }

    public function testNoBulkActionsWithoutEditPermission(): void
    {
        $controller = FilterSwitcherTestController::create();
        $this->assertCount(1, $controller->BulkActions());
        $controller->editable = false;
        $this->assertCount(0, $controller->BulkActions(), 'no checkboxes for readers');
    }

    public function testSwitcherBecomesSearchableFromThreshold(): void
    {
        RecordSwitcher::config()->set('searchable_from', 3);
        $switcher = RecordSwitcher::create()->option('A', '#a', true)->option('B', '#b');
        $this->assertFalse($switcher->IsSearchable());
        $switcher->option('Ä-Schule', '#c');
        $this->assertTrue($switcher->IsSearchable());
        $this->assertFalse($switcher->searchable(false)->IsSearchable(), 'explicitly off');
        $this->assertTrue(RecordSwitcher::create()->option('A', '#a')->searchable()->IsSearchable(), 'explicitly on');
        $this->assertSame('ä-schule', $switcher->Options()->last()->SearchText);
    }

    public function testSearchableSwitcherRendersSearchBox(): void
    {
        $render = function (RecordSwitcher $switcher): string {
            $data = ArrayData::create([
                'RecordPages' => ArrayList::create([
                    ArrayData::create(['Type' => 'switcher', 'Switcher' => $switcher]),
                ]),
            ]);
            return (string) $data->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordPageView');
        };
        $switcher = fn () => RecordSwitcher::create()->option('Schule A', '/a', true)->option('Schule B', '/b');
        $this->assertStringNotContainsString('data-fdk-switcher-search', $render($switcher()));

        $html = $render($switcher()->searchable());
        $this->assertStringContainsString('data-fdk-switcher-search', $html);
        $this->assertStringContainsString('type="search"', $html);
        $this->assertStringContainsString('data-search="schule b"', $html);
        $this->assertStringContainsString('href="/b"', $html);
    }

    public function testSelectAllFromMinimumOptions(): void
    {
        $two = CheckboxSetSelectAll::apply(CheckboxSetField::create('E', 'E', [1 => 'a', 2 => 'b']));
        $this->assertNull($two->getAttribute('data-fdk-select-all'));

        $three = CheckboxSetSelectAll::apply(CheckboxSetField::create('E', 'E', [1 => 'a', 2 => 'b', 3 => 'c']));
        $this->assertSame('Select all', $three->getAttribute('data-fdk-select-all'));
        $this->assertSame('None', $three->getAttribute('data-fdk-select-none'));
        $this->assertStringContainsString('data-fdk-select-all="Select all"', (string) $three->Field());

        $this->assertNotNull(CheckboxSetSelectAll::apply(CheckboxSetField::create('E', 'E', [1 => 'a', 2 => 'b']), 2)
            ->getAttribute('data-fdk-select-all'));
    }
}
