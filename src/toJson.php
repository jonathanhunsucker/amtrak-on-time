<?php

$source_directory = $argv[1];

function keyFromPath($path)
{
    list($number, $date) = explode('_', $path);
    $year = substr($date, 0, 4);
    $month = substr($date, 4, 2);
    $day = substr($date, 6, 2);
    return "$year-$month-$day";
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
        $line = $this->lines[10];
        return [
            'stop' => substr($line, 2, 3),
            'time' => trim(substr($line, 19, 4)),
            //'day' => trim(substr($line, 17, 2)),
        ];
    }

    public function destination()
    {
        $line = $this->lines[count($this->lines) - 1];
        return [
            'stop' => substr($line, 2, 3),
            'time' => trim(substr($line, 10, 4)),
        ];
    }

    public function route()
    {
        return trim(substr($this->lines[0], 2));
    }
}

$data = [
    'name' => 'Train ' . basename($source_directory),
    'records' => (object) $records,
];

if ($records) {
    $record = new Record($records[array_keys($records)[0]]);
    $data['origin'] = $record->origin();
    $data['destination'] = $record->destination();
    $data['route'] = $record->route();
}

echo json_encode($data);
