<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

/**
 * Field names of a record in both spellings: "School" and "SchoolID" for a has_one,
 * so a list matches form field names, db fields and relation names alike.
 */
class RecordFieldNames
{
    /**
     * @param array<int|string, mixed> $names
     * @return array<int, string>
     */
    public static function withRelationNames(array $names): array
    {
        $result = [];
        foreach ($names as $name) {
            $name = (string) $name;
            if ($name === '') {
                continue;
            }
            $result[] = $name;
            $result[] = str_ends_with($name, 'ID') ? substr($name, 0, -2) : $name . 'ID';
        }
        return array_values(array_unique($result));
    }
}
