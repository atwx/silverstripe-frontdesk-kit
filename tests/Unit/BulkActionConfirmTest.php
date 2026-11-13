<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Table\BulkAction;
use SilverStripe\Dev\SapphireTest;

class BulkActionConfirmTest extends SapphireTest
{
    public function testConfirmTextReachesTheChoices(): void
    {
        $action = BulkAction::create('delete', 'Löschen', fn () => null)->confirm('Wirklich löschen?');
        $this->assertSame('Wirklich löschen?', $action->getConfirm());
        $this->assertSame(['Wirklich löschen?'], $action->Choices()->column('Confirm'));
        $plain = BulkAction::create('done', 'Erledigt', fn () => null)->options(['a' => 'A', 'b' => 'B']);
        $this->assertSame(['', ''], $plain->Choices()->column('Confirm'), 'no confirmation');
    }
}
