<?php

date_default_timezone_set('Asia/Taipei');
$repo_path = "/tmp/github-issues";

include(__DIR__ . '/../../Elastic.php');
include(__DIR__ . '/../../config.php');

if (!getenv('ELASTIC_URL')) {
    throw new Exception("need ELASTIC_URL");
}

if (!file_exists($repo_path)) {
    chdir(dirname($repo_path));
    system("git clone https://github.com/g0v-data/github-issues", $ret);
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
        'term' => ['source' => 'issues'],
    ],
    'size' => 0,
    'aggs' => [
        'max_update' => ['max' => ['field' => 'updated_at']],
    ],
]));
$max_update = $obj->aggregations->max_update->value;
error_log("update from timestamp {$max_update} " . date('(c)', $max_update));

$c = 0;
foreach (json_decode(file_get_contents($repo_path . '/issues.json')) as $value) {
    if ($max_update and strtotime($value->updated_at) <= $max_update) {
        continue;
    }

    Elastic::dbBulkInsert('entry', "issues-{$value->id}", [
        'url' => $value->url,
        'title' => $value->title,
        'updated_at' => strtotime($value->updated_at),
        'source' => 'issues',
        'id' => $value->id,
        'content' => $value->title . "\n" . $value->body,
        'data' => $value,
    ]);
    $c ++;
}
Elastic::dbBulkCommit();
error_log("update {$c} pads");
