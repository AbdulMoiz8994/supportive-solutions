<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size (Kilobytes)
    |--------------------------------------------------------------------------
    |
    | Applied from global settings at runtime when available.
    |
    */

    'max_kilobytes' => (int) env('UPLOAD_MAX_KILOBYTES', 10240),

];
