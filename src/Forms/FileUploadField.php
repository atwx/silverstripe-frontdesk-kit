<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Versioned\Versioned;

/**
 * Upload of one file into a has_one File (or Image) relation, for frontdesk forms.
 * Named like other has_one fields ("CertificateID"), so field layouts, levels and
 * view rows use the same name.
 *
 * - Files are stored protected: they stay in the draft stage and are never
 *   published, so the web server does not serve them. They are downloaded through
 *   the controller that shows the record (see RecordFiles), which checks the
 *   record and the field level.
 * - Allowed extensions and the maximum size come from config (Image relations
 *   only accept images) and can be set per field.
 * - A replaced or removed file is deleted, unless it was published (e.g. chosen
 *   in the CMS and used elsewhere).
 * - Required: setRequired() refuses a save without a file (an existing one counts).
 */
class FileUploadField extends FileField
{
    /**
     * Folder for uploaded files (protected).
     */
    private static string $default_folder = 'frontdesk';

    /**
     * @var array<int, string>
     */
    private static array $allowed_extensions = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'doc', 'docx', 'odt', 'rtf', 'xls', 'xlsx', 'ods', 'csv', 'txt',
    ];

    /**
     * @var array<int, string>
     */
    private static array $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Maximum file size in bytes or INI format (e.g. "10M").
     */
    private static string $max_file_size = '10M';

    private ?string $downloadLink = null;

    private bool $required = false;

    /**
     * @param string $name Form field name: the relation name with "ID" (e.g. "CertificateID")
     */
    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);
        $this->setFolderName(static::config()->get('default_folder'));
        $this->setAllowedExtensions(static::config()->get('allowed_extensions'));
        $this->getValidator()->setAllowedMaxFileSize(static::config()->get('max_file_size'));
    }

    /**
     * Replaces scaffolded file fields of has_one File relations (the CMS UploadField,
     * which only works in the CMS, or a plain FileField) with this field.
     */
    public static function replaceCmsFields(FieldList $fields, DataObject $record): FieldList
    {
        $relations = RecordFiles::fileRelations($record);
        foreach ($fields->dataFields() as $field) {
            $name = $field->getName();
            $relation = str_ends_with($name, 'ID') ? substr($name, 0, -2) : $name;
            if (!isset($relations[$relation]) || $field instanceof self) {
                continue;
            }
            $replacement = static::create($relation . 'ID', $field->Title())
                ->forRelation($relations[$relation]);
            $fields->replaceField($name, $replacement);
        }
        return $fields;
    }

    /**
     * Limits the allowed extensions to images for Image relations.
     *
     * @param class-string<File> $class
     */
    public function forRelation(string $class): static
    {
        if (is_a($class, Image::class, true)) {
            $images = static::config()->get('image_extensions');
            $this->setAllowedExtensions(array_values(array_intersect($images, $this->getAllowedExtensions() ?: $images)));
        }
        return $this;
    }

    public function setMaxFileSize(int|string $size): static
    {
        $this->getValidator()->setAllowedMaxFileSize($size);
        return $this;
    }

    public function setRequired(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function Required()
    {
        return $this->required || parent::Required();
    }

    /**
     * Link to download the current file (set by the controller that shows the form).
     */
    public function setDownloadLink(?string $link): static
    {
        $this->downloadLink = $link;
        return $this;
    }

    public function getDownloadLink(): ?string
    {
        return $this->downloadLink;
    }

    public function getRelationName(): string
    {
        return str_ends_with($this->name, 'ID') ? substr($this->name, 0, -2) : $this->name;
    }

    public function getCurrentFile(): ?File
    {
        return RecordFiles::fileByID(is_numeric($this->value) ? (int) $this->value : 0);
    }

    public function getRemoveName(): string
    {
        return $this->name . '__remove';
    }

    public function getAcceptAttribute(): string
    {
        return implode(',', array_map(fn ($ext) => '.' . $ext, $this->getAllowedExtensions()));
    }

    /**
     * Allowed types and size for the hint below the field.
     */
    public function getHint(): string
    {
        return _t(self::class . '.HINT', 'Allowed: {types}, at most {size}', [
            'types' => implode(', ', $this->getAllowedExtensions()),
            'size' => File::format_size($this->getValidator()->getAllowedMaxFileSize()),
        ]);
    }

    public function Type()
    {
        return 'file fdk-file-upload';
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function Field($properties = [])
    {
        return $this->renderWith(self::class, $properties);
    }

    public function performReadonlyTransformation()
    {
        $clone = clone $this;
        $clone->setReadonly(true);
        return $clone;
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if ($this->required && !$this->hasUpload() && (!$this->getCurrentFile() || $this->removeRequested())) {
            $result->addFieldError($this->name, _t(self::class . '.REQUIRED', 'Please choose a file.'), 'required');
        }
        return $result;
    }

    public function saveInto(DataObjectInterface $record): void
    {
        if ($this->isReadonly() || $this->isDisabled() || !$record instanceof DataObject) {
            return;
        }
        $field = $this->name;
        $oldID = (int) $record->$field;

        if ($this->hasUpload()) {
            $class = DataObject::getSchema()->hasOneComponent($record::class, $this->getRelationName()) ?: File::class;
            if ($class === File::class) {
                $class = File::get_class_for_file_extension(File::get_file_extension($_FILES[$field]['name']));
            }
            $file = Injector::inst()->create($class);
            // Written to the draft stage only: a write in the live stage (the reading
            // stage of frontend requests) would publish the file.
            $loaded = RecordFiles::inDraft(
                fn () => $this->upload->loadIntoFile($_FILES[$field], $file, $this->getFolderName())
            );
            if (!$loaded || $this->upload->isError()) {
                return;
            }
            $record->$field = $file->ID;
        } elseif ($this->removeRequested() && !$this->required) {
            $record->$field = 0;
        } else {
            return;
        }

        if ($oldID && $oldID !== (int) $record->$field) {
            static::deleteUnpublished(RecordFiles::fileByID($oldID));
        }
    }

    /**
     * Deletes a file that was never published (an upload of this field).
     */
    public static function deleteUnpublished(?File $file): void
    {
        if (!$file || !$file->exists()) {
            return;
        }
        RecordFiles::inDraft(function () use ($file) {
            if (!$file->hasExtension(Versioned::class)) {
                $file->delete();
            } elseif (!$file->isPublished()) {
                $file->doArchive();
            }
        });
    }

    private function hasUpload(): bool
    {
        $file = $_FILES[$this->name] ?? null;
        return is_array($file) && isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    private function removeRequested(): bool
    {
        return (bool) Controller::curr()?->getRequest()->postVar($this->getRemoveName());
    }

    /**
     * Single-item list for templates (current file with link).
     *
     * @return ArrayList<ArrayData>
     */
    public function getCurrent(): ArrayList
    {
        $file = $this->getCurrentFile();
        if (!$file) {
            return ArrayList::create();
        }
        return ArrayList::create([ArrayData::create([
            'Name' => $file->Name,
            'Size' => $file->getSize(),
            'Link' => $this->downloadLink,
        ])]);
    }
}
