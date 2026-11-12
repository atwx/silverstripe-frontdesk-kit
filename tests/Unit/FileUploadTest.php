<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Controller\StandaloneRecordController;
use Atwx\SilverstripeFrontdeskKit\Forms\FieldLayout;
use Atwx\SilverstripeFrontdeskKit\Forms\FileUploadField;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Title
 * @property int $CertificateID
 * @property int $PhotoID
 * @property int $SecretID
 */
class FileUploadTestModel extends DataObject implements TestOnly
{
    private static string $table_name = 'FDK_FileUploadTestModel';
    private static array $db = ['Title' => 'Varchar'];
    private static array $has_one = [
        'Certificate' => File::class,
        'Photo' => Image::class,
        'Secret' => File::class,
    ];
}

class FileUploadTestController extends FrontdeskController implements TestOnly
{
    private static string $managed_model = FileUploadTestModel::class;
    private static string $url_segment = 'file-records';

    public function canView($member = null): bool
    {
        return true;
    }

    public function canEdit($member = null): bool
    {
        return true;
    }

    public function setTestAction(string $action): void
    {
        $this->action = $action;
    }

    protected function defineFieldLayout(?DataObject $record): ?FieldLayout
    {
        return FieldLayout::create()
            ->group('Main', ['Title', 'CertificateID', 'PhotoID'])
            ->setLevels(['SecretID' => FieldLayout::HIDDEN]);
    }

    /**
     * @return array<string, \SilverStripe\Model\ArrayData>
     */
    public function rows(DataObject $record): array
    {
        return $this->viewFieldRows($record);
    }
}

class FileUploadStandaloneController extends StandaloneRecordController implements TestOnly
{
    protected function resolveRecord(HTTPRequest $request): ?DataObject
    {
        return $request->param('Key') === 'key' ? FileUploadTestModel::get()->first() : null;
    }

    protected function baseLink(): string
    {
        return 'link/key';
    }

    protected function defineFieldLayout(DataObject $record): FieldLayout
    {
        return FieldLayout::create()
            ->setDefaultLevel(FieldLayout::HIDDEN)
            ->setLevels(['CertificateID' => FieldLayout::READ]);
    }
}

class FileUploadTest extends SapphireTest
{
    protected static $extra_dataobjects = [FileUploadTestModel::class];

    protected $usesDatabase = true;

