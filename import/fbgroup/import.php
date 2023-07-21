<?php

date_default_timezone_set('Asia/Taipei');
$repo_path = "/tmp/g0v-fbpage";
include(__DIR__ . '/../../Elastic.php');
include(__DIR__ . '/../../config.php');

if (!getenv('ELASTIC_URL')) {
    throw new Exception("need ELASTIC_URL");
}

if (!file_exists($repo_path)) {
    chdir(dirname($repo_path));
    system("git clone https://github.com/g0v-data/g0v-fbpage", $ret);
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
        'term' => ['source' => 'fbgroup'],
    ],
    'size' => 0,
    'aggs' => [
        'max_update' => ['max' => ['field' => 'updated_at']],
    ],
]));
$max_update = $obj->aggregations->max_update->value;
error_log("update from timestamp {$max_update} " . date('(c)', $max_update));

$c = 0;
foreach (glob("{$repo_path}/*.json") as $json_file) {
    $value = json_decode(file_get_contents($json_file));
    if ($max_update and strtotime($value->updated_time) <= $max_update) {
        continue;
    }
    $title = $value->message;
    $body = $value->message . "\n";
    foreach ($value->comments->data as $d) {
        $body = trim($body) . "\n" .  $d->message;
    }

    $id = explode('_', $value->id)[1];
    $url = "https://facebook.com/{$value->id}";

    Elastic::dbBulkInsert('entry', "fbgroup-{$id}", [
        'url' => 'https://facebook.com/' . $value->id,
        'title' => $title,
        'updated_at' => strtotime($value->updated_time),
        'source' => 'fbgroup',
        'id' => $id,
        'content' => $title . "\n" . $body,
        'fbgroup_data' => json_encode($value),
    ]);
    $c ++;
}
Elastic::dbBulkCommit();
error_log("update {$c} pads");
