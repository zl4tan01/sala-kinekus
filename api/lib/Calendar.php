<?php
declare(strict_types=1);

/**
 * Z kalendára von idú len dátumy. Žiadny názov, opis, účastník ani iný text udalosti.
 */
function sala_occupied_from_ics(string $ics, string $from, string $untilExclusive): array
{
    $set = [];
    foreach (sala_ics_events($ics) as $event) {
        foreach (sala_event_days($event, $untilExclusive) as $day) {
            if ($day >= $from && $day < $untilExclusive) {
                $set[$day] = true;
            }
        }
    }
    $days = array_keys($set);
    sort($days);
    return $days;
}

/**
 * Položky z Google Calendar API (už rozbalené singleEvents).
 * Celodenná udalosť má start.date. Udalosť s dateTime deň neobsadzuje.
 *
 * @param list<array<string,mixed>> $items
 * @return list<string>
 */
function sala_occupied_from_google_items(array $items, string $from, string $untilExclusive): array
{
    $set = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (($item['status'] ?? '') === 'cancelled') {
            continue;
        }
        $start = is_array($item['start'] ?? null) ? $item['start'] : [];
        $end = is_array($item['end'] ?? null) ? $item['end'] : [];
        if (!empty($start['dateTime']) || empty($start['date']) || !is_string($start['date'])) {
            continue;
        }
        $startDate = sala_date_only($start['date']);
        if ($startDate === null) {
            continue;
        }
        $endDate = null;
        if (!empty($end['date']) && is_string($end['date']) && empty($end['dateTime'])) {
            $endDate = sala_date_only($end['date']);
        }
        if ($endDate === null) {
            $endDate = sala_add_days($startDate, 1);
        }
        foreach (sala_each_date($startDate, $endDate) as $day) {
            if ($day >= $from && $day < $untilExclusive) {
                $set[$day] = true;
            }
        }
    }
    $days = array_keys($set);
    sort($days);
    return $days;
}

/** @return list<string> */
function sala_mock_occupied(string $today): array
{
    $dates = [];
    $cursor = new DateTimeImmutable($today);
    $cursor = $cursor->modify('+1 day');
    for ($i = 0; $i < 14; $i++) {
        if ($cursor->format('N') === '3') {
            $dates[] = $cursor->format('Y-m-d');
            $dates[] = $cursor->modify('+1 day')->format('Y-m-d');
            $dates[] = $cursor->modify('+2 days')->format('Y-m-d');
            break;
        }
        $cursor = $cursor->modify('+1 day');
    }

    $saturday = (new DateTimeImmutable($today))->modify('+14 days');
    while ($saturday->format('N') !== '6') {
        $saturday = $saturday->modify('+1 day');
    }
    $dates[] = $saturday->format('Y-m-d');

    $monday = (new DateTimeImmutable($today))->modify('+30 days');
    while ($monday->format('N') !== '1') {
        $monday = $monday->modify('+1 day');
    }
    $dates[] = $monday->format('Y-m-d');
    $dates[] = $monday->modify('+1 day')->format('Y-m-d');
    $dates[] = $monday->modify('+2 days')->format('Y-m-d');

    $dates = array_values(array_unique($dates));
    sort($dates);
    return array_values(array_filter($dates, static fn (string $d): bool => $d >= $today));
}

/**
 * @return list<array{timed:bool,cancelled:bool,start:?string,end:?string,rrule:?array,exdates:list<string>}>
 */
function sala_ics_events(string $ics): array
{
    $ics = str_replace("\r\n", "\n", $ics);
    $ics = str_replace("\r", "\n", $ics);
    $ics = preg_replace("/\n[ \t]/", '', $ics) ?? $ics;

    if (!preg_match_all('/BEGIN:VEVENT\n(.*?)(?:\n)?END:VEVENT/s', $ics, $matches)) {
        return [];
    }

    $events = [];
    foreach ($matches[1] as $block) {
        $start = null;
        $end = null;
        $startTimed = false;
        $endTimed = false;
        $cancelled = false;
        $rrule = null;
        $exdates = [];

        foreach (explode("\n", $block) as $line) {
            if ($line === '') {
                continue;
            }
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $left = substr($line, 0, $colon);
            $value = sala_ics_unescape(substr($line, $colon + 1));
            $pieces = explode(';', $left);
            $name = strtoupper($pieces[0]);
            $params = [];
            foreach (array_slice($pieces, 1) as $piece) {
                $kv = explode('=', $piece, 2);
                $params[strtoupper($kv[0])] = $kv[1] ?? '';
            }

            if ($name === 'STATUS' && strtoupper($value) === 'CANCELLED') {
                $cancelled = true;
            } elseif ($name === 'RRULE') {
                $rrule = sala_parse_rrule($value);
            } elseif ($name === 'EXDATE') {
                foreach (explode(',', $value) as $part) {
                    $date = sala_date_only($part);
                    if ($date !== null && sala_is_all_day($part, $params)) {
                        $exdates[] = $date;
                    }
                }
            } elseif ($name === 'DTSTART') {
                $startTimed = !sala_is_all_day($value, $params);
                $start = sala_date_only($value);
            } elseif ($name === 'DTEND') {
                $endTimed = !sala_is_all_day($value, $params);
                $end = sala_date_only($value);
            }
        }

        $timed = $startTimed || $endTimed;
        $events[] = [
            'timed' => $timed,
            'cancelled' => $cancelled,
            'start' => $start,
            'end' => $end,
            'rrule' => $rrule,
            'exdates' => $exdates,
        ];
    }

    return $events;
}

