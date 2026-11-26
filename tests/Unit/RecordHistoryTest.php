<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Table\RecordAction;
use Atwx\SilverstripeFrontdeskKit\Table\TimelineEntry;
use Atwx\SilverstripeFrontdeskKit\View\RecordPage;
use Atwx\SilverstripeFrontdeskKit\View\RecordPages;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;

class RecordHistoryTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RecordHistoryTestModel';
    private static array $db = ['Name' => 'Varchar'];
}

class RecordHistoryTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = RecordHistoryTestModel::class;
    private static string $url_segment = 'record-history';
    private static bool $timeline_page = true;
    private static array $record_action_menu = ['letter' => 'more', 'pdf' => 'more'];

    public static ?string $layout = null;

    public static bool $withTimeline = true;

    public function canView($member = null): bool
    {
        return true;
    }

    public function canEdit($member = null): bool
    {
        return true;
    }

    protected function defineTimeline(DataObject $record): ?ArrayList
    {
        return self::$withTimeline
            ? ArrayList::create([TimelineEntry::create('2026-11-01 10:00:00', 'Mail sent', str_repeat("Line\n", 20))])
            : null;
    }

    protected function defineRecordActions(DataObject $record): array
    {
        return [
            RecordAction::create('status', 'Change status')->handle(fn () => 'ok'),
            RecordAction::create('print', 'Print')->menu()->handle(fn () => 'ok'),
            RecordAction::create('letter', 'Letter')->handle(fn () => 'ok'),
        ];
    }

    protected function defineViewActions(DataObject $record): ArrayList
    {
        $actions = parent::defineViewActions($record);
        $actions->push(ArrayData::create(['Title' => 'PDF', 'Link' => '#pdf', 'Name' => 'pdf']));
        $actions->push(ArrayData::create(['Title' => 'Export', 'Link' => '#export', 'Menu' => 'more']));
        return $actions;
    }

    protected function defineRecordPages(DataObject $record): ?RecordPages
    {
        if (self::$layout === null) {
            return null;
        }
        $pages = RecordPages::create()->page(RecordPage::create('main', 'Main')->content(fn () => '<p>main</p>'));
        return self::$layout === 'tabs' ? $pages->layout('tabs') : $pages;
    }
}

class RecordHistoryTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [RecordHistoryTestModel::class];

    protected function tearDown(): void
    {
        RecordHistoryTestController::$layout = null;
        RecordHistoryTestController::$withTimeline = true;
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function view(string $segment = ''): array
    {
        $record = RecordHistoryTestModel::create(['Name' => 'Anna']);
        $record->write();
        $controller = RecordHistoryTestController::create();
        $request = new HTTPRequest('GET', 'record-history/view/' . $record->ID);
        $request->setRouteParams(['ID' => $record->ID, 'OtherID' => $segment]);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        return $controller->view($request);
    }

    public function testMenuWithHistoryAndMovedActions(): void
    {
        $data = $this->view();
        $this->assertNull($data['Timeline'], 'the timeline is on its own page');
        $menu = $data['MoreMenu'];
        $this->assertSame(['History', 'PDF', 'Export'], $menu->Links->column('Title'));
        $this->assertStringEndsWith('/history', $menu->Links->first()->Link);
        $this->assertSame(['print', 'letter'], $menu->Actions->column('Name'), 'menu() and record_action_menu');
        $this->assertSame(['← Back'], $data['ViewActions']->exclude('Primary', true)->column('Title'));

        $html = (string) RecordHistoryTestController::create()->customise($data)
            ->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordActionButtons');
        $this->assertStringContainsString('>Change status</button>', $html);
        $this->assertStringContainsString('fdk-record-more', $html);
        $this->assertSame(1, substr_count($html, '>Print</button>'), 'only in the menu');
    }

    public function testHistoryPage(): void
    {
        $data = $this->view('history');
        $this->assertTrue($data['IsHistory']);
        $this->assertCount(1, $data['Timeline']);
        $this->assertStringContainsString('record-history/view/', $data['BackLink']);
        $html = (string) RecordHistoryTestController::create()->customise($data)
            ->renderWith('Atwx\\SilverstripeFrontdeskKit\\Controller\\Layout\\FrontdeskController_view');
        $this->assertStringContainsString('History: Anna', $html);
        $this->assertStringContainsString('data-fdk-clamp', $html, 'long entries can be closed');
    }

    public function testRecordPagesInBothLayouts(): void
    {
        foreach (['sidebar', 'tabs'] as $layout) {
            RecordHistoryTestController::$layout = $layout;
            $data = $this->view();
            $this->assertSame('History', $data['MoreMenu']->Links->first()->Title, $layout);
            $this->assertTrue($this->view('history')['IsHistory'], $layout);
        }
    }

    public function testWithoutTimelineOrSwitchedOff(): void
    {
        RecordHistoryTestController::$withTimeline = false;
        $this->assertSame(['PDF', 'Export'], $this->view()['MoreMenu']->Links->column('Title'));
        try {
            $this->view('history');
            $this->fail('no timeline, no page');
        } catch (HTTPResponse_Exception $e) {
            $this->assertSame(404, $e->getResponse()->getStatusCode());
        }

        RecordHistoryTestController::$withTimeline = true;
        Config::modify()->set(RecordHistoryTestController::class, 'timeline_page', false);
        $data = $this->view();
        $this->assertCount(1, $data['Timeline'], 'inline as before');
        $this->assertNotContains('History', $data['MoreMenu']->Links->column('Title'));
    }
}
