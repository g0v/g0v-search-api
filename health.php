<?php

date_default_timezone_set('Asia/Taipei');

$ret = array(
    'group_times' => array(),
    'warnings' => array(),
);
$groups = array('hackpad', 'repo');

foreach ($groups as $group) {
    $curl = curl_init(getenv('SEARCH_URL') . '/entry/_search');
    curl_setopt($curl, CURLOPT_POSTFIELDS, '{"query":{"term":{"source":"' . $group . '"}},"size":0,"aggs":{"max_update":{"max":{"field":"updated_at"}}}}');
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    if (!$content = curl_exec($curl)) {
        $ret['warnings'][] = "API 抓取 {$group} 失敗";
    }
    $obj = json_decode($content);
    $max_update = $obj->aggregations->max_update->value;
    $ret['group_times'][$group] = date('Y/m/d H:i:s', $max_update);
    if (time() - $max_update > 3 * 86400) {
        $ret['warnings'][] = "{$group} 超過三天未更新";
    }
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);
