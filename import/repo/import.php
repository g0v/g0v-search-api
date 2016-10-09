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

if (!getenv('SEARCH_URL')) {
    throw new Exception("need SEARCH_URL");
}

$curl = curl_init(getenv('SEARCH_URL') . '/entry/_search');
curl_setopt($curl, CURLOPT_POSTFIELDS, '{"query":{"term":{"source":"repo"}},"size":0,"aggs":{"max_update":{"max":{"field":"updated_at"}}}}');
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$ret = curl_exec($curl);
$obj = json_decode($ret);
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
    $curl = curl_init();
    $url = getenv('SEARCH_URL') . '/entry/repo-' . str_replace('/', '-', $key);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
        'source' => 'repo',
        'title' => $value->description,
        'url' => 'https://github.com/' . $key,
        'updated_at' => strtotime($value->updated_at),
        'id' => $key,
        'content' => $value->name ."\n" . $value->description . "\n" . $value->readme,
        'data' => $value,
    )));
    $ret = curl_exec($curl);
    $info = curl_getinfo($curl);
    if (!in_array($info['http_code'], array(200, 201))) {
        throw new Exception($info['http_code'] . $ret);
    }
}
error_log("update {$c} pads");
