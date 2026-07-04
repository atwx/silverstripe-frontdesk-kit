<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Filter\TreeFilter;
use Atwx\SilverstripeFrontdeskKit\Forms\CheckboxTreeField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataList;

class TreeFilterTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testApplyToListSkipsEmptyJsonArray(): void
    {
        $list = $this->createMock(DataList::class);

        $filter = TreeFilter::create('Categories', 'Categories')
            ->apply(fn () => throw new \Exception('must not be called'));

        $this->assertSame($list, $filter->applyToList($list, '[]'));
    }

    public function testApplyToListSkipsNull(): void
    {
        $list = $this->createMock(DataList::class);

        $filter = TreeFilter::create('Categories', 'Categories')
            ->apply(fn () => throw new \Exception('must not be called'));

        $this->assertSame($list, $filter->applyToList($list, null));
    }

    public function testApplyToListSkipsInvalidJson(): void
    {
        $list = $this->createMock(DataList::class);

        $filter = TreeFilter::create('Categories', 'Categories')
            ->apply(fn () => throw new \Exception('must not be called'));

        $this->assertSame($list, $filter->applyToList($list, 'not json'));
    }

    public function testApplyToListDecodesJsonAndCallsApplyFn(): void
    {
        $input  = $this->createMock(DataList::class);
        $output = $this->createMock(DataList::class);
        $called = false;

        $filter = TreeFilter::create('Categories', 'Categories')
            ->apply(function ($l, $ids) use ($input, $output, &$called) {
                $called = true;
                $this->assertSame($input, $l);
                $this->assertEquals([3, 5, 8], $ids);
                return $output;
            });

        $result = $filter->applyToList($input, '[3,5,8]');

        $this->assertTrue($called);
        $this->assertSame($output, $result);
    }

    public function testApplyToListAcceptsArrayValue(): void
    {
        $input  = $this->createMock(DataList::class);
        $output = $this->createMock(DataList::class);

        $filter = TreeFilter::create('Categories', 'Categories')
            ->apply(function ($l, $ids) use ($output) {
                $this->assertEquals([1, 2], $ids);
                return $output;
            });

        $this->assertSame($output, $filter->applyToList($input, [1, 2]));
    }

    public function testRenderFieldReturnsCheckboxTreeField(): void
    {
        $field = TreeFilter::create('Categories', 'Categories')
            ->options([])
            ->renderField();

        $this->assertInstanceOf(CheckboxTreeField::class, $field);
        $this->assertEquals('Categories', $field->getName());
    }

    public function testOptionsCallableIsInvokedOnRenderField(): void
    {
        $invoked = false;
        TreeFilter::create('Categories', 'Categories')
            ->options(function () use (&$invoked) {
                $invoked = true;
                return [];
            })
            ->renderField();

        $this->assertTrue($invoked);
    }
}
