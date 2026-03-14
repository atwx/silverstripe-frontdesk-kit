<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Column;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

class ColumnTest extends SapphireTest
{
    protected $usesDatabase = false;

    // ─── Constructor / getters ────────────────────────────────────────────────

    public function testConstructorSetsNameAndLabel(): void
    {
        $col = Column::create('Title', 'My Label');

        $this->assertEquals('Title', $col->getName());
        $this->assertEquals('My Label', $col->getLabel());
    }

    public function testLabelDefaultsToName(): void
    {
        $col = Column::create('Status');

        $this->assertEquals('Status', $col->getLabel());
    }

    public function testTemplateAccessors(): void
    {
        $col = Column::create('Title', 'My Label');

        $this->assertEquals('My Label', $col->Title());
        $this->assertEquals('Title', $col->Field());
    }

    // ─── Fluent setters ───────────────────────────────────────────────────────

    public function testLabelIsFluent(): void
    {
        $col = Column::create('Title', 'Old');
        $this->assertSame($col, $col->label('New'), 'label() must return $this');
        $this->assertEquals('New', $col->getLabel());
    }

    public function testLinkIsFluent(): void
    {
        $col = Column::create('Title');
        $this->assertNull($col->getLinkPattern());
        $this->assertSame($col, $col->link('view/{ID}'));
        $this->assertEquals('view/{ID}', $col->getLinkPattern());
    }

    public function testSortableDefaultsFalse(): void
    {
        $this->assertFalse(Column::create('Title')->isSortable());
    }

    public function testSortableIsFluent(): void
    {
        $col = Column::create('Title');
        $col->sortable();
        $this->assertTrue($col->isSortable());
        $col->sortable(false);
        $this->assertFalse($col->isSortable());
    }

    public function testTypeDefaultsToText(): void
    {
        $this->assertEquals('text', Column::create('Title')->getType());
    }

    public function testTypeIsFluent(): void
    {
        $col = Column::create('Title');
        $this->assertSame($col, $col->type('html'));
        $this->assertEquals('html', $col->getType());
    }

    public function testVisibleInExportDefaultsTrue(): void
    {
        $this->assertTrue(Column::create('Title')->isVisibleInExport());
    }

    public function testVisibleInExportIsFluent(): void
    {
        $col = Column::create('Title');
        $this->assertSame($col, $col->visibleInExport(false));
        $this->assertFalse($col->isVisibleInExport());
    }

    // ─── renderValue ──────────────────────────────────────────────────────────

    /**
     * Return an anonymous DataObject subclass populated with $fields.
     * The stub exposes customMethod() and slug() as explicit PHP methods so
     * hasMethod() recognises them without any mocking.
     */
    private function makeRecord(array $fields = []): DataObject
    {
        return new class($fields) extends DataObject {
            // No table needed — we never write this record.
            private static string $table_name = 'FDK_ColumnTestRecord';

            public function customMethod(): string
            {
                return 'from_method';
            }

            public function slug(): string
            {
                return 'my-slug';
            }
        };
    }

    public function testRenderValueCallsMethodWhenAvailable(): void
    {
        $record = $this->makeRecord();
        $this->assertEquals('from_method', Column::create('customMethod')->renderValue($record));
    }

    public function testRenderValueAppliesCustomFormatter(): void
    {
        // Formatter receives the raw value returned by the field/method accessor
        $record = $this->makeRecord();
        $col = Column::create('customMethod')->format(fn ($v) => strtoupper($v));
        $this->assertEquals('FROM_METHOD', $col->renderValue($record));
    }

    public function testRenderValueFormatterReceivesRecordAsSecondArg(): void
    {
        $record = $this->makeRecord();
        // Formatter combines value from one method with a result from another method call
        $col = Column::create('customMethod')->format(fn ($v, $r) => $v . ':' . $r->slug());
        $this->assertEquals('from_method:my-slug', $col->renderValue($record));
    }

    // ─── renderLink ───────────────────────────────────────────────────────────

    public function testRenderLinkReturnsEmptyWithoutPattern(): void
    {
        $record = $this->makeRecord();
        $this->assertEquals('', Column::create('customMethod')->renderLink($record));
    }

    public function testRenderLinkInterpolatesIdPlaceholder(): void
    {
        $record = $this->makeRecord();
        $record->ID = 42;
        $this->assertEquals('view/42', Column::create('customMethod')->link('view/{ID}')->renderLink($record));
    }

    public function testRenderLinkInterpolatesMultiplePlaceholders(): void
    {
        $record = $this->makeRecord();
        $record->ID = 7;
        $link = Column::create('customMethod')->link('{ID}/method/{customMethod}')->renderLink($record);
        $this->assertEquals('7/method/from_method', $link);
    }

    public function testRenderLinkInterpolatesFromMethod(): void
    {
        $record = $this->makeRecord();
        $link = Column::create('customMethod')->link('view/{slug}')->renderLink($record);
        $this->assertEquals('view/my-slug', $link);
    }
}