/** @param array{timed:bool,cancelled:bool,start:?string,end:?string,rrule:?array,exdates:list<string>} $event
 *  @return list<string>
 */
function sala_event_days(array $event, string $windowEnd): array
{
    if ($event['cancelled'] || $event['timed'] || $event['start'] === null) {
        return [];
    }

    $start = $event['start'];
    $end = $event['end'] ?? sala_add_days($start, 1);
    if ($end <= $start) {
        $end = sala_add_days($start, 1);
    }
    $span = sala_day_span($start, $end);
    if ($span < 1) {
        return [];
    }

    $starts = [$start];
    if (is_array($event['rrule'])) {
        $starts = sala_occurrence_starts($start, $event['rrule'], $windowEnd);
    }

    $ex = array_flip($event['exdates']);
    $days = [];
    foreach ($starts as $occurrence) {
        if (isset($ex[$occurrence])) {
            continue;
        }
        foreach (sala_each_date($occurrence, sala_add_days($occurrence, $span)) as $day) {
            $days[] = $day;
        }
    }
    return $days;
}

function sala_day_span(string $start, string $endExclusive): int
{
    $a = new DateTimeImmutable($start);
    $b = new DateTimeImmutable($endExclusive);
    if ($b <= $a) {
        return 0;
    }
    return (int) $a->diff($b)->days;
}

/** @return array<string,string> */
function sala_parse_rrule(string $value): array
{
    $rule = [];
    foreach (explode(';', $value) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $rule[strtoupper($kv[0])] = strtoupper($kv[1]);
        }
    }
    return $rule;
}

/** @param array<string,string> $rule
 *  @return list<string>
 */
function sala_occurrence_starts(string $start, array $rule, string $windowEnd): array
{
    $freq = $rule['FREQ'] ?? '';
    $interval = max(1, (int) ($rule['INTERVAL'] ?? 1));
    $count = isset($rule['COUNT']) ? (int) $rule['COUNT'] : null;
    $until = isset($rule['UNTIL']) ? sala_date_only($rule['UNTIL']) : null;
    $starts = [];

    if ($freq === 'WEEKLY' && !empty($rule['BYDAY'])) {
        $map = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
        $weekdays = [];
        foreach (explode(',', $rule['BYDAY']) as $token) {
            $token = preg_replace('/^[+-]?\d+/', '', $token) ?? $token;
            if (isset($map[$token])) {
                $weekdays[] = $map[$token];
            }
        }
        $origin = new DateTimeImmutable($start);
        $monday = $origin->modify('-' . ((int) $origin->format('N') - 1) . ' days');
        for ($week = 0; $week < 160; $week++) {
            if ($count !== null && count($starts) >= $count) {
                break;
            }
            $weekStart = $monday->modify('+' . ($week * $interval) . ' weeks');
            if ($weekStart->format('Y-m-d') > $windowEnd && $week > 0) {
                break;
            }
            foreach ($weekdays as $n) {
                $date = $weekStart->modify('+' . ($n - 1) . ' days')->format('Y-m-d');
                if ($date < $start) {
                    continue;
                }
                if ($until !== null && $date > $until) {
                    continue;
                }
                $starts[] = $date;
                if ($count !== null && count($starts) >= $count) {
                    break;
                }
            }
        }
        $starts = array_values(array_unique($starts));
        sort($starts);
        return $starts;
    }

    $step = match ($freq) {
        'DAILY' => '+' . $interval . ' days',
        'WEEKLY' => '+' . $interval . ' weeks',
        'MONTHLY' => '+' . $interval . ' months',
        'YEARLY' => '+' . $interval . ' years',
        default => null,
    };

    if ($step === null) {
        return [$start];
    }

    $date = $start;
    for ($n = 0; $n < 800; $n++) {
        if ($count !== null && $n >= $count) {
            break;
        }
        if ($until !== null && $date > $until) {
            break;
        }
        if ($n > 0 && $date >= $windowEnd) {
            break;
        }
        $starts[] = $date;
        $date = (new DateTimeImmutable($date))->modify($step)->format('Y-m-d');
    }

    return $starts;
}

function sala_is_all_day(string $value, array $params): bool
{
    $type = strtoupper($params['VALUE'] ?? '');
    if ($type === 'DATE') {
        return true;
    }
    if ($type === 'DATE-TIME') {
        return false;
    }
    return !str_contains($value, 'T');
}

function sala_date_only(string $value): ?string
{
    $value = trim($value);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1
        || preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m) === 1) {
        // recognized below
    } else {
        return null;
    }
    $iso = $m[1] . '-' . $m[2] . '-' . $m[3];
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    if ($dt === false || $dt->format('Y-m-d') !== $iso) {
        return null;
    }
    return $iso;
}

function sala_ics_unescape(string $value): string
{
    return strtr($value, [
        '\\n' => "\n",
        '\\N' => "\n",
        '\\,' => ',',
        '\\;' => ';',
        '\\\\' => '\\',
    ]);
}
