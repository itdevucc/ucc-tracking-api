<?php

return [
    'source' => [
        'connection' => env('SAILOR_SOURCE_DB_CONNECTION', 'sailor_source'),
        'table' => env('SAILOR_SOURCE_TABLE', 'booking_itineraries'),
        'carriers_table' => env('SAILOR_SOURCE_CARRIERS_TABLE', 'sys_navieras'),
        'states_table' => env('SAILOR_SOURCE_STATES_TABLE', 'booking_itinerary_states'),
        'state_codes' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SAILOR_SOURCE_STATE_CODES', 'CONFIRMED,EMBARKED'))
        ))),
        'carrier_codes' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SAILOR_SOURCE_CARRIER_CODES', 'HLCU,MSCU'))
        ))),
        'allowed_databases' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SAILOR_SOURCE_ALLOWED_DATABASES', 'sailor_qa'))
        ))),
    ],
    'sync' => [
        'interval_minutes' => (int)env('TRACKING_SYNC_INTERVAL_MINUTES', 30),
        'batch_size' => (int)env('TRACKING_SYNC_BATCH_SIZE', 100),
        'max_failures' => (int)env('TRACKING_SYNC_MAX_FAILURES', 8),
    ],
    'hapag_lloyd' => [
        'base_url' => env('HAPAG_TRACKING_BASE_URL', 'https://api.hlag.com/hlag/external/v2/events'),
        'client_id' => env('HAPAG_CLIENT_ID'),
        'client_secret' => env('HAPAG_CLIENT_SECRET'),
    ],
    'msc' => [
        'base_url' => env('MSC_TRACKING_BASE_URL', 'https://api.tech.msc.com/msc/trackandtrace/v2.2'),
        'booking_parameter' => env('MSC_TRACKING_BOOKING_PARAMETER', 'carrierBookingReference'),
        'tenant_id' => env('MSC_TENANT_ID'),
        'client_id' => env('MSC_CLIENT_ID'),
        'scope' => env('MSC_SCOPE'),
        'certificate_path' => env('MSC_CERTIFICATE_PATH'),
        'private_key_path' => env('MSC_PRIVATE_KEY_PATH'),
        'certificate_password' => env('MSC_CERTIFICATE_PASSWORD'),
    ],


];
