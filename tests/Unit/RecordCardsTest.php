<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\View\RecordCard;
use Atwx\SilverstripeFrontdeskKit\View\RecordPage;
use Atwx\SilverstripeFrontdeskKit\View\RecordPages;
use Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;

class RecordCardsTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RecordCardsTestModel';
    private static array $db = [
        'Name' => 'Varchar',
        'School' => 'Varchar',
        'Grade' => 'Varchar',
        'Note' => 'Varchar',
        'Secret' => 'Varchar',
        'Status' => 'Varchar',
    ];
}

class RecordCardsTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = RecordCardsTestModel::class;
    private static string $url_segment = 'record-cards';

    public bool $tabs = true;

    public bool $withCards = true;

    public function canView($member = null): bool
    {
        return true;
    }

    public function canEdit($member = null): bool
    {
        return true;
    }

    public function setTestAction(string $action): void
    {
        $this->action = $action;
    }

    protected function defineFieldLayout(?DataObject $record): ?FieldLayout
    {
        return FieldLayout::create()
            ->group('Grunddaten', ['Name', 'School', 'Grade'], true, 'basics')
            ->group('Notizen', ['Note'], false, 'notes')
            ->group('Geheim', ['Secret'], true, 'secret')
            ->group('Status', ['Status'], true, 'status')
            ->setLevels(['Secret' => FieldLayout::HIDDEN]);
    }

    protected function defineHeaderStatus(DataObject $record): ?ArrayList
    {
        return ArrayList::create([
            ArrayData::create(['Title' => 'Akquise', 'Value' => 'offen', 'Color' => 'warning']),
            ArrayData::create(['Title' => 'Vorbereitung', 'Value' => 'läuft']),
        ]);
    }

    protected function defineRecordPages(DataObject $record): ?RecordPages
    {
        $pages = RecordPages::create()
            ->page(RecordPage::create('comments', 'Kommentare')->content(fn () => '<p>stream</p>'))
            ->switcher(RecordSwitcher::create()->option('Fall 2026', '/a', true)->option('Fall 2025', '/b')
                ->withAddLink('/add', '+ Fall'))
            ->page(RecordPage::create('notes', 'Notizen')->groups(['notes']));
        if ($this->tabs) {
            $pages->layout('tabs');
        }
        if ($this->withCards) {
            $pages
                ->card(RecordCard::create('state', 'Status')->groups(['status'])->content(fn () => '<p>phase</p>'))
                ->groupCards(null, ['notes'])
                ->card(RecordCard::create('self', 'Verwandt')->related(fn ($r) => $r, self::class))
                ->card(RecordCard::create('nothing', 'Leer')->content(fn () => ''));
        }
        return $pages;
    }
}

class RecordCardsTest extends SapphireTest
{
    protected static $extra_dataobjects = [RecordCardsTestModel::class];

    private function record(array $data = []): RecordCardsTestModel
    {
        $record = RecordCardsTestModel::create(array_merge(
            ['Name' => 'Max', 'School' => 'Gymnasium', 'Grade' => '', 'Note' => 'old', 'Secret' => 's'],
            $data
        ));
        $record->write();
        return $record;
    }

    private function controller(string $method, array $params = [], array $get = [], array $post = []): RecordCardsTestController
    {
        $controller = RecordCardsTestController::create();
        $request = new HTTPRequest($method, 'record-cards', $get, $post);
        $request->setRouteParams($params);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $controller->setTestAction($params['Action'] ?? 'view');
        return $controller;
    }

    /**
     * @return array<string, mixed>
     */
    private function view(RecordCardsTestModel $record, string $page = '', ?callable $setup = null): array
    {
        $controller = $this->controller('GET', ['ID' => $record->ID, 'OtherID' => $page]);
        if ($setup) {
            $setup($controller);
        }
        return $controller->view($controller->getRequest());
    }

    public function testSidebarLayoutIsTheDefault(): void
    {
        $data = $this->view($this->record(), '', fn ($c) => $c->tabs = false);
        $this->assertFalse($data['TabsLayout']);
        $this->assertArrayNotHasKey('RecordCards', $data);
        $html = (string) $this->controller('GET')->customise($data)
            ->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordPageView');
        $this->assertStringContainsString('fdk-record-sidebar', $html);
        $this->assertStringNotContainsString('fdk-record-card', $html);
    }

