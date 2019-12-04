<?php

function toMinutes($hours_minutes)
{
    $hour = intval(substr($hours_minutes, 0, 1), 10);
    $minutes = intval(substr($hours_minutes, 1, 3), 10);

    $arrival_time = $hour * 60 + $minutes;
    return $arrival_time;
}

while ($line = trim(fgets(STDIN))) {
    $delay = toMinutes($line) - toMinutes('852');
    echo $delay . "\n";
}
