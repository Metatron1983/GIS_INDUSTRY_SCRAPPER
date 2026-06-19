<?php
return [
    'base_url' => getenv('BASE_URL') ?: 'https://gisp.gov.ru',
    'navigator_url' => getenv('NAVIGATOR_URL') ?: 'https://gisp.gov.ru/navigator-measures/ru-RU',
    'items_per_page' => getenv('ITEMS_PER_PAGE') ?: 50,
    'max_retries' => getenv('MAX_RETRIES') ?: 3,
    'request_delay' => getenv('REQUEST_DELAY') ?: 2,
    'storage_path' => '/var/www/storage',
    'documents_path' => '/var/www/storage/documents',
    'reports_path' => '/var/www/storage/reports',
    'db' => [
        'host' => getenv('DB_HOST') ?: 'mysql',
        'name' => getenv('DB_NAME') ?: 'gisp_db',
        'user' => getenv('DB_USER') ?: 'gisp_user',
        'pass' => getenv('DB_PASS') ?: 'gisp_pass123',
    ],
    'yandex' => [
        'api_key' => getenv('YANDEX_API_KEY'),
        'folder_id' => getenv('YANDEX_FOLDER_ID'),
        'model' => getenv('YANDEX_MODEL') ?: 'yandexgpt-lite',
    ],
];