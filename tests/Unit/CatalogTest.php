<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Catalog\CatalogEntry;
use Atwx\SilverstripeFrontdeskKit\Catalog\CatalogFields;
use Atwx\SilverstripeFrontdeskKit\Catalog\CatalogFilter;
use Atwx\SilverstripeFrontdeskKit\Catalog\CatalogMultiField;
use Atwx\SilverstripeFrontdeskKit\Catalog\Catalogs;
use Atwx\SilverstripeFrontdeskKit\Controller\CatalogController;
use Atwx\SilverstripeFrontdeskKit\Forms\SearchableDropdownField;
use Atwx\SilverstripeFrontdeskKit\Table\Column;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 * @property int $SchoolTypeID
 * @method CatalogEntry SchoolType()
 * @method \SilverStripe\ORM\ManyManyList<CatalogEntry> Sports()
 */
class CatalogTestRecord extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_CatalogTestRecord';
    private static array $db = ['Title' => 'Varchar'];
    private static array $has_one = ['SchoolType' => CatalogEntry::class];
    private static array $many_many = ['Sports' => CatalogEntry::class];
    private static array $catalog_fields = ['SchoolType' => 'school_type', 'Sports' => 'sports'];
}

/**
 * @property string $PortalText
 */
class CatalogTestReaction extends CatalogEntry implements TestOnly
{
    private static string $table_name = 'FDK_CatalogTestReaction';
    private static ?string $catalog = 'reaction';
    private static array $db = ['PortalText' => 'Varchar'];
}

class CatalogTestController extends CatalogController implements TestOnly
{
    public function setTestAction(string $action): void
    {
        $this->action = $action;
    }
}

