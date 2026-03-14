<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Column;
use Atwx\SilverstripeFrontdeskKit\ColumnCollection;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

class ColumnCollectionTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function makeRecord(array $fields = []): DataObject
    {
        return new class($fields) extends DataObject {
            private static string $table_name = 'FDK_CollectionTestRecord';
        };
    }

    // ─── make / addColumn ─────────────────────────────────────────────────────

    public function testMakeAddsColumnAndReturnsBuilder(): void
    {
        $collection = ColumnCollection::create();
        $builder = $collection->make('Title', 'My Title');

        $this->assertEquals(1, $collection->count());
        $this->assertSame($collection, $builder->end());
    }

    public function testMakeBuilderFluentChain(): void
    {
        $collection = ColumnCollection::create();
        $result = $collection
            ->make('Title', 'Title')->link('view/{ID}')->end()
            ->make('Status', 'Status')->end();

        $this->assertSame($collection, $result);
        $this->assertEquals(2, $collection->count());
        $this->assertEquals('view/{ID}', $collection->first()->getLinkPattern());
    }

    public function testAddColumnIsFluent(): void
    {
        $collection = ColumnCollection::create();
        $col = Column::create('Title', 'Title');

        $this->assertSame($collection, $collection->addColumn($col));
        $this->assertSame($col, $collection->first());
    }

    // ─── forExport ────────────────────────────────────────────────────────────

    public function testForExportFiltersHiddenColumns(): void
    {
        $collection = ColumnCollection::create()
            ->make('Title')->end()
            ->make('Status')->visibleInExport(false)->end();

        $export = $collection->forExport();

        $this->assertEquals(1, $export->count());
        $this->assertEquals('Title', $export->first()->getName());
    }

    public function testForExportReturnsNewInstance(): void
    {
        $collection = ColumnCollection::create()->make('Title')->end();
        $this->assertNotSame($collection, $collection->forExport());
    }

    public function testForExportIncludesAllWhenNoneHidden(): void
    {
        $collection = ColumnCollection::create()
            ->make('Title')->end()
            ->make('Status')->end();

        $this->assertEquals(2, $collection->forExport()->count());
    }

    // ─── renderFor ────────────────────────────────────────────────────────────

    public function testRenderForProducesOneRowPerColumn(): void
    {
        $record = $this->makeRecord();
        $collection = ColumnCollection::create()
            ->make('ID')->end()
            ->make('ID')->end();

        $this->assertEquals(2, $collection->renderFor($record)->count());
    }

    public function testRenderForSetsValueAndHasLink(): void
    {
        $record = $this->makeRecord();
        $record->ID = 5;
        // Use ID column — ID is always accessible on DataObject without a DB schema
        $collection = ColumnCollection::create()->make('ID')->link('view/{ID}')->end();

        $cell = $collection->renderFor($record)->first();

        $this->assertEquals('5', $cell->Value);
        $this->assertTrue((bool) $cell->HasLink);
        $this->assertEquals('view/5', $cell->Link);
    }

    public function testRenderForPrependsBaseUrlToRelativeLink(): void
    {
        $record = $this->makeRecord();
        $record->ID = 10;
        $collection = ColumnCollection::create()->make('ID')->link('view/{ID}')->end();

        $cell = $collection->renderFor($record, 'http://example.com/contacts/')->first();

        $this->assertStringStartsWith('http://example.com/contacts/', $cell->Link);
        $this->assertStringContainsString('10', $cell->Link);
    }

    public function testRenderForDoesNotPrependToAbsoluteLink(): void
    {
        $record = $this->makeRecord();
        $record->ID = 3;
        $collection = ColumnCollection::create()->make('ID')->link('https://ext.example.com/{ID}')->end();

        $cell = $collection->renderFor($record, 'http://example.com/')->first();

        $this->assertStringStartsWith('https://ext.example.com/', $cell->Link);
    }

    public function testRenderForDoesNotPrependToRootRelativeLink(): void
    {
        $record = $this->makeRecord();
        $record->ID = 3;
        $collection = ColumnCollection::create()->make('ID')->link('/absolute/{ID}')->end();

        $cell = $collection->renderFor($record, 'http://example.com/')->first();

        $this->assertStringStartsWith('/absolute/', $cell->Link);
    }

    public function testRenderForSetsIsFirstFlagOnlyOnFirstCell(): void
    {
        $record = $this->makeRecord();
        $collection = ColumnCollection::create()
            ->make('ID')->end()
            ->make('ID')->end();

        $cells = $collection->renderFor($record);

        $this->assertTrue((bool) $cells->first()->IsFirst);
        $this->assertFalse((bool) $cells->last()->IsFirst);
    }

    public function testRenderForSetsType(): void
    {
        $record = $this->makeRecord();
        $collection = ColumnCollection::create()->make('ID')->type('html')->end();

        $this->assertEquals('html', $collection->renderFor($record)->first()->Type);
    }

    public function testRenderForHasNoLinkWhenNoPattern(): void
    {
        $record = $this->makeRecord();
        $collection = ColumnCollection::create()->make('ID')->end();

        $cell = $collection->renderFor($record)->first();

        $this->assertFalse((bool) $cell->HasLink);
        $this->assertEmpty($cell->Link);
    }
}
