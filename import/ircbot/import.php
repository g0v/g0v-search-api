<?php

date_default_timezone_set('Asia/Taipei');
if (!getenv('SEARCH_URL')) {
    throw new Exception("need SEARCH_URL");
}

$curl = curl_init(getenv('SEARCH_URL') . '/entry/_search');
curl_setopt($curl, CURLOPT_POSTFIELDS, '{"query":{"term":{"source":"ircbot"}},"size":0,"aggs":{"max_update":{"max":{"field":"updated_at"}}}}');
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$ret = curl_exec($curl);
$obj = json_decode($ret);
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

    $curl = curl_init();
    $url = getenv('SEARCH_URL') . '/entry/logbot-' . date("Y-m-d", $date);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
        'url' => 'https://logbot.g0v.tw/channel/g0v.tw/' . date('Y-m-d', $date),
        'title' => "g0v logbot " . date('Y-m-d', $date),
        'updated_at' => $date,
        'source' => 'logbot',
        'id' => date('Y-m-d', $date),
        'content' => trim($msg),
        'data' => array(),
    )));
    $ret = curl_exec($curl);
    $info = curl_getinfo($curl);
    $c ++;
    if (!in_array($info['http_code'], array(200, 201))) {
        throw new Exception($info['http_code'] . $ret);
    }
}
error_log("update {$c} pads");
