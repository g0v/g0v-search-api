<?php

include(__DIR__ . '/config.php');
include(__DIR__ . '/Elastic.php');

try {
    Elastic::dropIndex('entry');
} catch (Exception $e) {
}

Elastic::createIndex('entry', [
    'properties' => [
        'url' => ['type' => 'keyword'],
        'source' => ['type' => 'keyword'],
        'updated_at' => ['type' => 'integer'],
        'title' => ['type' => 'text', 'analyzer' => 'cjk'],
        'content' => ['type' => 'text', 'analyzer' => 'cjk'],
    ],
]);
