<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;

// ─── Test-Stub ────────────────────────────────────────────────────────────────
// TestFdkModel and TestFdkController are defined in FrontdeskControllerTest.php
// (same namespace, loaded first alphabetically by PHPUnit).

class TestFdkSubController extends FrontdeskSubController implements TestOnly
{
    private static string $managed_model = TestFdkModel::class;
    private static string $url_segment = 'sub-items';
    private static string $title = 'Sub Items';

    protected function getBaseQuery(): DataList
    {
        throw new \LogicException('getBaseQuery() must not be called in unit tests');
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

class FrontdeskSubControllerTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function makeParent(): TestFdkController
    {
        $parent = TestFdkController::create();
        $req    = new HTTPRequest('GET', '/test-items');
        $req->setSession(new Session([]));
        $parent->setRequest($req);
        return $parent;
    }

    private function makeSubController(int $parentId = 5): TestFdkSubController
    {
        $parent = $this->makeParent();

        $sub = TestFdkSubController::create();
        $req = new HTTPRequest('GET', '/test-items/sub-items/' . $parentId);
        $req->setSession(new Session([]));
        $sub->setRequest($req);
        $sub->setParentContext($parent, $parentId);
        return $sub;
    }

    // ─── setParentContext ─────────────────────────────────────────────────────

    public function testSetParentContextIsFluent(): void
    {
        $parent = $this->makeParent();

        $sub = TestFdkSubController::create();
        $req = new HTTPRequest('GET', '/test-items/sub-items/5');
        $req->setSession(new Session([]));
        $sub->setRequest($req);

        $result = $sub->setParentContext($parent, 5);
        $this->assertSame($sub, $result);
    }

    public function testGetParentIDAfterSetContext(): void
    {
        $sub = $this->makeSubController(7);
        $this->assertSame(7, $sub->getParentID());
    }

    // ─── Link ─────────────────────────────────────────────────────────────────

    public function testLinkBuildsCorrectUrl(): void
    {
        $sub = $this->makeSubController(5);
        $this->assertSame('test-items/sub-items/5', $sub->Link());
    }

    public function testLinkWithActionBuildsCorrectUrl(): void
    {
        $sub = $this->makeSubController(5);
        $this->assertSame('test-items/sub-items/5/edit', $sub->Link('edit'));
    }

    // ─── HtmxTarget ───────────────────────────────────────────────────────────

    public function testHtmxTarget(): void
    {
        $sub = $this->makeSubController();
        $this->assertSame('#fdk-sublist-sub-items', $sub->HtmxTarget());
    }

    // ─── Permissions ──────────────────────────────────────────────────────────

    public function testCanViewDelegatesToParentController(): void
    {
        $parent = $this->makeParent();
        $sub    = $this->makeSubController();
        // No logged-in user → both return false; key check is that sub delegates to parent
        $this->assertSame($parent->canView(), $sub->canView());
    }

    public function testCanEditDelegatesToParentController_False(): void
    {
        $sub = $this->makeSubController();
        // Parent's canEdit() defaults to false (editAllowed = false)
        $this->assertFalse($sub->canEdit());
    }

    public function testCanEditDelegatesToParentController_True(): void
    {
        $parent = $this->makeParent();
        $parent->setCanEdit(true);

        $sub = TestFdkSubController::create();
        $req = new HTTPRequest('GET', '/test-items/sub-items/5');
        $req->setSession(new Session([]));
        $sub->setRequest($req);
        $sub->setParentContext($parent, 5);

        $this->assertTrue($sub->canEdit());
    }
}
