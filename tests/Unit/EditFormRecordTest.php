<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class EditFormRecordTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_EditFormRecordTestModel';
    private static array $db = ['State' => 'Varchar', 'Text' => 'Varchar'];
}

/**
 * "Text" is only writable for records in state "open" (like a field that
 * becomes writable in a later status); new records are "draft".
 */
class EditFormRecordTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = EditFormRecordTestModel::class;
    private static string $url_segment = 'editform-records';

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
        $open = $record && $record->State === 'open';
        return FieldLayout::create()
            ->group('Main', ['State', 'Text'])
            ->setLevels(['State' => FieldLayout::READ, 'Text' => $open ? FieldLayout::WRITE : FieldLayout::READ]);
    }
}

class EditFormRecordTest extends SapphireTest
{
    protected static $extra_dataobjects = [EditFormRecordTestModel::class];

    private function controller(string $method, string $action, array $post = []): EditFormRecordTestController
    {
        $controller = EditFormRecordTestController::create();
        $request = new HTTPRequest($method, 'editform-records/' . $action, [], $post);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $controller->setTestAction($action);
        return $controller;
    }

    public function testSubmittedFormIsBuiltForTheSubmittedRecord(): void
    {
        $record = EditFormRecordTestModel::create(['State' => 'open', 'Text' => 'old']);
        $record->write();

        $form = $this->controller('POST', 'EditForm', ['ID' => $record->ID, 'Text' => 'new'])->EditForm();

        $this->assertFalse($form->Fields()->dataFieldByName('Text')->isReadonly());
        $form->loadDataFrom(['Text' => 'new']);
        $form->saveInto($record);
        $this->assertSame('new', $record->Text);
    }

    public function testSubmittedFormKeepsReadOnlyFieldsOfTheSubmittedRecord(): void
    {
        $record = EditFormRecordTestModel::create(['State' => 'draft', 'Text' => 'old']);
        $record->write();

        $form = $this->controller('POST', 'EditForm', ['ID' => $record->ID, 'Text' => 'new'])->EditForm();

        $this->assertTrue($form->Fields()->dataFieldByName('Text')->isReadonly());
    }

    public function testGetOnEditFormIgnoresId(): void
    {
        $record = EditFormRecordTestModel::create(['State' => 'open']);
        $record->write();

        $form = $this->controller('GET', 'EditForm', [])->EditForm();

        // Without a record the form is built for a new (draft) record.
        $this->assertTrue($form->Fields()->dataFieldByName('Text')->isReadonly());
    }
}
