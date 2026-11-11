<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use Atwx\SilverstripeFrontdeskKit\Forms\SearchableDropdownField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\SearchableDropdownField as CmsSearchableDropdownField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class RelationFieldsOwner extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RelationFieldsOwner';
    private static array $db = ['Name' => 'Varchar'];
    private static array $has_one = ['Contact' => RelationFieldsContact::class];
    private static array $has_many = [
        'Notes' => RelationFieldsNote::class,
        'Tasks' => RelationFieldsTask::class,
    ];
    private static array $field_labels = ['Contact' => 'Ansprechpartner', 'Tasks' => 'Aufgaben'];
}

class RelationFieldsContact extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RelationFieldsContact';
    private static array $db = ['FirstName' => 'Varchar', 'Surname' => 'Varchar'];

    public function getTitle(): string
    {
        return $this->FirstName . ' ' . $this->Surname;
    }
}

class RelationFieldsNote extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RelationFieldsNote';
    private static array $has_one = ['Owner' => RelationFieldsOwner::class];
}

class RelationFieldsTask extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_RelationFieldsTask';
    private static array $has_one = ['Owner' => RelationFieldsOwner::class];
}

class RelationFieldsNoteSubController extends FrontdeskSubController implements TestOnly
{
    private static string $managed_model = RelationFieldsNote::class;
    private static string $url_segment = 'notes';
    private static string $parent_field = 'OwnerID';

    protected function getBaseQuery(): DataList
    {
        return RelationFieldsNote::get()->filter('OwnerID', $this->getParentID());
    }
}

class RelationFieldsController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = RelationFieldsOwner::class;
    private static string $url_segment = 'relation-owners';
    private static array $sub_controllers = [RelationFieldsNoteSubController::class];

    public function rows(DataObject $record): array
    {
        return $this->viewFieldRows($record);
    }
}

class RelationFieldsTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        RelationFieldsOwner::class,
        RelationFieldsContact::class,
        RelationFieldsNote::class,
        RelationFieldsTask::class,
    ];

    private function owner(): RelationFieldsOwner
    {
        $contact = RelationFieldsContact::create(['FirstName' => 'Ada', 'Surname' => 'Lovelace']);
        $contact->write();
        $owner = RelationFieldsOwner::create(['Name' => 'Owner', 'ContactID' => $contact->ID]);
        $owner->write();
        RelationFieldsNote::create(['OwnerID' => $owner->ID])->write();
        RelationFieldsTask::create(['OwnerID' => $owner->ID])->write();
        RelationFieldsTask::create(['OwnerID' => $owner->ID])->write();
        return $owner;
    }

    public function testHasOneRowShowsTheTitleOfTheRelatedRecord(): void
    {
        $rows = RelationFieldsController::create()->rows($this->owner());

        $this->assertSame('Ansprechpartner', $rows['ContactID']->Label);
        $this->assertSame('Ada Lovelace', $rows['ContactID']->Value);
    }

    public function testHasManyRowsSkipRelationsListedBySubControllers(): void
    {
        $rows = RelationFieldsController::create()->rows($this->owner());

        $this->assertArrayNotHasKey('Notes', $rows);
        $this->assertSame('Aufgaben', $rows['Tasks']->Label);
        $this->assertSame('2', $rows['Tasks']->Value);
    }

    public function testScaffoldedCmsDropdownsAreReplaced(): void
    {
        $owner = $this->owner();
        $fields = $owner->scaffoldFormFields();
        $this->assertInstanceOf(CmsSearchableDropdownField::class, $fields->dataFieldByName('ContactID'));
        $fields->setValues($owner->toMap());

        SearchableDropdownField::replaceCmsFields($fields);

        $field = $fields->dataFieldByName('ContactID');
        $this->assertInstanceOf(SearchableDropdownField::class, $field);
        $this->assertSame($owner->ContactID, (int) $field->getValue());
        $this->assertSame('Ada Lovelace', $field->getSource()[$owner->ContactID] ?? null);
        $this->assertSame('Ada Lovelace', $field->getSelectedLabel());
    }

    public function testOtherFieldsAreKept(): void
    {
        $fields = FieldList::create(CmsSearchableDropdownField::create('ContactID', 'Contact', RelationFieldsContact::get()));
        $fields->push($name = \SilverStripe\Forms\TextField::create('Name'));

        SearchableDropdownField::replaceCmsFields($fields);

        $this->assertSame($name, $fields->dataFieldByName('Name'));
    }
}
