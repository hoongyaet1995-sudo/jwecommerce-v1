<?php

return [
    // Explicit server-owned allowlist; never accept an arbitrary browser-supplied destination.
    'compatible_base_urls' => array_values(array_filter(array_map('trim', explode(',', env('AI_COMPATIBLE_BASE_URLS', ''))))),
];
