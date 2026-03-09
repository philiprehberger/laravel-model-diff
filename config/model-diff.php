<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ignored Attributes
    |--------------------------------------------------------------------------
    |
    | These model attributes will be excluded from all diff comparisons.
    | Add any attributes that are not meaningful to track as changes.
    |
    */

    'ignored_attributes' => [
        'created_at',
        'updated_at',
        'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Date Format
    |--------------------------------------------------------------------------
    |
    | When a date or datetime attribute changes, its value will be formatted
    | using this format string for human-readable output via toHumanReadable().
    |
    */

    'date_format' => 'M j, Y g:i A',

];
