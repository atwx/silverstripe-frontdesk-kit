<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 * @property string $Note
 * @property bool $Active
 * @property ?string $SentOn
 * @property string $Locked
 */
class BulkEditTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_BulkEditTestModel';
    private static array $db = [
        'Title' => 'Varchar',
        'Note' => 'Varchar',
        'Active' => 'Boolean',
        'SentOn' => 'Date',
        'Locked' => 'Varchar',
    ];
}

class BulkEditTestController extends FrontdeskSubController implements TestOnly
{
    private static string $managed_model = BulkEditTestModel::class;
    private static string $url_segment = 'bulk-edit';
    private static array $bulk_edit_fields = ['Note', 'Active', 'SentOn', 'Locked'];

    public function canEdit($member = null): bool
    {
        return true;
    }

    protected function getBaseQuery(): DataList
    {
        return BulkEditTestModel::get();
    }

    protected function defineFieldLayout(?DataObject $record): ?FieldLayout
    {
        return FieldLayout::create()->setLevels(['Locked' => FieldLayout::READ]);
    }
}

class SubControllerBulkEditTest extends SapphireTest
{
    protected static $extra_dataobjects = [BulkEditTestModel::class];

    public function testOnlyFilledFieldsAreAppliedToTheChosenEntries(): void
    {
        $a = BulkEditTestModel::create(['Title' => 'A', 'Note' => 'a', 'Active' => true, 'Locked' => 'keep']);
        $a->write();
        $b = BulkEditTestModel::create(['Title' => 'B', 'Note' => 'b', 'Active' => true]);
        $b->write();
        $c = BulkEditTestModel::create(['Title' => 'C', 'Note' => 'c', 'Active' => true]);
        $c->write();

        $sub = $this->sub();
        $this->assertTrue($sub->canBulkEdit());
        $form = $sub->BulkEditForm();
        $chosen = array_map('intval', (array) $form->Fields()->dataFieldByName('BulkEntries')?->getValue());
        $this->assertSame([$a->ID, $b->ID, $c->ID], $chosen, 'all chosen');
        $active = $form->Fields()->dataFieldByName('Active');
        $this->assertInstanceOf(DropdownField::class, $active, 'checkbox with three states');
        $this->assertNull($form->Fields()->dataFieldByName('Title'), 'only the bulk edit fields');

        // Note empty (unchanged), Active "no", date set, locked field submitted anyway.
        $post = [
            'BulkEntries' => [$a->ID, $b->ID],
            'Note' => '',
            'Active' => '0',
            'SentOn' => '2026-11-20',
            'Locked' => 'x',
        ];
        $form->loadDataFrom($post);
        $sub->doBulkEdit($post, $form);

        foreach ([$a, $b] as $record) {
            $record = BulkEditTestModel::get()->byID($record->ID);
            $this->assertFalse((bool) $record->Active);
            $this->assertSame('2026-11-20', $record->SentOn);
        }
        $this->assertSame('a', BulkEditTestModel::get()->byID($a->ID)->Note, 'empty field leaves the value');
        $this->assertSame('keep', BulkEditTestModel::get()->byID($a->ID)->Locked, 'locked by the layout');
        $this->assertTrue((bool) BulkEditTestModel::get()->byID($c->ID)->Active, 'not chosen');
        $this->assertSame('2 entries changed.', $sub->getRequest()->getSession()->get('FrontdeskFlash')['Text'] ?? null);
    }

    private function sub(): BulkEditTestController
    {
        $parent = TestFdkController::create();
        $sub = BulkEditTestController::create();
        $request = new HTTPRequest('POST', 'test-items/bulk-edit/1/BulkEditForm');
        $request->setSession(new Session([]));
        $parent->setRequest($request);
        $sub->setRequest($request);
        $sub->setParentContext($parent, 1);
        return $sub;
    }
}
