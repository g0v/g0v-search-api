<?php

date_default_timezone_set('Asia/Taipei');
include(__DIR__ . '/../../Elastic.php');
include(__DIR__ . '/../../config.php');

if (!getenv('ELASTIC_URL')) {
    throw new Exception("need ELASTIC_URL");
}

$prefix = getenv('ELASTIC_PREFIX');
$obj = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode([
    'query' => [
        'term' => ['source' => 'logbot'],
    ],
    'size' => 0,
    'aggs' => [
        'max_update' => ['max' => ['field' => 'updated_at']],
    ],
]));
$max_update = $obj->aggregations->max_update->value;
error_log("update from timestamp {$max_update} " . date('(c)', $max_update));
$max_update = max($max_update, mktime(0, 0, 0, 7, 26, 2013)); // 最早從 2013/7/26 開始

$c = 0;
for ($date = strtotime('00:00:00', $max_update); $date < time() ; $date += 86400) {
    $content = file_get_contents("https://logbot.g0v.tw/channel/g0v.tw/" . date('Y-m-d', $date));
    $content = str_replace('</head>', '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>', $content);

    $doc = new DOMDocument;
    @$doc->loadHTML($content);
    $match = false;
    foreach ($doc->getElementsByTagName('ul') as $ul_dom) {
        if ($ul_dom->getAttribute('class') == 'logs') {
            $match = true;
            break;
        }
    }
    if (!$match) {
        throw new Exception("找不到 ul.logs");
    }
    $msg = '';

    foreach ($ul_dom->getElementsByTagName('li') as $li_dom) {
        $msg .= "\n" . $li_dom->getElementsByTagName('span')->item(0)->nodeValue;
    }

	$d = date('Y-m-d', $date);
    Elastic::dbBulkInsert('entry', "logbot-{$d}", [
        'url' => 'https://logbot.g0v.tw/channel/g0v.tw/' . date('Y-m-d', $date),
        'title' => "g0v logbot " . date('Y-m-d', $date),
        'updated_at' => $date,
        'source' => 'logbot',
        'id' => date('Y-m-d', $date),
        'content' => trim($msg),
        'data' => array(),
	]);
    $c ++;
}
Elastic::dbBulkCommit();
error_log("update {$c} pads");
