<?php

date_default_timezone_set('Asia/Taipei');
$repo_path = "/tmp/hackfoldr-backup-g0v/";

if (!getenv('SEARCH_URL')) {
    throw new Exception("need SEARCH_URL");
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

$curl = curl_init(getenv('SEARCH_URL') . '/entry/_search');
curl_setopt($curl, CURLOPT_POSTFIELDS, '{"query":{"term":{"source":"hackfoldr"}},"size":0,"aggs":{"max_update":{"max":{"field":"updated_at"}}}}');
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$ret = curl_exec($curl);
$obj = json_decode($ret);
$max_update = $obj->aggregations->max_update->value;
error_log("update from timestamp {$max_update} " . date('(c)', $max_update));

$c = 0;
foreach (json_decode(file_get_contents($repo_path . 'foldrs.json')) as $key => $value) {
    // TODO: check updated_at
    //if ($max_update and floor($value->last_backup_time) <= $max_update) {
    //    continue;
    //}
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

    $curl = curl_init();
    $url = getenv('SEARCH_URL') . '/entry/hackfoldr-' . $key;
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
        'url' => $value->url,
        'title' => $title,
        'updated_at' => 0,
        'source' => 'hackfoldr',
        'id' => $key,
        'content' => $content,
        'data' => $value,
    )));
    $ret = curl_exec($curl);
    $info = curl_getinfo($curl);
    $c ++;
    if (!in_array($info['http_code'], array(200, 201))) {
        throw new Exception($info['http_code'] . $ret);
    }
}
error_log("update {$c} pads");
