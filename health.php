<?php

date_default_timezone_set('Asia/Taipei');

include(__DIR__ . '/Elastic.php');
if (file_exists(__DIR__ . '/config.php')) {
    include(__DIR__ . '/config.php');
}

if (!getenv('ELASTIC_URL')) {
    throw new Exception("need ELASTIC_URL");
}

$ret = array(
    'group_times' => array(),
    'warnings' => array(),
);
$groups = array('hackpad', 'repo', 'logbot');
$prefix = getenv('ELASTIC_PREFIX');

foreach ($groups as $group) {
    try {
        $obj = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode([
            'query' => [
                'term' => ['source' => $group],
            ],
            'size' => 0,
            'aggs' => [
                'max_update' => [ 'max' => ['field' => 'updated_at' ]],
            ],
        ]));
    } catch (Exception $e) {
        $ret['warnings'][] = "API 抓取 {$group} 失敗";
        continue;
    }
    $max_update = $obj->aggregations->max_update->value;
    $ret['group_times'][$group] = date('Y/m/d H:i:s', $max_update);
    if (time() - $max_update > 3 * 86400) {
        $ret['warnings'][] = "{$group} 超過三天未更新";
    }
}

echo json_encode($ret, JSON_UNESCAPED_UNICODE);
