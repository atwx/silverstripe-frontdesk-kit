<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Forms\CollapsibleFieldGroup;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;

class FieldLayoutTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_FieldLayoutTestModel';
    private static array $db = ['Title' => 'Varchar', 'Venue' => 'Varchar', 'Note' => 'Text', 'Extra' => 'Varchar'];
}

class FieldLayoutTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function fields(): FieldList
    {
        return FieldList::create(
            TextField::create('Title'),
            TextField::create('Venue'),
            TextField::create('Note'),
            TextField::create('Extra'),
            HiddenField::create('ID')
        );
    }

    private function layout(): FieldLayout
    {
        return FieldLayout::create()
            ->group('Basics', ['Title', 'Venue'])
            ->group('Internal', ['Note'], false)
            ->setLevels(['Venue' => FieldLayout::READ, 'Note' => FieldLayout::HIDDEN]);
    }

    public function testLevels(): void
    {
        $layout = $this->layout();
        $this->assertSame('write', $layout->levelFor('Title'));
        $this->assertSame('read', $layout->levelFor('Venue'));
        $this->assertSame('hidden', $layout->levelFor('Note'));
        $this->assertSame('write', $layout->levelFor('Unknown'), 'default level');
        $this->assertTrue($layout->isWritable('Title'));
        $this->assertFalse($layout->isWritable('Venue'));
        $this->assertTrue($layout->isVisible('Venue'));
        $this->assertFalse($layout->isVisible('Note'));
        $layout->setDefaultLevel(FieldLayout::HIDDEN);
        $this->assertSame('hidden', $layout->levelFor('Unknown'));
        $this->assertSame('hidden', $layout->levelFor('Title'), 'Title has no explicit level');
    }

    public function testUnknownLevelIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldLayout::create()->setLevel('Title', 'admin');
    }

    public function testApplyGroupsHidesAndLocks(): void
    {
        $result = $this->layout()->applyTo($this->fields());

        $first = $result->first();
        $this->assertInstanceOf(CollapsibleFieldGroup::class, $first);
        $this->assertSame('Basics', $first->Title());
        $this->assertSame(['Title', 'Venue'], array_keys($first->FieldList()->dataFields()));
        $this->assertFalse($first->FieldList()->dataFieldByName('Title')->isReadonly());
        $this->assertTrue($first->FieldList()->dataFieldByName('Venue')->isReadonly());

        // "Internal" only holds the hidden field and is left out.
        $this->assertNull($result->dataFieldByName('Note'));
        $this->assertSame(['Title', 'Venue', 'Extra', 'ID'], array_keys($result->dataFields()));
        $this->assertCount(3, $result, 'group, ungrouped Extra, hidden ID');
    }

    public function testHiddenFormFieldsAreKept(): void
    {
        $result = FieldLayout::create()->setDefaultLevel(FieldLayout::HIDDEN)->applyTo($this->fields());
        $this->assertSame(['ID'], array_keys($result->dataFields()));
    }

    public function testReadonlyFieldsAreNotSaveable(): void
    {
        $result = $this->layout()->applyTo($this->fields());
        $this->assertSame(['Title', 'Extra', 'ID'], array_keys($result->saveableFields()));
    }

    public function testResetLockedChanges(): void
    {
        $record = FieldLayoutTestModel::create(
            ['ID' => 5, 'Title' => 'A', 'Venue' => 'Hall', 'Note' => 'secret', 'Extra' => 'x'],
            DataObject::CREATE_HYDRATED
        );
        $record->Title = 'B';
        $record->Venue = 'Elsewhere';
        $record->Note = 'changed';

        $reset = $this->layout()->resetLockedChanges($record);

        $this->assertSame(['Venue', 'Note'], $reset);
        $this->assertSame('B', $record->Title);
        $this->assertSame('Hall', $record->Venue);
        $this->assertSame('secret', $record->Note);
    }

    public function testResetCoversVirtualFields(): void
    {
        $record = FieldLayoutTestModel::create(['ID' => 5, 'Title' => 'A'], DataObject::CREATE_HYDRATED);
        // e.g. a form saving into a virtual field
        $record->Virtual_locked = 'x';
        $record->Virtual_open = 'y';

        $layout = FieldLayout::create()->setLevels([
            'Virtual_locked' => FieldLayout::READ,
            'Virtual_open' => FieldLayout::WRITE,
        ]);
        $this->assertSame(['Virtual_locked'], $layout->resetLockedChanges($record));
        $this->assertNull($record->getField('Virtual_locked'));
        $this->assertSame('y', $record->getField('Virtual_open'));
    }

    public function testViewGroups(): void
    {
        $rows = [];
        foreach (['Title', 'Venue', 'Note', 'Extra'] as $name) {
            $rows[$name] = ArrayData::create(['Label' => $name, 'Value' => 'v', 'Type' => 'text']);
        }
        $groups = $this->layout()->viewGroups($rows);

        $this->assertSame(['Basics', ''], $groups->column('Title'));
        $this->assertSame(['Title', 'Venue'], $groups->first()->Rows->column('Label'));
        $this->assertSame(['Extra'], $groups->last()->Rows->column('Label'));
    }
}
