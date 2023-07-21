<?php

date_default_timezone_set('Asia/Taipei');
$repo_path = "/tmp/hackpad-backup-g0v/";

include(__DIR__ . '/../../Elastic.php');
include(__DIR__ . '/../../config.php');

if (!getenv('ELASTIC_URL')) {
        throw new Exception("need ELASTIC_URL");
}

if (!file_exists($repo_path)) {
    chdir(dirname($repo_path));
    system("git clone https://github.com/g0v-data/hackpad-backup-g0v", $ret);
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
        'term' => ['source' => 'hackpad'],
    ],
    'size' => 0,
    'aggs' => [
        'max_update' => ['max' => ['field' => 'updated_at']],
    ],
]));
$max_update = $obj->aggregations->max_update->value;
error_log("update from timestamp {$max_update} " . date('(c)', $max_update));

$c = 0;
foreach (json_decode(file_get_contents($repo_path . 'pads.json')) as $value) {
    if ($max_update and floor($value->last_backup_time) <= $max_update) {
        continue;
    }
    $padid = $value->padid;
    if (!file_exists($repo_path . $padid . '.html')) {
        throw new Exception("$padid not found");
    }
    $content = file_get_contents($repo_path . $padid . '.html');
    $doc = new DOMDocument;
    @$doc->loadHTML($content);
    $content = $doc->getElementsByTagName('body')->item(0)->nodeValue;
    $value->content = $content;

    Elastic::dbBulkInsert('entry', "hackpad-{$padid}", [
        'url' => 'https://g0v.hackpad.com/' . $padid,
        'title' => $value->title,
        'updated_at' => floor($value->last_backup_time),
        'source' => 'hackpad',
        'id' => $padid,
        'content' => $padid . "\n" . $value->title . "\n" . $value->content,
        'data' => $value,
    ]);
    $c ++;
}
Elastic::dbBulkCommit();
error_log("update {$c} pads");
