# Files

has_one relations to `File` or `Image` get an upload field in frontdesk forms
(`FileUploadField`), in the edit form of a `FrontdeskController` and on standalone
record pages. The CMS upload field, which only works in the CMS, is replaced.

```php
private static array $has_one = [
    'Certificate' => File::class,
    'Photo' => Image::class,      // only images are accepted
];

// Delete the files with the record (not cascade_deletes: it reads the live stage
// on the frontend, where unpublished uploads do not exist)
protected function onAfterDelete(): void
{
    parent::onAfterDelete();
    RecordFiles::deleteFiles($this);
}
```

The field is named like other has_one fields: `CertificateID`. Use this name in
field layouts (groups, levels) and in `formFields()`:

```php
protected function formFields(FieldList $fields): FieldList
{
    $fields->dataFieldByName('CertificateID')
        ?->setAllowedExtensions(['pdf'])
        ->setMaxFileSize('5M')
        ->setRequired();
    return $fields;
}
```

## Protection

- Uploads are stored in the folder `frontdesk` (config `default_folder`) and stay
  in the draft stage. They are never published, so the web server does not serve
  them (`assets/.protected`). Frontend requests read and write the live stage, so
  uploads, lookups and deletions run in the draft stage (`RecordFiles::inDraft()`);
  read such files with `RecordFiles::fileOf($record, 'Certificate')`, not with
  `$record->Certificate()`, which finds nothing on the frontend.
- Files are downloaded through the controller that shows the record:
  - `<url_segment>/file/<ID>/<FieldName>` on a `FrontdeskController` (and its
    sub-controllers): the record must be in the list (`getBaseList()`) and pass
    `check_record_permissions`, the member must be allowed to view the controller.
  - `<key>/file/<FieldName>` on a standalone record page.
  - In both cases the field must be a has_one File relation and visible in the
    record's field layout. Hidden fields give a 404.
- PDFs and images are shown inline, all other types are downloaded
  (`Content-Disposition: attachment`), with `Cache-Control: private, no-store`,
  `nosniff` and a sandboxing Content-Security-Policy.
- A replaced or removed file is deleted, unless it was published (a file chosen in
  the CMS and used elsewhere).

## Config

```yaml
Atwx\SilverstripeFrontdeskKit\Forms\FileUploadField:
  default_folder: 'frontdesk'
  max_file_size: '10M'
  allowed_extensions: [pdf, jpg, jpeg, png, gif, webp, doc, docx, odt, rtf, xls, xlsx, ods, csv, txt]
  image_extensions: [jpg, jpeg, png, gif, webp]
```

Uploads are checked against the allowed extensions, the size and (with
silverstripe/mimevalidator) the file's content type.

## View

The record view shows a file as a link to the download (with its size), an image
also as a preview loaded through the same link. `RecordFiles::viewRow()` builds
such a row for own views.
