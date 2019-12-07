<?php

$source_directory = $argv[1];

function toMinutes($hours_minutes)
{
    $hour = intval(substr($hours_minutes, 0, 1), 10);
    $minutes = intval(substr($hours_minutes, 1, 3), 10);

    $arrival_time = $hour * 60 + $minutes;
    return $arrival_time;
}

function keyFromPath($path)
{
    list($number, $date) = explode('_', $path);
    $year = substr($date, 0, 4);
    $month = substr($date, 4, 2);
    $day = substr($date, 6, 2);
    return "$year-$month-$day";
}

function median($list)
{
    if (count($list) === 0) {
        throw new Exception('cannot take median on emty list');
    }

    asort($list);
    $list = array_values($list);
    $middle = count($list) / 2;

    if ($middle === round($middle)) {
        $median = $list[$middle];
    } else {
        $median = average([$list[floor($middle)], $list[ceil($middle)]]);
    }

    return $median;
}

function percentile($list, $percentile)
{
    asort($list);
    $list = array_values($list);

    $index = count($list) * $percentile;
    if ($index === round($index)) {
        $p = $list[$index];
    } else {
        // interpolate
        $p = $list[floor($index)];
        // TODO linear interpolation
    }

    return $p;
}

function average($list)
{
    return array_sum($list) / count($list);
}

function recordsFromDirectory($directory)
{
    $records = [];
    foreach (scandir($directory) as $path) {
        if (in_array($path, ['.', '..'])) {
            continue;
        }

        $key = keyFromPath($path);
        $records[$key] = file_get_contents($directory . '/' . $path);
    }

    return $records;
}

$records = recordsFromDirectory($source_directory);

class Line
{
    public function __construct($line)
    {
        $this->raw = $line;
    }

    public function stop()
    {
        return trim(substr($this->raw, 2, 3));
    }

    public function scheduledDeparture()
    {
        return trim(substr($this->raw, 19, 4));
    }

    public function scheduledArrival()
    {
        return trim(substr($this->raw, 10, 4));
    }

    public function actualDeparture()
    {
        return trim(substr($this->raw, 30, 5));
    }

    public function actualArrival()
    {
        return trim(substr($this->raw, 24, 5));
    }

    public function comments()
    {
        return trim(substr($this->raw, 37));
    }

    public function missingData()
    {
        return $this->actualArrival() === '';
    }

    public function getData()
    {
        $data = [
            'stop' => $this->stop(),
        ];

        if (!($data['missing_data'] = $this->missingData())) {
            $data['scheduled_arrival'] = $this->scheduledArrival();
            $data['actual_arrival'] = $this->actualArrival();
            $data['scheduled_departure'] = $this->scheduledDeparture();
            $data['actual_departure'] = $this->actualDeparture();
        }

        return $data;
    }
}

class Record
{
    private $raw;

    private $lines;

    public function __construct($record)
    {
        $this->raw = $record;
        $this->lines = explode("\n", trim($this->raw));
    }

    public function origin()
    {
        $line = $this->lines()[0];
        return [
            'stop' => $line->stop(),
            'time' => $line->scheduledDeparture(),
        ];
    }

    public function lines()
    {
        return array_values(array_filter(array_map(function ($line) {
            return new Line($line);
        }, array_slice($this->lines, 10)), function ($line) {
            return $line->stop() !== 'V';
        }));
    }

    public function destination()
    {
        $line = $this->lastLine();
        return [
            'stop' => $line->stop(),
            'time' => $line->scheduledArrival(),
        ];
    }

    public function route()
    {
        return trim(substr($this->lines[0], 2));
    }

    public function delay()
    {
        // time in minutes
        $scheduled = toMinutes($this->lastLine()->scheduledArrival());
        $actual = toMinutes($this->actualArrival());

        $delay = $actual - $scheduled;

        return $delay;
    }

    private function actualArrival()
    {
        return $this->lastLine()->actualArrival();
    }

    public function lastStop()
    {
        return $this->lastLine()->stop();
    }

    public function wasCancelled()
    {
        return $this->lastLine()->comments() === "Station Stop Canceled";
    }

    public function missingData()
    {
        return $this->lastLine()->missingData();
    }

    public function getData()
    {
        return [
            'cancelled' => $this->wasCancelled(),
            'missing_data' => $this->missingData(),
            'delay' => $this->delay(),
            'actual_arrival' => $this->actualArrival(),
            'lines' => array_map(function ($line) {
                return $line->getData();
            }, $this->lines()),
            'raw' => $this->raw,
        ];
    }

    private function lastLine()
    {
        $line = $this->lines[count($this->lines) - 1];
        return new Line($line);
    }
}

class RecordSet
{
    private $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function stats()
    {
        $valid_records = array_filter($this->records, function ($record) {
            return !($record->wasCancelled() || $record->missingData());
        });

        $delays = array_values(array_map(function ($record) {
            return $record->delay();
        }, $valid_records));

        $stats = [];

        $stats['cancellations'] = count(array_filter($this->records, function ($record) {
            return $record->wasCancelled();
        }));

        $stats['missing_data'] = count(array_filter($this->records, function ($record) {
            return $record->missingData();
        }));

        $stats['valid_records'] = count($valid_records);

        if ($delays) {
            $stats['median'] = median($delays);
            $stats['p90'] = percentile($delays, .90);
        }

        return $stats;
    }
}

$data = [
    'name' => 'Train ' . basename($source_directory),
    'records' => array_map(function ($record) {
      return (new Record($record))->getData();
    }, $records),
];

if ($records) {
    $record = new Record($records[array_keys($records)[0]]);
    $data['origin'] = $record->origin();
    $data['destination'] = $record->destination();
    $data['route'] = $record->route();

    $set = new RecordSet(array_values(array_map(function ($data) {return new Record($data);}, $records)));
    $data['stats'] = $set->stats();
}

echo json_encode($data);
