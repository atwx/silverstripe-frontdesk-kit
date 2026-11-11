<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\View\RecordPage;
use Atwx\SilverstripeFrontdeskKit\View\RecordPages;
use Atwx\SilverstripeFrontdeskKit\View\RecordSwitcher;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class RecordPagesTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RecordPagesTestModel';
    private static array $db = ['Name' => 'Varchar', 'Note' => 'Varchar', 'Secret' => 'Varchar'];
}

class RecordPagesTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = RecordPagesTestModel::class;
    private static string $url_segment = 'record-pages';

    public bool $secretVisible = false;

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
            ->group('Basics', ['Name'], true, 'basics')
            ->group('Notes', ['Note'], true, 'notes')
            ->group('Secret', ['Secret'], true, 'secret')
            ->setLevels(['Secret' => $this->secretVisible ? FieldLayout::WRITE : FieldLayout::HIDDEN]);
    }

    protected function defineRecordPages(DataObject $record): ?RecordPages
    {
        return RecordPages::create()
            ->page(RecordPage::create('basics', 'Basics')->groups(['basics']))
            ->switcher(RecordSwitcher::create()->option('This', '#', true)->withAddLink('add'))
            ->page(RecordPage::create('notes', 'Notes')->groups(['notes'])->content(fn () => '<p>own content</p>'))
            ->page(RecordPage::create('secret', 'Secret')->groups(['secret']));
    }
}

class RecordPagesTest extends SapphireTest
{
    protected static $extra_dataobjects = [RecordPagesTestModel::class];

    private function record(): RecordPagesTestModel
    {
        $record = RecordPagesTestModel::create(['Name' => 'n', 'Note' => 'old', 'Secret' => 's']);
        $record->write();
        return $record;
    }

    private function controller(
        string $method,
        string $url,
        array $params = [],
        array $get = [],
        array $post = []
    ): RecordPagesTestController {
        $controller = RecordPagesTestController::create();
        $request = new HTTPRequest($method, $url, $get, $post);
        $request->setRouteParams($params);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $controller->setTestAction($params['Action'] ?? 'view');
        return $controller;
    }

    public function testFieldLayoutOnlyGroupsHidesOtherFields(): void
    {
        $layout = FieldLayout::create()
            ->group('A', ['One'], true, 'a')
            ->group('B', ['Two'], true, 'b')
            ->setLevels(['Two' => FieldLayout::READ])
            ->onlyGroups(['b']);

        $this->assertSame(FieldLayout::HIDDEN, $layout->levelFor('One'));
        $this->assertSame(FieldLayout::READ, $layout->levelFor('Two'));
        $this->assertSame(FieldLayout::HIDDEN, $layout->levelFor('Ungrouped'));
        $this->assertSame(['b'], array_column($layout->getGroups(), 'key'));
        $this->assertTrue($layout->hasFieldsIn(['b']));
        $this->assertFalse($layout->hasFieldsIn(['b'], true));
    }

    public function testGroupKeyDefaultsToTitle(): void
    {
        $layout = FieldLayout::create()->group('Title', ['One']);
        $this->assertSame('Title', $layout->getGroups()[0]['key']);
    }

    public function testFirstVisiblePageIsShownByDefault(): void
    {
        $record = $this->record();
        $data = $this->controller('GET', 'record-pages/view/' . $record->ID, ['ID' => $record->ID])
            ->view($this->controller('GET', 'x', ['ID' => $record->ID])->getRequest());

        $this->assertSame('basics', $data['CurrentPage']->Segment);
        $types = $data['RecordPages']->column('Type');
        $this->assertSame(['page', 'switcher', 'page'], $types, 'the page with only hidden fields is left out');
        $this->assertStringContainsString('record-pages/edit/' . $record->ID . '?page=basics', $data['CurrentPage']->EditLink);
    }

    public function testPageIsSelectedByOtherId(): void
    {
        $record = $this->record();
        $controller = $this->controller('GET', 'x', ['ID' => $record->ID, 'OtherID' => 'notes']);
        $data = $controller->view($controller->getRequest());

        $this->assertSame('notes', $data['CurrentPage']->Segment);
        $this->assertStringContainsString('own content', (string) $data['PageContent']);
        $this->assertStringContainsString('old', (string) $data['PageContent']);
        $this->assertStringNotContainsString('>n<', (string) $data['PageContent']);
    }

    public function testHiddenPageAnswers404(): void
    {
        $record = $this->record();
        $controller = $this->controller('GET', 'x', ['ID' => $record->ID, 'OtherID' => 'secret']);
        $this->expectException(HTTPResponse_Exception::class);
        $controller->view($controller->getRequest());
    }

    public function testHiddenPageIsShownWhenItsFieldsAreVisible(): void
    {
        $record = $this->record();
        $controller = $this->controller('GET', 'x', ['ID' => $record->ID, 'OtherID' => 'secret']);
        $controller->secretVisible = true;
        $data = $controller->view($controller->getRequest());
        $this->assertSame('secret', $data['CurrentPage']->Segment);
    }

    public function testEditFormOfAPageOnlyHoldsItsFields(): void
    {
        $record = $this->record();
        $form = $this->controller('GET', 'x', ['ID' => $record->ID, 'Action' => 'edit'], ['page' => 'notes'])
            ->EditForm();

        $this->assertNotNull($form->Fields()->dataFieldByName('Note'));
        $this->assertNull($form->Fields()->dataFieldByName('Name'));
        $this->assertSame('notes', $form->Fields()->dataFieldByName('RecordPage')->getValue());
    }

    public function testSavingAPageKeepsTheOtherFields(): void
    {
        $record = $this->record();
        $post = ['ID' => $record->ID, 'RecordPage' => 'notes', 'Note' => 'new', 'Name' => 'changed'];
        $controller = $this->controller('POST', 'record-pages/EditForm', ['Action' => 'EditForm'], [], $post);
        $form = $controller->EditForm();
        $form->loadDataFrom($post);
        $controller->save($post, $form);

        $record = RecordPagesTestModel::get()->byID($record->ID);
        $this->assertSame('new', $record->Note);
        $this->assertSame('n', $record->Name);
    }
}
