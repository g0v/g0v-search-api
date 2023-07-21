<?php

include(__DIR__ . '/Elastic.php');
if (file_exists(__DIR__ . '/config.php')) {
    include(__DIR__ . '/config.php');
}
$terms = array();

foreach (array('q') as $k) {
    if (array_key_exists($k, $_GET)) {
        $terms[] = urlencode($k) . '=' . urlencode($_GET[$k]);
    }
}
$terms[] = 'track_scores=true';

$prefix = getenv('ELASTIC_PREFIX');
$url = "/{$prefix}entry/_search";
if (count($terms)) {
    $url .= '?' . implode('&', $terms);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if (array_key_exists('query', $_GET)) {
    $ret = Elastic::dbQuery($url, 'GET', strval($_GET['query']));
} else {
    $ret = Elastic::dbQuery($url);
}
echo json_encode($ret);
