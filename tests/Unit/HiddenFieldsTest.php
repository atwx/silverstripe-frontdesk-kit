<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class HiddenFieldsParent extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_HiddenFieldsParent';
    private static array $db = ['Title' => 'Varchar'];
}

/**
 * @property string $Title
 * @property string $LegacyID
 * @property int $ParentID
 */
class HiddenFieldsChild extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_HiddenFieldsChild';
    private static array $db = ['Title' => 'Varchar', 'LegacyID' => 'Varchar', 'Note' => 'Varchar'];
    private static array $has_one = ['Parent' => HiddenFieldsParent::class, 'Other' => HiddenFieldsParent::class];
    private static array $fdk_hidden_fields = ['LegacyID', 'Other'];
}

class HiddenFieldsController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = HiddenFieldsChild::class;
    private static string $url_segment = 'hidden-children';

    public function canEdit($member = null): bool
    {
        return true;
    }

    /**
     * @return array<string, \SilverStripe\Model\ArrayData>
     */
    public function rows(DataObject $record): array
    {
        return $this->viewFieldRows($record);
    }
}

class HiddenFieldsSubController extends FrontdeskSubController implements TestOnly
{
    private static string $managed_model = HiddenFieldsChild::class;
    private static string $url_segment = 'children';
    private static ?string $parent_field = 'ParentID';

    public function canEdit($member = null): bool
    {
        return true;
    }

    protected function getBaseQuery(): DataList
    {
        return HiddenFieldsChild::get();
    }

    /**
     * @return array<string, \SilverStripe\Model\ArrayData>
     */
    public function rows(DataObject $record): array
    {
        return $this->viewFieldRows($record);
    }
}

class HiddenFieldsTest extends SapphireTest
{
    protected static $extra_dataobjects = [HiddenFieldsParent::class, HiddenFieldsChild::class];

    private function request(): HTTPRequest
    {
        $request = new HTTPRequest('GET', 'x');
        $request->setSession(new Session([]));
        return $request;
    }

    public function testConfiguredFieldsAreLeftOutOfFormAndView(): void
    {
        $parent = HiddenFieldsParent::create(['Title' => 'P']);
        $parent->write();
        $child = HiddenFieldsChild::create(['Title' => 'C', 'LegacyID' => 'L1', 'ParentID' => $parent->ID, 'OtherID' => $parent->ID]);
        $child->write();
        $controller = HiddenFieldsController::create();
        $controller->setRequest($this->request());

        $fields = $controller->EditForm()->Fields();
        $this->assertNull($fields->dataFieldByName('LegacyID'));
        $this->assertNull($fields->dataFieldByName('OtherID'));
        $this->assertNotNull($fields->dataFieldByName('ParentID'));

        $rows = $controller->rows($child);
        $this->assertArrayNotHasKey('LegacyID', $rows);
        $this->assertArrayNotHasKey('OtherID', $rows);
        $this->assertArrayHasKey('ParentID', $rows);
    }

    public function testSubControllerLeavesOutTheParentField(): void
    {
        $parent = HiddenFieldsParent::create(['Title' => 'P']);
        $parent->write();
        $child = HiddenFieldsChild::create(['Title' => 'C', 'ParentID' => $parent->ID]);
        $child->write();
        $main = HiddenFieldsController::create();
        $main->setRequest($this->request());
        $sub = HiddenFieldsSubController::create();
        $sub->setRequest($this->request());
        $sub->setParentContext($main, (int) $parent->ID);

        $fields = $sub->EditForm()->Fields();
        $this->assertNull($fields->dataFieldByName('ParentID'));
        $this->assertNotNull($fields->dataFieldByName('Note'));
        $this->assertArrayNotHasKey('ParentID', $sub->rows($child));
    }
}
