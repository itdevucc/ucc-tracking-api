<?php

return [
    'administrators' => array_values(array_filter(array_map(
        fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('INTERNAL_ADMIN_EMAILS', 'itdevucc@ucclog.com,mlopez@ucclog.com')),
    ))),
];
