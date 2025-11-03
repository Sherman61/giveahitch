<?php
namespace App\Status;

/**
 * Map canonical statuses used in the application to the values stored in the
 * database. Historically the `status` column was limited to 10 characters,
 * so values like `in_progress` had to be shortened when persisted.
 */
const DB_STATUS_MAP = [
    'in_progress' => 'inprogress',
];

/** @var array<string,string> */
const CANONICAL_STATUS_MAP = [
    'inprogress' => 'in_progress',
];

/**
 * Convert a canonical status value (used by the UI / business logic) into the
 * value that should be stored in the database.
 */
function to_db(string $status): string
{
    return DB_STATUS_MAP[$status] ?? $status;
}

/**
 * Convert a status value fetched from the database into the canonical form
 * used throughout the application.
 */
function from_db(?string $status): string
{
    $status = (string) $status;
    return CANONICAL_STATUS_MAP[$status] ?? $status;
}

/**
 * Normalize a list of status values from the database into canonical form.
 *
 * @param array<int|string, string|null> $rows
 * @return array<int|string, string>
 */
function normalize_list(array $rows): array
{
    foreach ($rows as $key => $value) {
        $rows[$key] = from_db($value);
    }
    return $rows;
}
