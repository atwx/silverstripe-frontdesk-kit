<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Table\Column;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnBuilder;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use SilverStripe\Dev\SapphireTest;

class ColumnBuilderTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function makeBuilder(): array
    {
        $collection = ColumnCollection::create();
        $column = Column::create('Title', 'Title');
        $builder = new ColumnBuilder($column, $collection);
        return [$builder, $column, $collection];
    }

    // ─── end() ────────────────────────────────────────────────────────────────

    public function testEndReturnsCollection(): void
    {
        [$builder, , $collection] = $this->makeBuilder();
        $this->assertSame($collection, $builder->end());
    }

    // ─── link() ───────────────────────────────────────────────────────────────

    public function testLinkIsFluent(): void
    {
        [$builder] = $this->makeBuilder();
        $this->assertSame($builder, $builder->link('view/{ID}'));
    }

    public function testLinkDelegatesToColumn(): void
    {
        [$builder, $column] = $this->makeBuilder();
        $builder->link('view/{ID}');
        $this->assertEquals('view/{ID}', $column->getLinkPattern());
    }

    // ─── label() ──────────────────────────────────────────────────────────────

    public function testLabelIsFluent(): void
    {
        [$builder] = $this->makeBuilder();
        $this->assertSame($builder, $builder->label('New Label'));
    }

    public function testLabelDelegatesToColumn(): void
    {
        [$builder, $column] = $this->makeBuilder();
        $builder->label('New Label');
        $this->assertEquals('New Label', $column->getLabel());
    }

    // ─── sortable() ───────────────────────────────────────────────────────────

    public function testSortableIsFluent(): void
    {
        [$builder] = $this->makeBuilder();
        $this->assertSame($builder, $builder->sortable());
    }

    public function testSortableDelegatesToColumn(): void
    {
        [$builder, $column] = $this->makeBuilder();
        $builder->sortable(true);
        $this->assertTrue($column->isSortable());
    }

    public function testSortableFalseDelegatesToColumn(): void
    {
        [$builder, $column] = $this->makeBuilder();
        $builder->sortable(false);
        $this->assertFalse($column->isSortable());
    }

    // ─── type() ───────────────────────────────────────────────────────────────

    public function testTypeIsFluent(): void
    {
        [$builder] = $this->makeBuilder();
        $this->assertSame($builder, $builder->type('html'));
    }

    public function testTypeDelegatesToColumn(): void
    {
        [$builder, $column] = $this->makeBuilder();
        $builder->type('html');
        $this->assertEquals('html', $column->getType());
    }

    // ─── format() ─────────────────────────────────────────────────────────────

    public function testFormatIsFluent(): void
    {
        [$builder] = $this->makeBuilder();
        $this->assertSame($builder, $builder->format(fn ($v) => $v));
    }

    public function testFormatDelegatesToColumn(): void
    {
        [$builder, $column] = $this->makeBuilder();
        $builder->format(fn ($v) => strtoupper((string) $v));

        // Verify the formatter was applied by rendering a value on a stub record
        $record = new class extends \SilverStripe\ORM\DataObject {
            private static string $table_name = 'FDK_BuilderTestRecord';
            public function Title(): string { return 'hello'; }
        };
        $this->assertEquals('HELLO', $column->renderValue($record));
    }

    // ─── visibleInExport() ────────────────────────────────────────────────────

    public function testVisibleInExportIsFluent(): void
    {
        [$builder] = $this->makeBuilder();
        $this->assertSame($builder, $builder->visibleInExport(false));
    }

    public function testVisibleInExportDelegatesToColumn(): void
    {
        [$builder, $column] = $this->makeBuilder();
        $builder->visibleInExport(false);
        $this->assertFalse($column->isVisibleInExport());
    }
}
