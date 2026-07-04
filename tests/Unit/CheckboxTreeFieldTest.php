<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Forms\CheckboxTreeField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;

class CheckboxTreeFieldTest extends SapphireTest
{
    protected $usesDatabase = false;

    protected function makeNode(int $id, string $title, array $children = []): ArrayData
    {
        return new class ($id, $title, $children) extends ArrayData {
            private array $childNodes;

            public function __construct(int $id, string $title, array $children)
            {
                parent::__construct(['ID' => $id, 'Title' => $title]);
                $this->childNodes = $children;
            }

            public function Children(): ArrayList
            {
                return ArrayList::create($this->childNodes);
            }
        };
    }

    public function testItemsJSONBuildsNestedStructure(): void
    {
        $items = [
            $this->makeNode(1, 'Root', [
                $this->makeNode(2, 'Child'),
            ]),
        ];

        $field = CheckboxTreeField::create('Tree', 'Tree', $items);
        $decoded = json_decode($field->ItemsJSON(), true);

        $this->assertCount(1, $decoded);
        $this->assertEquals(1, $decoded[0]['value']);
        $this->assertEquals('Root', $decoded[0]['name']);
        $this->assertCount(1, $decoded[0]['children']);
        $this->assertEquals('Child', $decoded[0]['children'][0]['name']);
        $this->assertEquals([], $decoded[0]['children'][0]['children']);
    }

    public function testFieldValueEncodesArrayAsJson(): void
    {
        $field = CheckboxTreeField::create('Tree', 'Tree', []);
        $field->setValue([3, 5]);

        $this->assertEquals('[3,5]', $field->FieldValue());
    }

    public function testFieldValuePassesStringThrough(): void
    {
        $field = CheckboxTreeField::create('Tree', 'Tree', []);
        $field->setValue('[7,9]');

        $this->assertEquals('[7,9]', $field->FieldValue());
    }

    public function testFieldValueDefaultsToEmptyJsonArray(): void
    {
        $field = CheckboxTreeField::create('Tree', 'Tree', []);

        $this->assertEquals('[]', $field->FieldValue());
    }
}
