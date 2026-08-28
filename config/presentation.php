<?php

return [
    'service_token' => env('MAGIC_HTML_SERVICE_TOKEN'),
    'requests_per_minute' => (int) env('PRESENTATION_REQUESTS_PER_MINUTE', 60),
    'compiled_css_path' => resource_path('presentation/tailwind.css'),
    'utility_version' => '4.3.3',
    'heroicons_path' => resource_path('presentation/heroicons.json'),
    'profiles' => [
        'tailwindcss-heroicons-system-v1' => [
            'component_adapter' => 'tailwindcss-core',
            'utility_adapter' => 'tailwindcss-v4',
            'icon_adapter' => 'heroicons-v2',
            'font_adapters' => ['system', 'self-hosted'],
            'image_adapter' => 'relative-assets',
        ],
    ],
];