    /**
     * @var array<int, string>
     */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        TestAssetStore::activate('FileUploadTest');
        Upload_Validator::config()->set('use_is_uploaded_file', false);
    }

    protected function tearDown(): void
    {
        $_FILES = [];
        foreach ($this->tmpFiles as $path) {
            @unlink($path);
        }
        TestAssetStore::reset();
        parent::tearDown();
    }

    private function controller(string $method, string $action, array $post = []): FileUploadTestController
    {
        $controller = FileUploadTestController::create();
        $request = new HTTPRequest($method, 'file-records/' . $action, [], $post);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $controller->setTestAction($action);
        return $controller;
    }

    private function upload(string $field, string $name, string $content = "%PDF-1.4\n%test\n"): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fdk');
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;
        $_FILES[$field] = [
            'name' => $name,
            'type' => 'application/octet-stream',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($content),
        ];
    }

    private function storedFile(string $name, string $content = 'stored', string $class = File::class): File
    {
        /** @var File $file */
        $file = $class::create();
        $file->setFromString($content, 'frontdesk/' . $name);
        $file->write();
        return $file;
    }

    private function download(FileUploadTestController $controller, int $id, string $field): HTTPResponse
    {
        $request = new HTTPRequest('GET', 'file-records/file/' . $id . '/' . $field);
        $request->setRouteParams(['ID' => $id, 'OtherID' => $field]);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        return $controller->file($request);
    }

    public function testEditFormHasUploadFieldsForFileRelations(): void
    {
        $record = FileUploadTestModel::create(['Title' => 'A']);
        $record->write();

        $fields = $this->controller('GET', 'edit/' . $record->ID)->EditForm()->Fields();

        $this->assertInstanceOf(FileUploadField::class, $fields->dataFieldByName('CertificateID'));
        $photo = $fields->dataFieldByName('PhotoID');
        $this->assertInstanceOf(FileUploadField::class, $photo);
        $this->assertSame(['jpg', 'jpeg', 'png', 'gif', 'webp'], $photo->getAllowedExtensions());
        $this->assertNull($fields->dataFieldByName('SecretID'), 'hidden file fields are not in the form');
        $this->assertNull($fields->dataFieldByName('Secret'));
    }

    public function testUploadIsStoredProtected(): void
    {
        $record = FileUploadTestModel::create(['Title' => 'A']);
        $record->write();
        $this->upload('CertificateID', 'zeugnis.pdf', '%PDF-1.4 test');

        $field = FileUploadField::create('CertificateID');
        $this->assertTrue($field->validate()->isValid());
        $field->saveInto($record);
        $record->write();

        $file = $record->Certificate();
        $this->assertTrue($file->exists());
        $this->assertSame('zeugnis.pdf', $file->Name);
        $this->assertFalse($file->isPublished());
        $this->assertSame(AssetStore::VISIBILITY_PROTECTED, $file->getVisibility());
        $this->assertStringStartsWith('frontdesk/', $file->getFilename());
    }

    /**
     * Frontend requests read and write the live stage: an upload there must still
     * stay unpublished, and view rows, downloads and replacing must find it.
     */
    public function testUploadInTheLiveStageStaysUnpublished(): void
    {
        $record = FileUploadTestModel::create(['Title' => 'A']);
        $record->write();

        Versioned::withVersionedMode(function () use ($record) {
            Versioned::set_stage(Versioned::LIVE);
            $this->upload('CertificateID', 'live.pdf');
            FileUploadField::create('CertificateID')->saveInto($record);
            $record->write();
            $_FILES = [];

            $this->assertSame(0, Versioned::get_by_stage(File::class, Versioned::LIVE)->filter('Name', 'live.pdf')->count());
            $controller = $this->controller('GET', 'view/' . $record->ID);
            $this->assertArrayHasKey('CertificateID', $controller->rows($record));
            $this->assertStringContainsString('%PDF', $this->download($controller, $record->ID, 'CertificateID')->getBody());

            $oldID = $record->CertificateID;
            $this->upload('CertificateID', 'live2.pdf');
            FileUploadField::create('CertificateID')->saveInto($record);
            $this->assertNotSame($oldID, $record->CertificateID);
            $this->assertNull(Versioned::get_by_stage(File::class, Versioned::DRAFT)->byID($oldID), 'replaced upload deleted');
        });

        $file = Versioned::get_by_stage(File::class, Versioned::DRAFT)->byID($record->CertificateID);
        $this->assertNotNull($file);
        $this->assertFalse($file->isPublished());
        $this->assertSame(AssetStore::VISIBILITY_PROTECTED, $file->getVisibility());
    }

    public function testReplacingDeletesTheOldUploadButNotAPublishedFile(): void
    {
        $old = $this->storedFile('old.pdf');
        $record = FileUploadTestModel::create(['CertificateID' => $old->ID]);
        $record->write();

        $this->upload('CertificateID', 'new.pdf');
        FileUploadField::create('CertificateID')->saveInto($record);
        $record->write();

        $this->assertNotSame($old->ID, $record->CertificateID);
        $this->assertNull(File::get()->byID($old->ID), 'the replaced upload is deleted');

        $published = $this->storedFile('published.pdf');
        $published->publishSingle();
        $record->CertificateID = $published->ID;
        $record->write();
        $this->upload('CertificateID', 'newer.pdf');
        FileUploadField::create('CertificateID')->saveInto($record);

        $this->assertNotNull(File::get()->byID($published->ID), 'a published file is kept');
    }

    public function testRemoveCheckboxClearsTheRelation(): void
    {
        $old = $this->storedFile('old.pdf');
        $record = FileUploadTestModel::create(['CertificateID' => $old->ID]);
        $record->write();

        $controller = $this->controller('POST', 'EditForm', ['ID' => $record->ID, 'CertificateID__remove' => '1']);
        $controller->pushCurrent();
        try {
            $controller->EditForm()->Fields()->dataFieldByName('CertificateID')->saveInto($record);
        } finally {
            $controller->popCurrent();
        }

        $this->assertSame(0, (int) $record->CertificateID);
        $this->assertNull(File::get()->byID($old->ID));
    }

    public function testNoUploadKeepsTheFile(): void
    {
        $old = $this->storedFile('old.pdf');
        $record = FileUploadTestModel::create(['CertificateID' => $old->ID]);
        $record->write();

        FileUploadField::create('CertificateID')->saveInto($record);

        $this->assertSame($old->ID, (int) $record->CertificateID);
    }

    public function testDisallowedExtensionIsRefused(): void
    {
        $this->upload('CertificateID', 'evil.html', '<script>alert(1)</script>');

        $result = FileUploadField::create('CertificateID')->validate();

        $this->assertFalse($result->isValid());
    }

    public function testRequiredFieldNeedsAFile(): void
    {
        $field = FileUploadField::create('CertificateID')->setRequired();
        $this->assertFalse($field->validate()->isValid());

        $field = FileUploadField::create('CertificateID')->setRequired()->setValue($this->storedFile('there.pdf')->ID);
        $this->assertTrue($field->validate()->isValid(), 'an existing file counts');

        $field = FileUploadField::create('CertificateID')->setRequired();
        $this->upload('CertificateID', 'new.pdf');
        $this->assertTrue($field->validate()->isValid());
    }

    public function testDownloadOfAVisibleFile(): void
    {
        $record = FileUploadTestModel::create(['CertificateID' => $this->storedFile('a.pdf', '%PDF')->ID]);
        $record->write();

        $response = $this->download($this->controller('GET', 'file'), $record->ID, 'CertificateID');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('%PDF', $response->getBody());
        $this->assertStringStartsWith('inline;', $response->getHeader('Content-Disposition'));
        $this->assertSame('private, no-store', $response->getHeader('Cache-Control'));
    }

    public function testOtherTypesAreDownloadedNotShownInline(): void
    {
        $record = FileUploadTestModel::create(['CertificateID' => $this->storedFile('notes.txt', '<b>x</b>')->ID]);
        $record->write();

        $response = $this->download($this->controller('GET', 'file'), $record->ID, 'CertificateID');

        $this->assertStringStartsWith('attachment;', $response->getHeader('Content-Disposition'));
        $this->assertSame('application/octet-stream', $response->getHeader('Content-Type'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function refusedFields(): array
    {
        return [
            'hidden in the layout' => ['SecretID'],
            'no file relation' => ['TitleID'],
            'not an ID field' => ['Certificate'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusedFields')]
    public function testDownloadIsRefused(string $field): void
    {
        $record = FileUploadTestModel::create([
            'CertificateID' => $this->storedFile('a.pdf')->ID,
            'SecretID' => $this->storedFile('secret.pdf')->ID,
        ]);
        $record->write();

        $this->expectException(HTTPResponse_Exception::class);
        $this->download($this->controller('GET', 'file'), $record->ID, $field);
    }

    public function testDownloadOfAnUnknownRecordIsRefused(): void
    {
        $this->expectException(HTTPResponse_Exception::class);
        $this->download($this->controller('GET', 'file'), 999, 'CertificateID');
    }

    public function testViewRowsLinkToTheDownload(): void
    {
        $record = FileUploadTestModel::create([
            'CertificateID' => $this->storedFile('a.pdf')->ID,
            'PhotoID' => $this->storedFile('p.png', 'png', Image::class)->ID,
        ]);
        $record->write();

        $rows = $this->controller('GET', 'view/' . $record->ID)->rows($record);

        $this->assertStringContainsString('file-records/file/' . $record->ID . '/CertificateID', $rows['CertificateID']->Value);
        $this->assertStringContainsString('<img src="file-records/file/' . $record->ID . '/PhotoID"', $rows['PhotoID']->Value);
    }

    public function testStandalonePageDownloadsOnlyVisibleFiles(): void
    {
        $record = FileUploadTestModel::create([
            'CertificateID' => $this->storedFile('a.pdf', '%PDF')->ID,
            'SecretID' => $this->storedFile('secret.pdf')->ID,
        ]);
        $record->write();

        $get = function (string $field): HTTPResponse {
            $controller = FileUploadStandaloneController::create();
            $request = new HTTPRequest('GET', 'link/key/file/' . $field);
            $request->setRouteParams(['Key' => 'key', 'Action' => 'file', 'ID' => $field]);
            $request->setSession(new Session([]));
            $controller->setRequest($request);
            return $controller->file($request);
        };

        $this->assertSame('%PDF', $get('CertificateID')->getBody());
        $this->expectException(HTTPResponse_Exception::class);
        $get('SecretID');
    }
}
