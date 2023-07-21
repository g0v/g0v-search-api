<?php

date_default_timezone_set('Asia/Taipei');
$repo_path = "/tmp/g0v-repo-info/";

if (!file_exists($repo_path)) {
    chdir(dirname($repo_path));
    system("git clone https://github.com/jmehsieh/g0v-repo-info", $ret);
} else {
    chdir($repo_path);
    system("git pull", $ret);
}
if ($ret !== 0) {
    throw new Exception("git pull failed");
}

include(__DIR__ . '/../../Elastic.php');
include(__DIR__ . '/../../config.php');

if (!getenv('ELASTIC_URL')) {
    throw new Exception("need ELASTIC_URL");
}


$prefix = getenv('ELASTIC_PREFIX');
$obj = Elastic::dbQuery("/{$prefix}entry/_search", "GET", json_encode([
    'query' => [
        'term' => ['source' => 'repo'],
    ],
    'size' => 0,
    'aggs' => [
        'max_update' => ['max' => ['field' => 'updated_at']],
    ],
]));
$max_update = $obj->aggregations->max_update->value;
error_log("update from timestamp {$max_update} " . date('(c)', $max_update));

$c = 0;
foreach (json_decode(file_get_contents($repo_path . 'repo_info.json')) as $key => $value) {
    if ($max_update and strtotime($value->updated_at) <= $max_update) {
        continue;
    }
    $c ++;

    if (property_exists($value, 'readme_filename')) {
        $value->readme = file_get_contents($repo_path . $value->readme_filename);
    } else {
        $value->readme = '';
    }

    $k = str_replace('/', '-', $key);
    Elastic::dbBulkInsert('entry', "repo-{$k}", [
        'source' => 'repo',
        'title' => $value->description,
        'url' => 'https://github.com/' . $key,
        'updated_at' => strtotime($value->updated_at),
        'id' => $key,
        'content' => $value->name ."\n" . $value->description . "\n" . $value->readme,
        'data' => $value,
    ]);
}
Elastic::dbBulkCommit();
error_log("update {$c} pads");
