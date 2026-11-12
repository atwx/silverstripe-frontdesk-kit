<?php

namespace Atwx\SilverstripeFrontdeskKit\View;

use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\ORM\FieldType\DBText;

/**
 * Row of a record view for a db field, as text the view escapes: strings with
 * their raw value (their Nice() is already escaped), other fields (dates,
 * booleans, numbers) with Nice(); plain multi-line text (DBText) as escaped HTML
 * with its line breaks kept.
 */
class ViewRow
{
    public static function forField(string $label, DataObject $record, string $name): ArrayData
    {
        $field = $record->dbObject($name);
        if ($field instanceof DBText && !$field instanceof DBHTMLText) {
            $text = (string) $field->getValue();
            return ArrayData::create([
                'Label' => $label,
                'Value' => nl2br(htmlspecialchars($text, ENT_QUOTES), false),
                'Type' => 'html',
            ]);
        }
        if ($field instanceof DBHTMLText) {
            // Rich text as plain text (its HTML is not trusted in views)
            $value = (string) $field->Plain();
        } elseif ($field instanceof DBString) {
            // Nice() of strings is HTML-escaped; the view escapes text itself.
            $value = (string) $field->getValue();
        } else {
            $value = $field
                ? (method_exists($field, 'Nice') ? (string) $field->Nice() : (string) $field)
                : (string) $record->$name;
        }
        return ArrayData::create(['Label' => $label, 'Value' => $value, 'Type' => 'text']);
    }
}
