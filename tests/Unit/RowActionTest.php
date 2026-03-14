<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\RowAction;
use SilverStripe\Dev\SapphireTest;

class RowActionTest extends SapphireTest
{
    protected $usesDatabase = false;

    // ─── Static factories ─────────────────────────────────────────────────────

    public function testLinkFactory(): void
    {
        $action = RowAction::link('View', 'http://example.com/view/1');

        $this->assertEquals('View', $action->getLabel());
        $this->assertEquals('http://example.com/view/1', $action->getUrl());
        $this->assertFalse($action->isDeleteAction());
        $this->assertFalse($action->isHtmxAction());
        $this->assertNull($action->getConfirmMessage());
    }

    public function testDeleteFactorySetsFlags(): void
    {
        $action = RowAction::delete('http://example.com/delete/1');

        $this->assertTrue($action->isDeleteAction());
        $this->assertFalse($action->isHtmxAction());
        $this->assertNotEmpty($action->getLabel());
        $this->assertNotNull($action->getConfirmMessage(), 'Delete action should have a default confirm message');
        $this->assertTrue($action->HasConfirm());
    }

    public function testHtmxFactory(): void
    {
        $action = RowAction::htmx('Approve', 'http://example.com/approve/1', 'post');

        $this->assertTrue($action->isHtmxAction());
        $this->assertFalse($action->isDeleteAction());
        $this->assertEquals('post', $action->getMethod());
        $this->assertEquals('Approve', $action->getLabel());
    }

    public function testHtmxFactoryDefaultsToGet(): void
    {
        $action = RowAction::htmx('Do', 'http://example.com/do');

        $this->assertEquals('get', $action->getMethod());
    }

    // ─── Fluent modifiers ─────────────────────────────────────────────────────

    public function testWithIcon(): void
    {
        $action = RowAction::link('Edit', '/edit');
        $result = $action->withIcon('pencil');

        $this->assertSame($action, $result, 'withIcon() must be fluent');
        $this->assertEquals('pencil', $action->getIcon());
        $this->assertTrue($action->HasIcon());
    }

    public function testWithConfirm(): void
    {
        $action = RowAction::link('Delete', '/delete');
        $result = $action->withConfirm('Really?');

        $this->assertSame($action, $result, 'withConfirm() must be fluent');
        $this->assertEquals('Really?', $action->getConfirmMessage());
        $this->assertTrue($action->HasConfirm());
    }

    public function testEnabledWithBoolTrue(): void
    {
        $action = RowAction::link('View', '/view');
        $action->enabled(true);

        $this->assertTrue($action->isEnabled());
    }

    public function testEnabledWithBoolFalse(): void
    {
        $action = RowAction::link('View', '/view');
        $action->enabled(false);

        $this->assertFalse($action->isEnabled());
    }

    public function testEnabledWithCallable(): void
    {
        $action = RowAction::link('View', '/view');
        $action->enabled(fn () => true);
        $this->assertTrue($action->isEnabled());

        $action->enabled(fn () => false);
        $this->assertFalse($action->isEnabled());
    }

    public function testIsEnabledDefaultsTrue(): void
    {
        $action = RowAction::link('View', '/view');

        $this->assertTrue($action->isEnabled());
    }

    // ─── Template accessors ───────────────────────────────────────────────────

    public function testTemplateAccessorsMatchGetters(): void
    {
        $action = RowAction::link('Edit', '/edit/1');
        $action->withIcon('pencil')->withConfirm('Sure?');

        $this->assertEquals($action->getLabel(), $action->Label());
        $this->assertEquals($action->getUrl(), $action->Url());
        $this->assertEquals($action->getIcon(), $action->Icon());
        $this->assertEquals($action->getConfirmMessage(), $action->ConfirmMessage());
        $this->assertEquals($action->isDeleteAction(), $action->IsDelete());
        $this->assertEquals($action->isHtmxAction(), $action->IsHtmx());
        $this->assertEquals($action->getMethod(), $action->Method());
        $this->assertEquals($action->HasConfirm(), $action->HasIcon());
    }

    public function testHasIconFalseByDefault(): void
    {
        $action = RowAction::link('View', '/view');

        $this->assertFalse($action->HasIcon());
        $this->assertEquals('', $action->getIcon());
    }

    public function testHasConfirmFalseByDefault(): void
    {
        $action = RowAction::link('View', '/view');

        $this->assertFalse($action->HasConfirm());
    }
}
