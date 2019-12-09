<?php

$source_directory = $argv[1];

function toMinutes($hours_minutes)
{
    $hour = intval(substr($hours_minutes, 0, -3), 10);
    $hour %= 12;
    $hour -= 1;

    $minutes = intval(substr($hours_minutes, strlen($hours_minutes) - 3, strlen($hours_minutes) - 1), 10);

    $meridiem = strtolower($hours_minutes[strlen($hours_minutes) - 1]);
    if ($meridiem === 'p') {
        $hour += 12;
    }

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

    if (count($list) % 2 === 1) {
        // odd, can take middle
        $median = $list[(count($list) - 1) / 2];
    } else {
        // even, average the left and right
        $left = $list[count($list) / 2 - 1];
        $right = $list[count($list) / 2];
        $median = average([$left, $right]);
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
        return $this->read(2, 3);
    }

    public function scheduledDepartureDay()
    {
        $day = $this->read(16, 3);

        if ($day !== null) {
            $day = intval($day, 10);
        }

        return $day;
    }

    public function scheduledDepartureTime()
    {
        return $this->read(19, 5);
    }

    public function scheduledArrivalDay()
    {
        $day = $this->read(7, 3);

        if ($day !== null) {
            $day = intval($day, 10);
        }

        return $day;
    }

    public function scheduledArrivalTime()
    {
        return $this->read(10, 5);
    }

    public function actualDepartureDay()
    {
        $day = $this->scheduledDepartureDay();

        if ($day !== null && $this->rolledOverDay($this->scheduledDepartureTime(), $this->actualDeparture())) {
            // day rolled over
            $day += 1;
        }

        return $day;
    }

    private function rolledOverDay($scheduled, $actual)
    {
        if ($scheduled === null || $actual === null) {
            return false;
        }

        $rolled_over_day = toMinutes($scheduled) >= toMinutes('700P') && toMinutes($actual) <= toMinutes('700A');

        return $rolled_over_day;
    }

    public function actualDeparture()
    {
        return $this->read(31, 5);
    }

    public function actualArrivalDay()
    {
        $day = $this->scheduledArrivalDay();

        if ($day !== null && $this->rolledOverday($this->scheduledArrivalTime(), $this->actualArrival())) {
            // day rolled over
            $day += 1;
        }

        return $day;
    }

    public function actualArrival()
    {
        return $this->read(25, 5);
    }

    public function comments()
    {
        return $this->read(37, 0);
    }

    public function read($start, $length)
    {
        $value = trim(substr($this->raw, $start, $length));
        if ($value === '' || $value === '*') {
            return null;
        }
        return $value;
    }

    public function missingData()
    {
        return $this->actualArrival() === null || $this->actualDeparture() === null;
    }

    public function getData()
    {
        $data = [
            'stop' => $this->stop(),
            'raw' => $this->raw,
        ];

        $data['missing_data'] = $this->missingData();

        $data['scheduled_arrival'] = $this->scheduledArrivalTime();
        $data['scheduled_arrival_day'] = $this->scheduledArrivalDay();
        $data['actual_arrival'] = $this->actualArrival();
        $data['actual_arrival_day'] = $this->actualArrivalDay();
        $data['scheduled_departure'] = $this->scheduledDepartureTime();
        $data['scheduled_departure_day'] = $this->scheduledDepartureDay();
        $data['actual_departure'] = $this->actualDeparture();
        $data['actual_departure_day'] = $this->actualDepartureDay();

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
        $this->lines = explode("\r\n", trim($this->raw));
    }

    public function origin()
    {
        $line = $this->lines()[0];
        return [
            'stop' => $line->stop(),
            'time' => $line->scheduledDepartureTime(),
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
            'time' => $line->scheduledArrivalTime(),
        ];
    }

    public function route()
    {
        return trim(substr($this->lines[0], 2));
    }

    public function delay()
    {
        $line = $this->lastLine();

        $destination_scheduled_arrival = $line->scheduledArrivalTime();
        $destination_actual_arrival = $line->actualArrival();

        if ($destination_scheduled_arrival === null || $destination_actual_arrival === null) {
            return null;
        }

        // time in minutes
        $scheduled = toMinutes($destination_scheduled_arrival) + 24 * 60 * $line->scheduledArrivalDay();
        $actual = toMinutes($destination_actual_arrival) + 24 * 60 * $line->actualArrivalDay();

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

    public function experiencedServiceDisruption()
    {
        return $this->lines[0] === '* THIS TRAIN EXPERIENCED A SERVICE DISRUPTION.';
    }

    public function missingData()
    {
        return $this->lastLine()->missingData();
    }

    public function getData()
    {
        return [
            'cancelled' => $this->wasCancelled(),
            'experienced_service_disruption' => $this->experiencedServiceDisruption(),
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
        $valid_records = $this->records;

        $delays = array_values(array_map(function ($record) {
            return $record->delay();
        }, $valid_records));

        $stats = [];

        $stats['cancellations'] = count(array_filter($this->records, function ($record) {
            return $record->wasCancelled();
        }));

        $stats['service_disruptions'] = count(array_filter($this->records, function ($record) {
            return $record->experiencedServiceDisruption();
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