class CatalogTest extends SapphireTest
{
    protected static $extra_dataobjects = [CatalogTestRecord::class, CatalogTestReaction::class];

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('de_DE');
        Catalogs::config()->set('catalogs', [
            'school_type' => ['title' => 'Schultyp'],
            'sports' => ['title' => 'Sportarten'],
            'reaction' => ['title' => 'Reaktion', 'class' => CatalogTestReaction::class],
        ]);
    }

    private function entry(string $catalog, string $title, array $values = []): CatalogEntry
    {
        $entry = CatalogEntry::create(array_merge(['Catalog' => $catalog, 'Title' => $title], $values));
        $entry->write();
        return $entry;
    }

    public function testCodeIsGeneratedUniqueAndLocked(): void
    {
        $a = $this->entry('sports', 'Field Hockey');
        $b = $this->entry('sports', 'Field Hockey');
        $c = $this->entry('school_type', 'Field Hockey');
        $this->assertSame('field_hockey', $a->Code);
        $this->assertSame('field_hockey_2', $b->Code);
        $this->assertSame('field_hockey', $c->Code, 'codes are unique per catalog');

        $a->Code = 'other';
        $this->expectException(ValidationException::class);
        $a->write();
    }

    public function testDuplicateOrInvalidCodeIsRefused(): void
    {
        $this->entry('sports', 'Tennis', ['Code' => 'tennis']);
        $this->expectException(ValidationException::class);
        $this->entry('sports', 'Tennis 2', ['Code' => 'tennis']);
    }

    public function testUnknownCatalogIsRefused(): void
    {
        $this->expectException(ValidationException::class);
        $this->entry('nope', 'X');
    }

    public function testOnlyOneDefaultPerCatalog(): void
    {
        $a = $this->entry('school_type', 'Boarding', ['IsDefault' => true]);
        $b = $this->entry('school_type', 'Prep', ['IsDefault' => true]);
        $this->assertFalse((bool) CatalogEntry::get()->byID($a->ID)->IsDefault);
        $this->assertSame($b->ID, Catalogs::singleton()->defaultEntry('school_type')?->ID);
    }

    public function testFormFieldsOfferActiveEntriesAndKeepCurrentInactiveOnes(): void
    {
        $boarding = $this->entry('school_type', 'Boarding', ['IsDefault' => true]);
        $summer = $this->entry('school_type', 'Summer', ['Active' => false]);
        $hockey = $this->entry('sports', 'Hockey');
        $polo = $this->entry('sports', 'Polo', ['Active' => false]);

        $fields = CatalogFields::apply(FieldList::create(), CatalogTestRecord::singleton());
        $type = $fields->dataFieldByName('SchoolTypeID');
        $this->assertInstanceOf(SearchableDropdownField::class, $type);
        $this->assertSame([$boarding->ID => 'Boarding'], $type->getSource());
        $this->assertSame($boarding->ID, (int) $type->getValue(), 'new records get the default');
        $sports = $fields->dataFieldByName('Sports');
        $this->assertInstanceOf(CatalogMultiField::class, $sports);
        $this->assertStringNotContainsString('Polo', $sports->ItemsJSON());

        $record = CatalogTestRecord::create(['SchoolTypeID' => $summer->ID]);
        $record->write();
        $record->Sports()->add($polo);
        $fields = CatalogFields::apply(FieldList::create(), $record);
        $this->assertSame(
            [$boarding->ID => 'Boarding', $summer->ID => 'Summer (inaktiv)'],
            $fields->dataFieldByName('SchoolTypeID')->getSource()
        );
        $this->assertStringContainsString('Polo (inaktiv)', $fields->dataFieldByName('Sports')->ItemsJSON());
        $this->assertSame([$polo->ID], $fields->dataFieldByName('Sports')->selectedIDs());
        $this->assertNotNull($hockey);
    }

    public function testMultiFieldSavesOnlyOfferedEntries(): void
    {
        $hockey = $this->entry('sports', 'Hockey');
        $tennis = $this->entry('sports', 'Tennis');
        $polo = $this->entry('sports', 'Polo', ['Active' => false]);
        $record = CatalogTestRecord::create();
        $record->write();

        $field = CatalogFields::apply(FieldList::create(), $record)->dataFieldByName('Sports');
        $field->setValue(json_encode([$hockey->ID, $tennis->ID]));
        $this->assertTrue($field->validate()->isValid());
        $field->saveInto($record);
        $this->assertEqualsCanonicalizing([$hockey->ID, $tennis->ID], $record->Sports()->column('ID'));

        $field = CatalogFields::apply(FieldList::create(), $record)->dataFieldByName('Sports');
        $field->setValue(json_encode([$hockey->ID, $polo->ID]));
        $this->assertFalse($field->validate()->isValid(), 'an inactive entry cannot be added');
    }

    public function testEditFormLoadsTheCurrentEntries(): void
    {
        $hockey = $this->entry('sports', 'Hockey');
        $record = CatalogTestRecord::create();
        $record->write();
        $record->Sports()->add($hockey);

        $fields = CatalogFields::apply(FieldList::create(), $record);
        $form = Form::create(null, 'Form', $fields, FieldList::create());
        $form->loadDataFrom($record);
        $field = $form->Fields()->dataFieldByName('Sports');
        $this->assertSame([(int) $hockey->ID], $field->selectedIDs(), 'loadDataFrom keeps the relation');
        $this->assertSame('[' . $hockey->ID . ']', $field->FieldValue());
    }

    public function testEntriesInUseCannotBeDeleted(): void
    {
        $type = $this->entry('school_type', 'Boarding');
        $hockey = $this->entry('sports', 'Hockey');
        $unused = $this->entry('sports', 'Curling');
        $record = CatalogTestRecord::create(['SchoolTypeID' => $type->ID]);
        $record->write();
        $record->Sports()->add($hockey);

        $this->assertTrue($type->isInUse());
        $this->assertTrue($hockey->isInUse());
        $this->assertFalse($unused->isInUse());
        $this->logInWithPermission('ADMIN');
        $this->assertFalse($hockey->canDelete());
        $this->assertTrue($unused->canDelete());

        $unused->delete();
        $this->expectException(ValidationException::class);
        $hockey->delete();
    }

    public function testFilterAndColumn(): void
    {
        $boarding = $this->entry('school_type', 'Boarding');
        $hockey = $this->entry('sports', 'Hockey');
        $tennis = $this->entry('sports', 'Tennis', ['Active' => false]);
        $a = CatalogTestRecord::create(['Title' => 'A', 'SchoolTypeID' => $boarding->ID]);
        $a->write();
        $a->Sports()->addMany([$hockey, $tennis]);
        $b = CatalogTestRecord::create(['Title' => 'B']);
        $b->write();

        $list = CatalogTestRecord::get();
        $this->assertSame(['A'], CatalogFilter::create('SchoolType', 'Typ', 'school_type')
            ->applyToList($list, $boarding->ID)->column('Title'));
        $this->assertSame(['A'], CatalogFilter::create('Sports', 'Sport', 'sports')
            ->applyToList($list, $tennis->ID)->column('Title'));
        $this->assertArrayHasKey($tennis->ID, CatalogFilter::create('Sports', 'Sport', 'sports')->renderField()->getSource());

        $this->assertSame('Hockey, Tennis (inaktiv)', (string) (new Column('Sports'))->renderValue($a));
    }

    public function testSyncByCodeUsesTheCatalogClass(): void
    {
        $catalogs = Catalogs::singleton();
        $entries = $catalogs->sync('reaction', [
            'yes' => ['Title' => 'Yes', 'PortalText' => 'Die Schule hat zugesagt'],
            'no' => 'No',
        ]);
        $this->assertInstanceOf(CatalogTestReaction::class, $entries['yes']);
        $this->assertSame('Die Schule hat zugesagt', $entries['yes']->PortalText);

        $catalogs->sync('reaction', ['no' => 'No (full)']);
        $this->assertSame(2, CatalogEntry::get()->filter('Catalog', 'reaction')->count());
        $this->assertSame('No (full)', $catalogs->byCode('reaction', 'no')?->Title);
    }

    public function testControllerAddsEntriesAsTheCatalogClass(): void
    {
        $controller = CatalogTestController::create();
        $request = new HTTPRequest('GET', 'catalogs/add', ['Catalog' => 'reaction']);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $controller->setTestAction('add');
        $this->assertSame(CatalogTestReaction::class, $controller->getManagedModel());

        $controller->setTestAction('index');
        $this->assertSame(CatalogEntry::class, $controller->getManagedModel());
    }

    public function testEmptyHasOneValidates(): void
    {
        $this->entry('school_type', 'Boarding');
        $record = CatalogTestRecord::create();
        $record->write();
        $field = CatalogFields::apply(FieldList::create(), $record)->dataFieldByName('SchoolTypeID');
        $field->setValue(0);
        $this->assertTrue($field->validate()->isValid(), 'an empty has_one (0) is "none"');
        $field->setValue(999);
        $this->assertFalse($field->validate()->isValid());
    }
}
