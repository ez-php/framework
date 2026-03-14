<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME'),
    'debug' => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN),
    'locale' => getenv('APP_LOCALE') ?: 'en',
    'fallback_locale' => getenv('APP_FALLBACK_LOCALE') ?: 'en',
];
