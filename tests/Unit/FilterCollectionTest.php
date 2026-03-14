<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\DateRangeFilter;
use Atwx\SilverstripeFrontdeskKit\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\TextFilter;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataList;

class FilterCollectionTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function makeList(): DataList
    {
        return $this->createMock(DataList::class);
    }

    private function request(array $vars = []): HTTPRequest
    {
        return new HTTPRequest('GET', '/', $vars);
    }

    // ─── add / getFilters ─────────────────────────────────────────────────────

    public function testAddIsFluent(): void
    {
        $c = FilterCollection::create();
        $f = TextFilter::create('A', 'A');
        $this->assertSame($c, $c->add($f));
    }

    public function testAddedFiltersAreReturned(): void
    {
        $f1 = TextFilter::create('A', 'A');
        $f2 = TextFilter::create('B', 'B');
        $c = FilterCollection::create()->add($f1)->add($f2);

        $this->assertSame([$f1, $f2], $c->getFilters());
    }

    // ─── toFieldList ──────────────────────────────────────────────────────────

    public function testToFieldListContainsOneFieldPerFilter(): void
    {
        $c = FilterCollection::create()
            ->add(TextFilter::create('A', 'A'))
            ->add(TextFilter::create('B', 'B'));

        $this->assertEquals(2, $c->toFieldList()->count());
    }

    // ─── isActive ─────────────────────────────────────────────────────────────

    public function testIsActiveReturnsFalseForEmptyRequest(): void
    {
        $c = FilterCollection::create()->add(TextFilter::create('Q', 'Q'));
        $this->assertFalse($c->isActive($this->request()));
    }

    public function testIsActiveReturnsTrueWhenFilterHasValue(): void
    {
        $c = FilterCollection::create()->add(TextFilter::create('Q', 'Q'));
        $this->assertTrue($c->isActive($this->request(['Q' => 'hello'])));
    }

    public function testIsActiveIgnoresEmptyStringValue(): void
    {
        $c = FilterCollection::create()->add(TextFilter::create('Q', 'Q'));
        $this->assertFalse($c->isActive($this->request(['Q' => ''])));
    }

    public function testIsActiveReturnsTrueForDateRangeFrom(): void
    {
        $c = FilterCollection::create()->add(DateRangeFilter::create('Created', 'Created'));
        $this->assertTrue($c->isActive($this->request(['Created_From' => '2025-01-01'])));
    }

    public function testIsActiveReturnsTrueForDateRangeTo(): void
    {
        $c = FilterCollection::create()->add(DateRangeFilter::create('Created', 'Created'));
        $this->assertTrue($c->isActive($this->request(['Created_To' => '2025-12-31'])));
    }

    public function testIsActiveReturnsFalseForEmptyDateRange(): void
    {
        $c = FilterCollection::create()->add(DateRangeFilter::create('Created', 'Created'));
        $this->assertFalse($c->isActive($this->request()));
    }

    // ─── applyAll ─────────────────────────────────────────────────────────────

    public function testApplyAllCallsFilterFnForMatchingVar(): void
    {
        $input  = $this->makeList();
        $output = $this->makeList();
        $called = false;

        $c = FilterCollection::create()
            ->add(TextFilter::create('Q', 'Q')
                ->apply(function ($l, $v) use ($input, $output, &$called) {
                    $called = true;
                    $this->assertSame($input, $l);
                    $this->assertEquals('hello', $v);
                    return $output;
                }));

        $result = $c->applyAll($input, $this->request(['Q' => 'hello']));

        $this->assertTrue($called);
        $this->assertSame($output, $result);
    }

    public function testApplyAllSkipsFilterWithNoValue(): void
    {
        $list = $this->makeList();
        $c = FilterCollection::create()
            ->add(TextFilter::create('Q', 'Q')
                ->apply(fn () => throw new \Exception('must not be called')));

        $this->assertSame($list, $c->applyAll($list, $this->request()));
    }

    public function testApplyAllChainsFiltersInOrder(): void
    {
        $l1 = $this->makeList();
        $l2 = $this->makeList();
        $l3 = $this->makeList();

        $c = FilterCollection::create()
            ->add(TextFilter::create('A', 'A')->apply(fn ($l, $v) => $l2))
            ->add(TextFilter::create('B', 'B')->apply(fn ($l, $v) => $l3));

        $result = $c->applyAll($l1, $this->request(['A' => 'x', 'B' => 'y']));

        $this->assertSame($l3, $result, 'Filters must be chained: output of first feeds into second');
    }

    public function testApplyAllRoutesDateRangeToApplyRange(): void
    {
        $input  = $this->makeList();
        $output = $this->makeList();
        $called = false;

        $c = FilterCollection::create()
            ->add(DateRangeFilter::create('Created', 'Created')
                ->apply(function ($l, $range) use ($input, $output, &$called) {
                    $called = true;
                    $this->assertEquals('2025-01-01', $range['from']);
                    $this->assertEquals('2025-12-31', $range['to']);
                    return $output;
                }));

        $result = $c->applyAll($input, $this->request([
            'Created_From' => '2025-01-01',
            'Created_To'   => '2025-12-31',
        ]));

        $this->assertTrue($called);
        $this->assertSame($output, $result);
    }

    public function testApplyAllSkipsDateRangeWithNoValues(): void
    {
        $list = $this->makeList();
        $c = FilterCollection::create()
            ->add(DateRangeFilter::create('Created', 'Created')
                ->apply(fn () => throw new \Exception('must not be called')));

        $this->assertSame($list, $c->applyAll($list, $this->request()));
    }
}
