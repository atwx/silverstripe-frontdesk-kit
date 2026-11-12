<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\View\ViewRow;
use SilverStripe\Dev\SapphireTest;

class ViewRowTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testMultiLineTextKeepsLineBreaksAndIsEscaped(): void
    {
        $record = StandaloneTestModel::create(['Text' => "Butleigh Road\nStreet <b>x</b>", 'Title' => 'A & B']);

        $row = ViewRow::forField('Anschrift', $record, 'Text');
        $this->assertSame('html', $row->Type);
        $this->assertSame("Butleigh Road<br>\nStreet &lt;b&gt;x&lt;/b&gt;", $row->Value);

        $row = ViewRow::forField('Titel', $record, 'Title');
        $this->assertSame('text', $row->Type);
        $this->assertSame('A & B', $row->Value);
    }
}
