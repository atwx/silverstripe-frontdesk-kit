<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Table\ActionException;
use Atwx\SilverstripeFrontdeskKit\Table\RecordAction;
use Atwx\SilverstripeFrontdeskKit\Table\TimelineEntry;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

class RecordActionTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testAccessors(): void
    {
        $action = RecordAction::create('status', 'Change status')
            ->options(['review' => 'Review', 2 => 'Two'])
            ->withComment('Comment', true)
            ->description('Pick one');

        $this->assertSame('status', $action->getName());
        $this->assertSame('Change status', $action->getLabel());
        $this->assertSame(['review' => 'Review', '2' => 'Two'], $action->getOptions());
        $this->assertTrue($action->hasOptions());
        $this->assertSame('Comment', $action->getCommentLabel());
        $this->assertTrue($action->isCommentRequired());
        $this->assertSame('Change status', $action->getSubmitLabel(), 'defaults to label');
        $this->assertSame('Pick one', $action->getDescription());
    }

    public function testInvalidNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RecordAction::create('no spaces', 'x');
    }

    public function testRunPassesOptionAndTrimmedComment(): void
    {
        $calls = [];
        $action = RecordAction::create('status', 'Change')
            ->options(['a' => 'A'])
            ->withComment('Comment')
            ->handle(function (DataObject $record, ?string $option, string $comment) use (&$calls) {
                $calls[] = [$option, $comment];
                return 'done';
            });

        $this->assertSame('done', $action->run(DataObject::create(), 'a', '  hello '));
        $this->assertSame([['a', 'hello']], $calls);
    }

    public function testRunRejectsUnknownOrMissingOption(): void
    {
        $action = RecordAction::create('status', 'Change')->options(['a' => 'A']);
        foreach (['b', null] as $option) {
            try {
                $action->run(DataObject::create(), $option, '');
                $this->fail('Expected ActionException');
            } catch (ActionException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRunRequiresComment(): void
    {
        $this->expectException(ActionException::class);
        RecordAction::create('note', 'Note')->withComment('Text', true)->run(DataObject::create(), null, '   ');
    }

    public function testWithoutOptionsTheOptionIsIgnored(): void
    {
        $received = 'unset';
        RecordAction::create('confirm', 'Confirm')
            ->handle(function ($record, $option) use (&$received) {
                $received = $option;
                return null;
            })
            ->run(DataObject::create(), 'smuggled', '');
        $this->assertNull($received);
    }

    public function testTimelineEntry(): void
    {
        $entry = TimelineEntry::create('2026-11-11 10:30:00', 'Status changed', 'Text', 'Andy', 'Status Change!');
        $this->assertSame('Status changed', $entry->Title);
        $this->assertSame('statuschange', $entry->Kind);
        $this->assertSame(strtotime('2026-11-11 10:30:00'), $entry->Timestamp);
        $this->assertSame('11.11.2026', $entry->Date->Format('dd.MM.yyyy'));
    }
}
