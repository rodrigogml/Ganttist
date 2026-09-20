<?php

return [
    'enabled' => (bool) env('DOCUMENTS_ENABLED', false),
    'disk' => env('DOCUMENTS_DISK', 'local'),
    'max_upload_kb' => (int) env('DOCUMENTS_MAX_UPLOAD_KB', 512000),
    'processor' => [
        'python' => env('DOCUMENTS_PYTHON_BINARY', 'python3'),
        'script' => base_path('scripts/documents/pdf_crop_region.py'),
        'timeout_seconds' => (int) env('DOCUMENTS_PROCESSOR_TIMEOUT', 240),
    ],
];
