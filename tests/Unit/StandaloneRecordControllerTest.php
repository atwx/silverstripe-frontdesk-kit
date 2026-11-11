<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\StandaloneRecordController;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\Table\RecordAction;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\SecurityToken;

class StandaloneTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_StandaloneTestModel';
    private static array $db = ['Title' => 'Varchar', 'Text' => 'Text', 'Secret' => 'Varchar', 'State' => 'Varchar'];
}

class StandaloneTestController extends StandaloneRecordController implements TestOnly
{
    /** Only this key opens the record. */
    public static string $validKey = 'abc';

    /** @var array<int, array<int, string>> */
    public array $saved = [];

    public int $views = 0;

    protected function resolveRecord(HTTPRequest $request): ?DataObject
    {
        if ($request->param('Key') !== self::$validKey) {
            return null;
        }
        return StandaloneTestModel::get()->first();
    }

    protected function baseLink(): string
    {
        return 'standalone/' . self::$validKey;
    }

    protected function defineFieldLayout(DataObject $record): FieldLayout
    {
        return FieldLayout::create()
            ->group('Main', ['Title', 'Text'])
            ->setDefaultLevel(FieldLayout::HIDDEN)
            ->setLevels(['Title' => FieldLayout::READ, 'Text' => $record->State === 'locked' ? FieldLayout::READ : FieldLayout::WRITE]);
    }

    protected function defineRecordActions(DataObject $record): array
    {
        return [
            RecordAction::create('lock', 'Lock')->handle(function (DataObject $record) {
                $record->State = 'locked';
                $record->write();
                return 'Locked.';
            }),
        ];
    }

    protected function onView(DataObject $record): void
    {
        $this->views++;
    }

    protected function afterSave(DataObject $record, array $changed): void
    {
        $this->saved[] = $changed;
    }

    public function forRequest(string $method, string $key, array $post = []): static
    {
        $request = new HTTPRequest($method, 'standalone/' . $key, [], $post);
        $request->setRouteParams(['Key' => $key]);
        $request->setSession(new Session([]));
        $this->setRequest($request);
        return $this;
    }
}

class StandaloneRecordControllerTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [StandaloneTestModel::class];

    protected function setUp(): void
    {
        parent::setUp();
        SecurityToken::disable();
        StandaloneTestModel::create(['Title' => 'T', 'Text' => 'x', 'Secret' => 's', 'State' => 'open'])->write();
    }

    protected function tearDown(): void
    {
        SecurityToken::enable();
        parent::tearDown();
    }

    public function testUnknownKeyIs404(): void
    {
        $ctrl = StandaloneTestController::create()->forRequest('GET', 'wrong');
        try {
            $ctrl->index($ctrl->getRequest());
            $this->fail('Expected 404');
        } catch (HTTPResponse_Exception $e) {
            $this->assertSame(404, $e->getResponse()->getStatusCode());
        }
    }

    public function testPageShowsFormWithVisibleFields(): void
    {
        $ctrl = StandaloneTestController::create()->forRequest('GET', 'abc');
        $html = (string) $ctrl->index($ctrl->getRequest());

        $this->assertSame(1, $ctrl->views);
        $form = $ctrl->RecordForm();
        $this->assertNotNull($form);
        $this->assertSame(['Text'], array_keys($form->Fields()->saveableFields()));
        $this->assertNull($form->Fields()->dataFieldByName('Secret'));
        $this->assertStringContainsString('Lock', $html);
        $this->assertStringNotContainsString('>s<', $html);
    }

    public function testSaveOnlyWritesWritableFields(): void
    {
        $ctrl = StandaloneTestController::create()->forRequest('POST', 'abc');
        $form = $ctrl->RecordForm();
        $this->assertNotNull($form);
        $form->loadDataFrom(['Text' => 'neu', 'Title' => 'manipuliert', 'Secret' => 'manipuliert']);

        $ctrl->save(['Text' => 'neu'], $form);
        $record = StandaloneTestModel::get()->first();

        $this->assertSame('neu', $record->Text);
        $this->assertSame('T', $record->Title);
        $this->assertSame('s', $record->Secret);
        $this->assertSame([['Text']], $ctrl->saved);
        $this->assertSame('success', $ctrl->FlashMessage()?->Type);
    }

    public function testNoFormWithoutWritableFields(): void
    {
        $record = StandaloneTestModel::get()->first();
        $record->State = 'locked';
        $record->write();

        $ctrl = StandaloneTestController::create()->forRequest('GET', 'abc');
        $this->assertNull($ctrl->RecordForm());
        $html = (string) $ctrl->index($ctrl->getRequest());
        $this->assertStringContainsString('Main', $html, 'view groups instead of the form');
    }

    public function testRecordAction(): void
    {
        $ctrl = StandaloneTestController::create()->forRequest('POST', 'abc', ['RecordAction' => 'lock']);
        $response = $ctrl->recordaction($ctrl->getRequest());

        $this->assertTrue($response->isRedirect());
        $this->assertSame('locked', StandaloneTestModel::get()->first()->State);
        $this->assertSame('Locked.', $ctrl->FlashMessage()?->Text);
    }

    public function testResponsesAreNotCachedOrIndexed(): void
    {
        $ctrl = StandaloneTestController::create();
        $request = new HTTPRequest('GET', 'abc');
        $request->setRouteParams(['Key' => 'abc']);
        $request->setSession(new Session([]));
        $response = $ctrl->handleRequest($request);
        $this->assertStringContainsString('no-store', (string) $response->getHeader('Cache-Control'));
        $this->assertStringContainsString('noindex', (string) $response->getHeader('X-Robots-Tag'));
    }
}
