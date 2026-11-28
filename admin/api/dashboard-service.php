<?php

declare(strict_types=1);

$sharedService = dirname(__DIR__, 2) . '/includes/dashboard-service.php';

if (!file_exists($sharedService)) {
    throw new RuntimeException('Shared dashboard service file not found: ' . $sharedService);
}

require_once $sharedService;