    public function testTabsWithCards(): void
    {
        $record = $this->record();
        $data = $this->view($record);
        $this->assertTrue($data['TabsLayout']);
        $this->assertSame('comments', $data['CurrentPage']->Segment, 'first page is the start');

        $cards = $data['RecordCards'];
        $this->assertSame(['state', 'basics', 'self'], $cards->column('Key'), 'notes excluded, secret hidden, empty card left out, status taken by the explicit card');
        $basics = $cards->find('Key', 'basics');
        $this->assertSame('Grunddaten', $basics->Title);
        $this->assertTrue($basics->Open);
        $this->assertStringContainsString('Gymnasium', (string) $basics->Content);
        $this->assertStringNotContainsString('Grade', (string) $basics->Content, 'empty field left out');
        $this->assertSame('Max · Gymnasium', $basics->Summary);
        $this->assertStringContainsString('record-cards/edit/' . $record->ID . '?page=basics', $basics->EditLink);
        $this->assertStringContainsString('BackURL=' . rawurlencode('record-cards/view/' . $record->ID . '/comments'), $basics->EditLink);

        $state = $cards->find('Key', 'state');
        $this->assertNotNull($state->EditLink, 'empty groups, but editable');
        $this->assertStringContainsString('phase', (string) $state->Content);

        $self = $cards->find('Key', 'self');
        $this->assertNull($self->EditLink);
        $this->assertStringContainsString('fdk-compact-record', (string) $self->Content);
        $this->assertStringContainsString('record-cards/view/' . $record->ID, (string) $self->Content);

        $this->assertSame('select', $data['RecordSwitchers']->first()->getDisplay(), 'switcher as select in tabs');
        $this->assertSame(['Akquise', 'Vorbereitung'], $data['HeaderStatus']->column('Title'));
    }

    public function testTabsWithoutCards(): void
    {
        $data = $this->view($this->record(), 'notes', fn ($c) => $c->withCards = false);
        $this->assertSame(0, $data['RecordCards']->count());
        $html = (string) $this->controller('GET')->customise($data)
            ->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordPageView');
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('aria-current="page">Notizen</a>', $html);
        $this->assertStringContainsString('data-fdk-switcher-select', $html, 'switcher column without cards');
        $this->assertStringNotContainsString('<details class="fdk-record-card', $html);
        $this->assertStringContainsString('badge-warning">offen</span>', $html);
    }

    public function testRenderedCardsRememberTheirState(): void
    {
        $data = $this->view($this->record());
        $html = (string) $this->controller('GET')->customise($data)
            ->renderWith('Atwx\\SilverstripeFrontdeskKit\\Includes\\RecordPageView');
        $this->assertStringContainsString('data-fdk-card="fdk-card:record-cards:basics"', $html);
        $this->assertStringContainsString('<option value="/b">Fall 2025</option>', $html);
        $this->assertStringContainsString('+ Fall</a>', $html);
    }

    public function testClosedGroupCardAndOwnSummary(): void
    {
        $pages = RecordPages::create()->layout('tabs')
            ->card(RecordCard::create('a', 'A')->groups(['x'])->open(fn ($r) => $r->Name === 'open')->summary(fn () => 'kurz'))
            ->groupCards(['y']);
        $layout = FieldLayout::create()->group('X', ['Name'], true, 'x')->group('Y', ['Note'], false, 'y');
        $cards = $pages->getCards($layout);
        $this->assertSame(['a', 'y'], array_map(fn ($c) => $c->getSegment(), $cards));
        $this->assertFalse($cards[1]->isOpenFor(RecordCardsTestModel::create()), 'closed like the group');
        $this->assertTrue($cards[0]->isOpenFor(RecordCardsTestModel::create(['Name' => 'open'])));
        $this->assertSame('kurz', $cards[0]->summaryFor(RecordCardsTestModel::create()));
        $this->assertSame($cards[1]->getSegment(), $pages->findEditable('y', $layout)?->getSegment());
    }

    public function testEditingACardOnlyChangesItsGroups(): void
    {
        $record = $this->record();
        $form = $this->controller('GET', ['ID' => $record->ID, 'Action' => 'edit'], ['page' => 'basics'])->EditForm();
        $this->assertNotNull($form->Fields()->dataFieldByName('School'));
        $this->assertNull($form->Fields()->dataFieldByName('Note'));

        $post = ['ID' => $record->ID, 'RecordPage' => 'basics', 'School' => 'Realschule', 'Note' => 'changed'];
        $controller = $this->controller('POST', ['Action' => 'EditForm'], [], $post);
        $form = $controller->EditForm();
        $form->loadDataFrom($post);
        $controller->save($post, $form);

        $record = RecordCardsTestModel::get()->byID($record->ID);
        $this->assertSame('Realschule', $record->School);
        $this->assertSame('old', $record->Note);
    }

    public function testUnknownLayoutIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecordPages::create()->layout('grid');
    }
}
