<?php

date_default_timezone_set('Asia/Taipei');
$repo_path = "/tmp/hackfoldr-backup-g0v/";
include(__DIR__ . '/../../Elastic.php');
include(__DIR__ . '/../../config.php');

if (!getenv('ELASTIC_URL')) {
        throw new Exception("need ELASTIC_URL");
}

if (!file_exists($repo_path)) {
    chdir(dirname($repo_path));
    system("git clone https://github.com/JmeHsieh/hackfoldr-backup-g0v", $ret);
} else {
    chdir($repo_path);
    system("git pull", $ret);
}
if ($ret !== 0) {
    throw new Exception("git pull failed");
}

$prefix = getenv('ELASTIC_PREFIX');
$obj = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode([
    'query' => [
        'term' => ['source' => 'hackfoldr'],
    ],
    'size' => 0,
    'aggs' => [
        'max_update' => ['max' => ['field' => 'updated_at']],
    ],
]));
$max_update = $obj->aggregations->max_update->value;
error_log("update from timestamp {$max_update} " . date('(c)', $max_update));

$c = 0;
foreach (json_decode(file_get_contents($repo_path . 'foldrs.json')) as $key => $value) {
    if ($max_update and strtotime($value->updated_at) <= $max_update) {
        continue;
    }
    if (!file_exists($repo_path . $key. '.json')) {
        throw new Exception("$key not found");
    }
    $content = json_decode(file_get_contents($repo_path . $key. '.json'));
    if (count($content) < 1) {
        continue;
    }
    $headers = array_shift($content);
    if ($headers[1] != '#title') {
        continue;
    }
    $title = $content[0][1];
    $content = trim(implode("\n", array_map(function($rows) { return $rows[1]; }, $content)));

    Elastic::dbBulkInsert('entry', "hackfoldr-{$key}", [
        'url' => $value->url,
        'title' => $title,
        'updated_at' => strtotime($value->updated_at),
        'source' => 'hackfoldr',
        'id' => $key,
        'content' => $content,
        'data' => $value,
    ]);
    $c ++;
}
Elastic::dbBulkCommit();
error_log("update {$c} pads");
