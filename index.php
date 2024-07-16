<?php

declare(strict_types=1);

require_once './vendor/autoload.php';

use CiroLoSapio\Parser\Processor;

$processor = new Processor(
    json_decode(file_get_contents('php://input')),
    $_GET['namespace'] ?? null,
    $_GET['first_name'] ?? null
);

$processor->download(
    $processor->createTarArchive(
        $processor->getFiles()
    )
);
