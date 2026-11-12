<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 * @property string $Locked
 */
class SubSaveTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_SubSaveTestModel';
    private static array $db = ['Title' => 'Varchar', 'Locked' => 'Varchar'];
}

class SubSaveTestController extends FrontdeskSubController implements TestOnly
{
    private static string $managed_model = SubSaveTestModel::class;
    private static string $url_segment = 'sub-save';

    public function canEdit($member = null): bool
    {
        return true;
    }

    public function setTestAction(string $action): void
    {
        $this->action = $action;
    }

    protected function getBaseQuery(): DataList
    {
        return SubSaveTestModel::get();
    }

    protected function defineFieldLayout(?DataObject $record): ?FieldLayout
    {
        return FieldLayout::create()->setLevels(['Locked' => FieldLayout::READ]);
    }
}

class SubControllerSaveTest extends SapphireTest
{
    protected static $extra_dataobjects = [SubSaveTestModel::class];

    public function testSaveResetsChangesToLockedFields(): void
    {
        $record = SubSaveTestModel::create(['Title' => 'old', 'Locked' => 'keep']);
        $record->write();
        $post = ['ID' => $record->ID, 'Title' => 'new', 'Locked' => 'changed'];

        $parent = TestFdkController::create();
        $sub = SubSaveTestController::create();
        $request = new HTTPRequest('POST', 'test-items/sub-save/1/EditForm', [], $post);
        $request->setSession(new Session([]));
        $parent->setRequest($request);
        $sub->setRequest($request);
        $sub->setParentContext($parent, 1);
        $sub->setTestAction('EditForm');

        $form = $sub->EditForm();
        // A manipulated POST: the read-only field is submitted anyway.
        $form->loadDataFrom($post);
        $form->Fields()->push(\SilverStripe\Forms\TextField::create('Locked'));
        $form->loadDataFrom($post);
        $sub->save($post, $form);

        $record = SubSaveTestModel::get()->byID($record->ID);
        $this->assertSame('new', $record->Title);
        $this->assertSame('keep', $record->Locked);
    }
}
