<?php

return [
    /*
    | Windows: use forward slashes or doubled backslashes in .env (Dotenv rejects single \).
    | Paths under "Program Files" are rewritten to an 8.3 short path at boot so Snappy works.
    | Or set WKHTMLTOPDF_BINARY to e.g. C:\Progra~1\wkhtmltopdf\bin\wkhtmltopdf.exe manually.
    */
    'pdf' => [
        'enabled' => true,
        'binary' => env('WKHTMLTOPDF_BINARY', 'wkhtmltopdf'),
        'timeout' => false,
        'options' => [
            'enable-local-file-access' => true,
            'encoding' => 'utf-8',
            'margin-top' => 0,
            'margin-right' => 0,
            'margin-bottom' => 0,
            'margin-left' => 0,
        ],
        'env' => [],
    ],
    'image' => [
        'enabled' => true,
        'binary' => env('WKHTMLTOIMAGE_BINARY', 'wkhtmltoimage'),
        'timeout' => false,
        'options' => [],
        'env' => [],
    ],
];

