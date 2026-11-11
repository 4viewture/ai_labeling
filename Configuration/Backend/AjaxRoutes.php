<?php

declare(strict_types=1);

// File list context menu "Scan for AI content" (preview and apply)
return [
    'ailabeling_scan_file' => [
        'path' => '/ai-labeling/scan-file',
        'methods' => ['POST'],
        'target' => \FourViewture\AiLabeling\Controller\FileScanController::class . '::scanAction',
    ],
];
