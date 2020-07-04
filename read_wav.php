<?php

$header = [ // 項目 => [length, format]
    "riff" => [4, "string"],
    "size" => [4, "number"],
    "format" => [4, "string"],
    "chunk_id" => [4, "string"],
    "format_chunk" => [4, "hex"],
    "wav_format" => [2, "number"],
    "channel_count" => [2, "number"],
    "sample_rate" => [4, "number"],
    "byte_per_sec" => [4, "number"],
    "byte_per_sample_x_channel" => [2, "number"],
    "bit_per_sample" => [2, "number"],
    "expansion" => [16, "hex"],
];

$file = "./960aa15f29760f4604b120c65eb94a1f.wav";
$d = file_get_contents($file);

// 初期化
$offset = 0;
$wav_format = 0;
$channel_count = 0;
$bit_per_sample = 0;
foreach ($header as $key => $params) {
    if ($key === "expansion" && $wav_format === 1) continue;

    list($length, $format) = $params;
    $val = substr($d, $offset, $length);
    switch ($format) {
        case "number":
            switch ($length) {
                case 2:
                    $val = unpack("v", $val)[1];
                    break;
                case 4:
                    $val = unpack("V", $val)[1];
                    break;
            }
            break;
        case "hex":
            $val = bin2hex($val);
            break;
    }
    var_dump([$key, $val]);

    if ($key === "wav_format") $wav_format = $val;
    if ($key === "channel_count") $channel_count = $val;
    if ($key === "bit_per_sample") $bit_per_sample = $val;

    $offset += $length;
}

//return;

// ここからデータ
$data_id = substr($d, $offset, 4);
$offset += 4;
var_dump(["data_id", $data_id]);

$data_size = unpack("V", substr($d, $offset, 4))[1];
$offset += 4;
var_dump(["data_size", $data_size]);

$length = 0;
if ($bit_per_sample === 8) $length = 1;
else if ($bit_per_sample === 16) $length = 2;
else return;

$total_size = 0;
while ($total_size < $data_size) {
    $total_size += $length;
    for ($i = 0; $i < $channel_count; $i++) {
        $data = unpack("v", substr($d, $offset, $length))[1];
//        var_dump([$total_size, $data_size - $total_size, $data, $i === 0 ? "L" : "R"]);
        print("$data\n");
        $offset += $length;
    }
}
