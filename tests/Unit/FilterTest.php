<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\DateRangeFilter;
use Atwx\SilverstripeFrontdeskKit\SelectFilter;
use Atwx\SilverstripeFrontdeskKit\TextFilter;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataList;

class FilterTest extends SapphireTest
{
    protected $usesDatabase = false;

    // ─── Base: getters ────────────────────────────────────────────────────────

    public function testGetters(): void
    {
        $filter = TextFilter::create('MyField', 'My Label');

        $this->assertEquals('MyField', $filter->getName());
        $this->assertEquals('My Label', $filter->getLabel());
    }

    public function testApplyIsFluent(): void
    {
        $filter = TextFilter::create('Q', 'Q');
        $this->assertSame($filter, $filter->apply(fn ($l, $v) => $l));
    }

    // ─── applyToList ──────────────────────────────────────────────────────────

    public function testApplyToListSkipsEmptyString(): void
    {
        $list = $this->createMock(DataList::class);
        $list->expects($this->never())->method($this->anything());

        $filter = TextFilter::create('Q', 'Q')
            ->apply(fn () => throw new \Exception('must not be called'));

        $this->assertSame($list, $filter->applyToList($list, ''));
    }

    public function testApplyToListSkipsNull(): void
    {
        $list = $this->createMock(DataList::class);

        $filter = TextFilter::create('Q', 'Q')
            ->apply(fn () => throw new \Exception('must not be called'));

        $this->assertSame($list, $filter->applyToList($list, null));
    }

    public function testApplyToListAcceptsZeroString(): void
    {
        $list = $this->createMock(DataList::class);
        $called = false;

        $filter = TextFilter::create('Q', 'Q')
            ->apply(function ($l, $v) use ($list, &$called) {
                $called = true;
                $this->assertEquals('0', $v);
                return $list;
            });

        $filter->applyToList($list, '0');

        $this->assertTrue($called, 'applyFn must be called for the string "0"');
    }

    public function testApplyToListCallsApplyFnAndReturnsResult(): void
    {
        $input  = $this->createMock(DataList::class);
        $output = $this->createMock(DataList::class);
        $called = false;

        $filter = TextFilter::create('Q', 'Q')
            ->apply(function ($l, $v) use ($input, $output, &$called) {
                $called = true;
                $this->assertSame($input, $l);
                $this->assertEquals('foo', $v);
                return $output;
            });

        $result = $filter->applyToList($input, 'foo');

        $this->assertTrue($called);
        $this->assertSame($output, $result);
    }

    public function testApplyToListReturnsUnmodifiedListWithNoApplyFn(): void
    {
        $list = $this->createMock(DataList::class);
        $filter = TextFilter::create('Q', 'Q');

        $this->assertSame($list, $filter->applyToList($list, 'anything'));
    }

    // ─── TextFilter ───────────────────────────────────────────────────────────

    public function testTextFilterRenderFieldReturnsTextField(): void
    {
        $field = TextFilter::create('Query', 'Search')->renderField();

        $this->assertInstanceOf(\SilverStripe\Forms\TextField::class, $field);
        $this->assertEquals('Query', $field->getName());
        $this->assertEquals('Search', $field->Title());
    }

    // ─── SelectFilter ─────────────────────────────────────────────────────────

    public function testSelectFilterRenderFieldReturnsDropdown(): void
    {
        $field = SelectFilter::create('Status', 'Status')
            ->options(['a' => 'A'])
            ->renderField();

        $this->assertInstanceOf(\SilverStripe\Forms\DropdownField::class, $field);
        $this->assertEquals('Status', $field->getName());
    }

    public function testSelectFilterOptionsArrayPassedToField(): void
    {
        $opts = ['foo' => 'Foo', 'bar' => 'Bar'];
        $field = SelectFilter::create('Type', 'Type')->options($opts)->renderField();

        $source = $field->getSource();
        $this->assertArrayHasKey('foo', $source);
        $this->assertArrayHasKey('bar', $source);
    }

    public function testSelectFilterOptionsCallableIsInvokedOnRenderField(): void
    {
        $invoked = false;
        $filter = SelectFilter::create('Status', 'Status')
            ->options(function () use (&$invoked) {
                $invoked = true;
                return ['a' => 'A'];
            });

        $filter->renderField();

        $this->assertTrue($invoked);
    }

    // ─── DateRangeFilter ──────────────────────────────────────────────────────

    public function testDateRangeFilterRenderFieldHasTwoDateFields(): void
    {
        $group = DateRangeFilter::create('Created', 'Created')->renderField();

        $children = $group->FieldList();
        $this->assertEquals(2, $children->count());
        $this->assertInstanceOf(\SilverStripe\Forms\DateField::class, $children->first());
        $this->assertInstanceOf(\SilverStripe\Forms\DateField::class, $children->last());
    }

    public function testDateRangeFilterFieldNamesHaveSuffix(): void
    {
        $group = DateRangeFilter::create('Created', 'Created')->renderField();
        $children = $group->FieldList();

        $this->assertEquals('Created_From', $children->first()->getName());
        $this->assertEquals('Created_To', $children->last()->getName());
    }

    public function testDateRangeFilterApplyRangeCallsApplyFn(): void
    {
        $input  = $this->createMock(DataList::class);
        $output = $this->createMock(DataList::class);
        $called = false;

        $filter = DateRangeFilter::create('Created', 'Created')
            ->apply(function ($l, $range) use ($input, $output, &$called) {
                $called = true;
                $this->assertEquals('2025-01-01', $range['from']);
                $this->assertEquals('2025-12-31', $range['to']);
                return $output;
            });

        $result = $filter->applyRange($input, '2025-01-01', '2025-12-31');

        $this->assertTrue($called);
        $this->assertSame($output, $result);
    }

    public function testDateRangeFilterApplyRangeWithNoFnReturnsUnmodifiedList(): void
    {
        $list = $this->createMock(DataList::class);
        $filter = DateRangeFilter::create('Created', 'Created');

        $this->assertSame($list, $filter->applyRange($list, '2025-01-01', null));
    }
}
