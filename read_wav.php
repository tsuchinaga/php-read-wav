<?php

ini_set('memory_limit', '256M');

//$file = "./files/wav/環境音パ30.wav";

const SAMPLE_PER_SEC = 44100;
const MAX_VALUE = 32767;

foreach (glob("./files/wav/*.wav") as $file) {
    $contents = file_get_contents($file);
    $offset = 0;

    $riff = null;
    $fmt = null;
    $data = null;
    $oc = [];
    while (1) {
        switch ($type = get_string($contents, $offset, 4)) {
            case "RIFF":
                $riff = new Header($contents, $offset);
                $offset = $riff->next;
                break;
            case "fmt ":
                $fmt = new Fmt($contents, $offset);
                $offset = $fmt->next;
                break;
            case "data":
                if ($fmt == null) break; // 先にfmtが読めてないとdataが読めない
                $data = new Data($contents, $offset, $fmt->bits_per_sample, $fmt->channel);
                $offset = $data->next;
                break;
            case "":
                break 2;
            default:
                $c = new OtherChunk($contents, $offset);
                $oc[] = $c;
                $offset = $c->next;
                break;
        }
    }
    $flat = $data->flat();
    echo "$file," . speak_count($flat) . "\n";
}

return;

function get_string(string $contents, int $offset, int $size)
{
    return substr($contents, $offset, $size);
}

function get_number(string $contents, int $offset, int $size)
{
    if ($size !== 2 && $size !== 4) return 0;
    return $val = unpack($size === 2 ? "v" : "V", substr($contents, $offset, $size))[1];
}

function get_hex(string $contents, int $offset, int $size)
{
    return bin2hex(substr($contents, $offset, $size));
}

class Header
{
    var $chunk_id;
    var $chunk_size;
    var $form_type;
    var $start;
    var $next;

    function __construct(string $contents, int $start)
    {
        $offset = $start;
        $this->start = $start;

        $this->chunk_id = get_string($contents, $offset, 4);
        $offset += 4;

        $this->chunk_size = get_number($contents, $offset, 4);
        $offset += 4;

        $this->form_type = get_string($contents, $offset, 4);
        $offset += 4;

        $this->next = $offset;
    }
}

class Fmt
{
    var $chunk_id;
    var $chunk_size;
    var $wave_format_type;
    var $channel;
    var $sample_per_sec;
    var $byte_per_sec;
    var $block_size;
    var $bits_per_sample;
    var $start;
    var $next;

    function __construct(string $contents, int $start)
    {
        $offset = $start;
        $this->start = $start;

        $this->chunk_id = get_string($contents, $offset, 4);
        $offset += 4;

        $this->chunk_size = get_number($contents, $offset, 4);
        $offset += 4;

        $this->wave_format_type = get_number($contents, $offset, 2);
        $offset += 2;

        $this->channel = get_number($contents, $offset, 2);
        $offset += 2;

        $this->sample_per_sec = get_number($contents, $offset, 4);
        $offset += 4;

        $this->byte_per_sec = get_number($contents, $offset, 4);
        $offset += 4;

        $this->block_size = get_number($contents, $offset, 2);
        $offset += 2;

        $this->bits_per_sample = get_number($contents, $offset, 2);
        $offset += 2;

        $this->next = $offset;
    }
}

class Data
{

    var $chunk_id;
    var $chunk_size;
    var $bit_per_sample;
    var $channel;
    var $data = [];
    var $start;
    var $next;

    function __construct(string $contents, int $start, int $bit_per_sample, int $channel)
    {
        $offset = $start;
        $this->start = $start;
        $this->bit_per_sample = $bit_per_sample;
        $this->channel = $channel;

        $this->chunk_id = get_string($contents, $offset, 4);
        $offset += 4;

        $this->chunk_size = get_number($contents, $offset, 4);
        $offset += 4;

        if ($bit_per_sample === 8) $length = 1;
        else if ($bit_per_sample === 16) $length = 2;
        else return;

        $total_size = 0;
        while ($total_size < $this->chunk_size) {
            $data = [];

            // 左側
            $data[] = get_number($contents, $offset, $length);
            $offset += $length;
            $total_size += $length;

            // 右側があれば
            if ($channel === 2) {
                $data[] = get_number($contents, $offset, $length);
                $offset += $length;
                $total_size += $length;
            }

            $this->data[] = $data;
        }

        $this->next = $offset;
    }

    function flat()
    {
        $data = [];
        foreach ($this->data as $d) {
            $d = count($d) === 1 ? $d[0] : ($d[0] + $d[1]) / 2;
            $data[] = $d - 32768;
        }
        return $data;
    }
}

class OtherChunk
{
    var $chunk_id;
    var $chunk_size;
    var $hex;
    var $start;
    var $next;

    function __construct(string $contents, int $start)
    {
        $offset = $start;
        $this->start = $start;

        $this->chunk_id = get_string($contents, $offset, 4);
        $offset += 4;

        $this->chunk_size = get_number($contents, $offset, 4);
        $offset += 4;

        $this->hex = get_hex($contents, $offset, $this->chunk_size);
        $offset += $this->chunk_size;

        $this->next = $offset;
    }
}

function speak_count(array $data)
{
    $split = 30;
    $under_border = 1;
    $max = [];

    // 一定間隔毎の最大値を取り出す
    foreach ($data as $i => $d) {
        $idx = (int)(($i + 1) / (SAMPLE_PER_SEC / $split));
        if (!isset($idx)) $max[$idx] = 0;
        if ($max[$idx] < $d) $max[$idx] = $d;
    }

    $count = 0;
    $is_max = true;
    foreach ($max as $n) {
//        echo "$n\n";
        if ($n === MAX_VALUE - $under_border) {
            if (!$is_max) $count++;
            $is_max = true;
        } else {
            $is_max = false;
        }
    }

    return $count;
}
