<?php

declare(strict_types=1);

$proxies = (string) env('TRUSTED_PROXIES', 'REMOTE_ADDR');

return [
    'proxies' => $proxies === 'none' ? [] : array_values(array_filter(array_map(trim(...), explode(',', $proxies)))),
];
