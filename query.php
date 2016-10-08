<?php

$url = getenv('SEARCH_URL') . '/entry/_search';
$terms = array();
foreach (array('q') as $k) {
    if (array_key_exists($k, $_GET)) {
        $terms[] = urlencode($k) . '=' . urlencode($_GET[$k]);
    }
}

if (count($terms)) {
    $url .= '?' . implode('&', $terms);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$curl = curl_init($url);
curl_exec($curl);
