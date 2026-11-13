<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\Table\ActionException;
use Atwx\SilverstripeFrontdeskKit\Table\RecordAction;
use Atwx\SilverstripeFrontdeskKit\Table\TimelineEntry;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\SecurityToken;

class RecordActionTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RecordActionTestModel';
    private static array $db = ['Name' => 'Varchar', 'State' => 'Varchar', 'Secret' => 'Varchar'];
}

class RecordActionTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = RecordActionTestModel::class;
    private static string $url_segment = 'ra-items';

    /** @var array<int, array{int, ?string, string}> */
    public array $calls = [];

    /** @var array<string, mixed> */
    public array $planned = [];

    protected function defineRecordActions(DataObject $record): array
    {
        if ($record->State === 'closed') {
            return [];
        }
        return [
            RecordAction::create('state', 'Change state')
                ->options(['open' => 'Open', 'closed' => 'Closed'])
                ->withComment('Comment')
                ->handle(function (DataObject $record, ?string $option, string $comment) {
                    if ($option === 'open' && $record->State === 'open') {
                        throw new ActionException('Already open.');
                    }
                    $this->calls[] = [$record->ID, $option, $comment];
                    $record->State = $option;
                    $record->write();
                    return 'State changed.';
                }),
            RecordAction::create('assign', 'Assign')
                ->select('lead', 'Lead', [3 => 'Ann', 5 => 'Bob'], '5'),
            RecordAction::create('plan', 'Plan')
                ->multiSelect('schools', 'Schools', [1 => 'Eton', 2 => 'Harrow', 3 => 'Rugby'], [2])
                ->input('due', 'Due', 'date', '2026-12-01', true)
                ->input('hours', 'Hours', 'number')
                ->handle(function (DataObject $record, ?string $option, string $comment, array $values) {
                    $this->planned = $values;
                    return null;
                }),
        ];
    }

    protected function defineTimeline(DataObject $record): ?ArrayList
    {
        return ArrayList::create([
            TimelineEntry::create('2026-01-01 10:00:00', 'old'),
            TimelineEntry::create('2026-03-01 10:00:00', 'new'),
        ]);
    }

    protected function defineFieldLayout(?DataObject $record): ?FieldLayout
    {
        return FieldLayout::create()->group('Main', ['Name', 'State'])->setLevel('Secret', FieldLayout::HIDDEN);
    }

}

class RecordActionControllerTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [RecordActionTestModel::class];

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

    private function post(RecordActionTestController $ctrl, int $id, array $vars): void
    {
        $request = new HTTPRequest('POST', 'ra-items/recordaction/' . $id, [], $vars);
        $request->setRouteParams(['ID' => $id]);
        $request->setSession(new Session([]));
        $ctrl->setRequest($request);
        $ctrl->recordaction($request);
    }

    private function record(string $state = 'new'): RecordActionTestModel
    {
        $record = RecordActionTestModel::create(['Name' => 'A', 'State' => $state, 'Secret' => 's']);
        $record->write();
        return $record;
    }

    public function testActionRunsWithOptionAndComment(): void
    {
        $record = $this->record();
        $ctrl = RecordActionTestController::create();
        $this->post($ctrl, $record->ID, ['RecordAction' => 'state', 'Option' => 'open', 'Comment' => 'ok']);

        $this->assertSame([[$record->ID, 'open', 'ok']], $ctrl->calls);
        $this->assertSame('open', RecordActionTestModel::get()->byID($record->ID)?->State);
        $flash = $ctrl->FlashMessage();
        $this->assertSame('State changed.', $flash?->Text);
        $this->assertSame('success', $flash?->Type);
        $this->assertTrue($ctrl->getResponse()->isRedirect());
    }

    public function testRefusedActionShowsError(): void
    {
        $record = $this->record('open');
        $ctrl = RecordActionTestController::create();
        $this->post($ctrl, $record->ID, ['RecordAction' => 'state', 'Option' => 'open']);

        $this->assertSame([], $ctrl->calls);
        $this->assertSame('error', $ctrl->FlashMessage()?->Type);
    }

    public function testInvalidOptionIsRefused(): void
    {
        $record = $this->record();
        $ctrl = RecordActionTestController::create();
        $this->post($ctrl, $record->ID, ['RecordAction' => 'state', 'Option' => 'deleted']);
        $this->assertSame([], $ctrl->calls);
        $this->assertSame('error', $ctrl->FlashMessage()?->Type);
    }

    public function testActionNotOfferedForRecordIsRejected(): void
    {
        $record = $this->record('closed');
        $ctrl = RecordActionTestController::create();
        try {
            $this->post($ctrl, $record->ID, ['RecordAction' => 'state', 'Option' => 'open']);
            $this->fail('Expected HTTP 400');
        } catch (\SilverStripe\Control\HTTPResponse_Exception $e) {
            $this->assertSame(400, $e->getResponse()->getStatusCode());
        }
        $this->assertSame([], $ctrl->calls);
    }

    public function testGetIsRejected(): void
    {
        $record = $this->record();
        $ctrl = RecordActionTestController::create();
        $request = new HTTPRequest('GET', 'ra-items/recordaction/' . $record->ID);
        $request->setRouteParams(['ID' => $record->ID]);
        $request->setSession(new Session([]));
        $ctrl->setRequest($request);
        $this->expectException(\SilverStripe\Control\HTTPResponse_Exception::class);
        $ctrl->recordaction($request);
    }

    public function testViewDataHasActionsTimelineAndGroups(): void
    {
        $record = $this->record();
        $ctrl = RecordActionTestController::create();
        $request = new HTTPRequest('GET', 'ra-items/view/' . $record->ID);
        $request->setRouteParams(['ID' => $record->ID]);
        $request->setSession(new Session([]));
        $ctrl->setRequest($request);

        $data = $ctrl->view($request);

        $this->assertSame(['state', 'assign', 'plan'], $data['RecordActions']->column('Name'));
        $select = $data['RecordActions']->find('Name', 'assign')->Selects->first();
        $this->assertSame(['5'], $select->Options->filter('Selected', true)->column('Value'), 'numeric current value is preselected');
        $this->assertSame(['open', 'closed'], $data['RecordActions']->first()->Options->column('Value'));
        $this->assertSame(['new', 'old'], $data['Timeline']->column('Title'), 'newest first');
        $html = (string) $data['ViewContent'];
        $this->assertStringContainsString('Main', $html);
        $this->assertStringNotContainsString('>s<', $html, 'hidden field not shown');
    }

    public function testMultiSelectAndInputsReachTheHandler(): void
    {
        $record = $this->record();
        $ctrl = RecordActionTestController::create();
        $this->post($ctrl, $record->ID, [
            'RecordAction' => 'plan',
            'Select' => ['schools' => ['1', '3', '3']],
            'Input' => ['due' => '2026-12-24', 'hours' => ' 2.5 '],
        ]);
        $this->assertSame(['schools' => ['1', '3'], 'due' => '2026-12-24', 'hours' => '2.5'], $ctrl->planned);

        foreach ([
            [['schools' => ['9']], ['due' => '2026-12-24']],
            [[], ['due' => '24.12.2026']],
            [[], ['due' => '']],
            [[], ['due' => '2026-12-24', 'hours' => 'many']],
        ] as [$select, $input]) {
            $ctrl = RecordActionTestController::create();
            $this->post($ctrl, $record->ID, ['RecordAction' => 'plan', 'Select' => $select, 'Input' => $input]);
            $this->assertSame([], $ctrl->planned, json_encode([$select, $input]));
            $this->assertSame('error', $ctrl->FlashMessage()?->Type);
        }

        $ctrl = RecordActionTestController::create();
        $request = new HTTPRequest('GET', 'ra-items/view/' . $record->ID);
        $request->setRouteParams(['ID' => $record->ID]);
        $request->setSession(new Session([]));
        $ctrl->setRequest($request);
        $html = (string) $ctrl->customise(['RecordActions' => $ctrl->view($request)['RecordActions']])
            ->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordActionDialogs');
        $this->assertStringContainsString('name="Select[schools][]" value="2" checked', $html);
        $this->assertStringContainsString('type="date" name="Input[due]" value="2026-12-01"', $html);
    }
}
