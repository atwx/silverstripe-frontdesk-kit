<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\MultiSelectFilter;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\Export;
use Atwx\SilverstripeFrontdeskKit\Table\ListView;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * @property ?string $Name
 * @property ?string $Sports
 * @property int $Fee
 */
class MultiSelectSchool extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_MultiSelectSchool';
    private static array $db = ['Name' => 'Varchar', 'Sports' => 'Varchar', 'Fee' => 'Int'];
}

class MultiSelectSchoolController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = MultiSelectSchool::class;
    private static string $url_segment = 'ms-schools';

    public function canView($member = null): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->getQuery()->sort('Name')->column('Name');
    }

    /**
     * @return array{items: DataList<DataObject>, columns: ColumnCollection, fileName: string}|null
     */
    public function data(string $key): ?array
    {
        return $this->exportData($key);
    }

    protected function defineColumns(): ColumnCollection
    {
        $columns = ColumnCollection::create()->make('Name', 'Name')->end()->make('Fee', 'Fee')->end();
        foreach ((array) $this->filterValue('Sports') as $sport) {
            $columns->make('Rating_' . $sport, 'Rating ' . $sport)
                ->format(fn ($value, $record) => str_contains((string) $record->Sports, $sport) ? 'yes' : 'no')
                ->end();
        }
        return $columns;
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()
            ->add(
                MultiSelectFilter::create('Sports', 'Sportarten')
                    ->options(['tennis' => 'Tennis', 'golf' => 'Golf', 'rowing' => 'Rudern'])
                    ->field('Sports:PartialMatch')
                    ->choosableMatch()
            )
            ->add(
                MultiSelectFilter::create('Fees', 'Gebühr')
                    ->options(['low' => 'niedrig', 'high' => 'hoch'])
                    ->apply(fn (DataList $list, array $values, bool $all) => $all
                        ? $list->filter('ID', 0)
                        : $list->filter($values === ['low'] ? 'Fee:LessThan' : 'Fee:GreaterThanOrEqual', 1000))
            );
    }

    protected function defineListViews(): array
    {
        return [ListView::create('cheap', 'Günstig')->query(fn (DataList $list) => $list->filter('Fee:LessThan', 1000))];
    }

    protected function defineExports(): array
    {
        return [
            Export::create('fees', 'Fees 2027')->fileName('Fees 2027')->columns(['Fee', 'Name']),
            Export::create('years', 'Gebühren je Jahr')
                ->columns(function () {
                    $columns = ColumnCollection::create()->make('Name', 'Name')->end();
                    foreach ([2026, 2027] as $year) {
                        $columns->make('Fee' . $year, 'Fee ' . $year)
                            ->format(fn ($value, $record) => (string) ($record->Fee + $year - 2026))
                            ->end();
                    }
                    return $columns;
                })
                ->query(fn (DataList $list) => $list->filter('Fee:GreaterThan', 0)),
            Export::create('cheap-only', 'Nur günstige')->views(['cheap']),
        ];
    }
}

class MultiSelectExportTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [MultiSelectSchool::class];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['Alpha', 'tennis,golf', 500],
            ['Beta', 'tennis', 1500],
            ['Gamma', 'golf,rowing', 0],
        ] as [$name, $sports, $fee]) {
            MultiSelectSchool::create(['Name' => $name, 'Sports' => $sports, 'Fee' => $fee])->write();
        }
    }

    /**
     * @param array<string, string> $get
     */
    private function controller(array $get = []): MultiSelectSchoolController
    {
        $controller = MultiSelectSchoolController::create();
        $request = new HTTPRequest('GET', 'ms-schools', $get);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        return $controller;
    }

    public function testAnyOrAllOfTheChosenOptions(): void
    {
        $chosen = json_encode(['tennis', 'golf']);
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->controller(['Sports' => $chosen])->names(), 'OR');
        $this->assertSame(
            ['Alpha'],
            $this->controller(['Sports' => $chosen, 'Sports_Match' => 'all'])->names(),
            'AND'
        );
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->controller(['Sports' => '[]'])->names(), 'nothing chosen');
        $this->assertSame(['Gamma'], $this->controller(['Sports' => 'rowing'])->names(), 'single value (old view)');
        $form = $this->controller(['Sports' => 'rowing'])->FilterForm();
        $this->assertSame('["rowing"]', $form?->Fields()->dataFieldByName('Sports')?->FieldValue());
        $this->assertSame(
            ['Alpha', 'Gamma'],
            $this->controller(['Fees' => json_encode(['low'])])->names(),
            'apply callable gets the values'
        );
        $this->assertSame(
            ['Alpha', 'Gamma'],
            $this->controller(['Fees' => json_encode(['low']), 'Fees_Match' => 'all'])->names(),
            'not choosable: Fees_Match ignored'
        );
    }

    public function testFieldsAndDynamicColumns(): void
    {
        $controller = $this->controller(['Sports' => json_encode(['golf']), 'Sports_Match' => 'all']);
        $form = $controller->FilterForm();
        $this->assertNotNull($form);
        $group = $form->Fields()->fieldByName('Sports_Group');
        $this->assertInstanceOf(FieldGroup::class, $group);
        $this->assertSame('all', $form->Fields()->dataFieldByName('Sports_Match')?->getValue());
        $this->assertStringContainsString('"value":"tennis"', $form->Fields()->dataFieldByName('Sports')->Field());

        $names = array_map(fn ($c) => $c->getName(), $controller->Columns()->toArray());
        $this->assertSame(['Name', 'Fee', 'Rating_golf'], $names, 'column per chosen sport');
    }

    public function testAdditionalExportsWithOwnColumnsFileNameAndViews(): void
    {
        $controller = $this->controller();
        $titles = $controller->Actions()->column('Title');
        $this->assertContains('Fees 2027', $titles);
        $this->assertNotContains('Nur günstige', $titles, 'only in the view cheap');
        $this->assertContains('Nur günstige', $this->controller(['view' => 'cheap'])->Actions()->column('Title'));
        $this->assertNull($controller->data('cheap-only'));
        $this->assertNull($controller->data('unknown'));

        $fees = $this->controller(['Sports' => json_encode(['tennis'])])->data('fees');
        $this->assertNotNull($fees);
        $this->assertSame('Fees 2027.xlsx', $fees['fileName']);
        $this->assertSame(['Fee', 'Name'], array_map(fn ($c) => $c->getName(), $fees['columns']->toArray()));
        $this->assertSame(['Alpha', 'Beta'], $fees['items']->sort('Name')->column('Name'), 'filtered as on screen');

        $years = $controller->data('years');
        $this->assertNotNull($years);
        $this->assertSame(['Name', 'Fee2026', 'Fee2027'], array_map(fn ($c) => $c->getName(), $years['columns']->toArray()));
        $this->assertSame(['Alpha', 'Beta'], $years['items']->sort('Name')->column('Name'), 'own query');
        $beta = MultiSelectSchool::get()->find('Name', 'Beta');
        $this->assertSame('1501', (string) $years['columns']->byName('Fee2027')?->renderValue($beta));
        $this->assertMatchesRegularExpression('/^export-years-\d{4}-\d{2}-\d{2}\.xlsx$/', $years['fileName']);
    }
}
