<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPStreamResponse;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Files of a record's has_one File relations: which relations hold files, the file
 * of a field the member may see, the download response and view rows.
 *
 * Downloads are the only way to the (protected) files of FileUploadField. The
 * controller that shows a record checks the record first (canView, its list), this
 * class checks the field: it must be a has_one File relation and visible in the
 * record's field layout.
 */
class RecordFiles
{
    /**
     * Extensions shown inline in the browser; all others are downloaded (an HTML or
     * SVG file shown inline could run scripts on the site's origin).
     */
    private const array INLINE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Runs $callback in the draft stage. Uploads are never published, so they only
     * exist in the draft stage, while frontend requests read (and write) the live
     * stage: every lookup, upload and deletion of such files goes through here.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function inDraft(callable $callback): mixed
    {
        return Versioned::withVersionedMode(function () use ($callback) {
            Versioned::set_stage(Versioned::DRAFT);
            return $callback();
        });
    }

    public static function fileByID(int $id): ?File
    {
        if (!$id) {
            return null;
        }
        $file = static::inDraft(fn () => File::get()->byID($id));
        return $file instanceof File && $file->exists() ? $file : null;
    }

    /**
     * The file of a has_one File relation, read from the draft stage.
     */
    public static function fileOf(DataObject $record, string $relation): ?File
    {
        return static::fileByID((int) $record->getField($relation . 'ID'));
    }

    /**
     * Deletes the unpublished files of the record's file relations; call it from
     * onAfterDelete() (cascade_deletes reads the live stage on the frontend and
     * misses them).
     */
    public static function deleteFiles(DataObject $record): void
    {
        foreach (array_keys(static::fileRelations($record)) as $relation) {
            FileUploadField::deleteUnpublished(static::fileOf($record, $relation));
        }
    }

    /**
     * has_one relations of the record that hold files, as relation name => class.
     *
     * @return array<string, class-string<File>>
     */
    public static function fileRelations(DataObject $record): array
    {
        $relations = [];
        foreach ((array) $record->config()->get('has_one') as $name => $spec) {
            $class = is_array($spec) ? ($spec['class'] ?? null) : $spec;
            if (is_string($class) && is_a($class, File::class, true)) {
                $relations[(string) $name] = $class;
            }
        }
        return $relations;
    }

    /**
     * The file of a field ("CertificateID") of the record, null when the field is no
     * file relation, hidden in the layout or empty. The caller checks the record.
     */
    public static function visibleFile(DataObject $record, string $fieldName, ?FieldLayout $layout): ?File
    {
        if (!str_ends_with($fieldName, 'ID')) {
            return null;
        }
        $relation = substr($fieldName, 0, -2);
        if (!isset(static::fileRelations($record)[$relation])) {
            return null;
        }
        if ($layout && !$layout->isVisible($fieldName)) {
            return null;
        }
        return static::fileOf($record, $relation);
    }

    /**
     * Streams the file: inline for PDFs and images, as a download otherwise; never
     * cached by shared caches.
     */
    public static function response(File $file, bool $forceDownload = false): HTTPResponse
    {
        $extension = strtolower((string) $file->getExtension());
        $inline = !$forceDownload && in_array($extension, self::INLINE_EXTENSIONS, true);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $file->Name) ?: 'file';

        $response = HTTPStreamResponse::create($file->getStream(), (int) $file->getAbsoluteSize());
        $response->addHeader('Content-Type', $inline ? (string) $file->getMimeType() : 'application/octet-stream');
        $response->addHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
        $response->addHeader('X-Content-Type-Options', 'nosniff');
        $response->addHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        $response->addHeader('Cache-Control', 'private, no-store');
        return $response;
    }

    /**
     * View row of a file: its name linked to the download, images also as a
     * preview (loaded through the same link).
     */
    public static function viewRow(string $label, File $file, string $link): ArrayData
    {
        $name = htmlspecialchars((string) $file->Name, ENT_QUOTES);
        $href = htmlspecialchars($link, ENT_QUOTES);
        $html = '<a class="link" href="' . $href . '" target="_blank" rel="noopener">' . $name . '</a>'
            . ' <span class="text-base-content/60 text-sm">(' . htmlspecialchars((string) $file->getSize(), ENT_QUOTES) . ')</span>';
        if ($file instanceof Image) {
            $html = '<a href="' . $href . '" target="_blank" rel="noopener"><img src="' . $href . '" alt="' . $name . '"'
                . ' class="max-h-40 max-w-48 rounded mb-1" loading="lazy"></a>' . $html;
        }
        return ArrayData::create(['Label' => $label, 'Value' => $html, 'Type' => 'html']);
    }
}
