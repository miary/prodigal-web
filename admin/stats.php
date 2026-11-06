<?php
/*
 * Stats view for the private admin page: reads the anonymous events saved by
 * api/track.php and renders them as HTML.
 */

declare(strict_types=1);

const STATS_PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];
const STATS_TIMEZONE_NAME = 'America/Los_Angeles';

function statsPeriod(mixed $requested): int
{
    $days = (int) $requested;
    return isset(STATS_PERIODS[$days]) ? $days : 30;
}

function loadStats(PDO $pdo, int $days): array
{
    $zone = new DateTimeZone(STATS_TIMEZONE_NAME);
    $end = new DateTimeImmutable('today', $zone);
    $start = $end->modify('-' . ($days - 1) . ' days');
    $range = [$start->format('Y-m-d'), $end->format('Y-m-d')];

    $rows = function (string $sql, array $extra = []) use ($pdo, $range): array {
        $query = $pdo->prepare($sql);
        $query->execute(array_merge($range, $extra));
        return $query->fetchAll(PDO::FETCH_ASSOC);
    };

    $daily = [];
    foreach ($rows(
        "SELECT day, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors
         FROM stats_events WHERE name = 'pageview' AND day BETWEEN ? AND ? GROUP BY day"
    ) as $row) {
        $daily[$row['day']] = ['views' => (int) $row['views'], 'visitors' => (int) $row['visitors']];
    }

    /* Every day in the period, with zeros where nothing was recorded. */
    $series = [];
    for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
        $key = $day->format('Y-m-d');
        $series[] = ['day' => $day] + ($daily[$key] ?? ['views' => 0, 'visitors' => 0]);
    }

    $byName = function (string $name) use ($pdo, $range): array {
        $query = $pdo->prepare(
            "SELECT label, COUNT(*) AS events, COUNT(DISTINCT visitor) AS visitors
             FROM stats_events WHERE name = ? AND day BETWEEN ? AND ?
             GROUP BY label ORDER BY visitors DESC, events DESC"
        );
        $query->execute(array_merge([$name], $range));
        return $query->fetchAll(PDO::FETCH_ASSOC);
    };

    $signups = $pdo->prepare('SELECT COUNT(*) FROM signups WHERE created_at >= NOW() - INTERVAL ? DAY');
    $signups->execute([$days]);

    return [
        'days' => $days,
        'start' => $start,
        'end' => $end,
        'series' => $series,
        'visitors' => array_sum(array_column($series, 'visitors')),
        'views' => array_sum(array_column($series, 'views')),
        'signups' => (int) $signups->fetchColumn(),
        'sources' => $rows(
            "SELECT referrer AS label, COUNT(DISTINCT visitor) AS visitors
             FROM stats_events WHERE name = 'pageview' AND day BETWEEN ? AND ?
             GROUP BY referrer ORDER BY visitors DESC LIMIT 10"
        ),
        'campaigns' => $rows(
            "SELECT source, campaign, COUNT(DISTINCT visitor) AS visitors
             FROM stats_events
             WHERE name = 'pageview' AND day BETWEEN ? AND ? AND (source IS NOT NULL OR campaign IS NOT NULL)
             GROUP BY source, campaign ORDER BY visitors DESC LIMIT 10"
        ),
        'devices' => $rows(
            "SELECT device AS label, COUNT(DISTINCT visitor) AS visitors
             FROM stats_events WHERE name = 'pageview' AND day BETWEEN ? AND ?
             GROUP BY device ORDER BY visitors DESC"
        ),
        'timezones' => $rows(
            "SELECT timezone AS label, COUNT(DISTINCT visitor) AS visitors
             FROM stats_events WHERE name = 'pageview' AND day BETWEEN ? AND ?
             GROUP BY timezone ORDER BY visitors DESC LIMIT 8"
        ),
        'opens' => $byName('open'),
        'clicks' => $byName('click'),
        'scroll' => $byName('scroll'),
    ];
}

function statsNumber(int $value): string
{
    return number_format($value);
}

/* Visitors per day (or per week for the 12-month view) as an SVG bar chart. */
function renderStatsChart(array $stats): string
{
    $buckets = [];
    $weekly = $stats['days'] > 90;
    foreach ($stats['series'] as $point) {
        $key = $weekly ? $point['day']->modify('monday this week')->format('Y-m-d') : $point['day']->format('Y-m-d');
        $buckets[$key] ??= ['day' => $weekly ? $point['day']->modify('monday this week') : $point['day'], 'visitors' => 0, 'views' => 0];
        $buckets[$key]['visitors'] += $point['visitors'];
        $buckets[$key]['views'] += $point['views'];
    }
    $buckets = array_values($buckets);

    $width = 960;
    $height = 240;
    $left = 40;
    $bottom = 28;
    $top = 12;
    $plotWidth = $width - $left;
    $plotHeight = $height - $top - $bottom;

    $peak = max(1, ...array_column($buckets, 'visitors'));
    $step = (int) max(1, ceil($peak / 4 / (10 ** floor(log10(max(1, $peak / 4))))) * (10 ** floor(log10(max(1, $peak / 4)))));
    $ceiling = (int) (ceil($peak / $step) * $step);

    $count = count($buckets);
    $slot = $plotWidth / $count;
    $gap = $slot > 6 ? 2 : 1;
    $barWidth = min(40, max(1, $slot - $gap));
    $radius = min(4, $barWidth / 2);

    $svg = [];
    for ($tick = 0; $tick <= $ceiling; $tick += $step) {
        $y = round($top + $plotHeight - $tick / $ceiling * $plotHeight, 1);
        $svg[] = "<line class=\"grid\" x1=\"$left\" x2=\"$width\" y1=\"$y\" y2=\"$y\"/>";
        $svg[] = '<text class="tick" x="' . ($left - 8) . "\" y=\"" . ($y + 4) . '" text-anchor="end">' . statsNumber($tick) . '</text>';
    }

    $labelEvery = max(1, (int) ceil($count / 6));
    foreach ($buckets as $index => $bucket) {
        $x = $left + $index * $slot + ($slot - $barWidth) / 2;
        $barHeight = $bucket['visitors'] / $ceiling * $plotHeight;
        $baseline = $top + $plotHeight;
        $date = $bucket['day']->format('M j');
        $tip = ($weekly ? "Week of $date" : $bucket['day']->format('D, M j')) . ': '
            . statsNumber($bucket['visitors']) . ' ' . ($bucket['visitors'] === 1 ? 'visitor' : 'visitors') . ', '
            . statsNumber($bucket['views']) . ' page ' . ($bucket['views'] === 1 ? 'view' : 'views');

        $svg[] = '<g class="bar" data-tip="' . e($tip) . '">';
        $svg[] = '<rect class="hit" x="' . round($left + $index * $slot, 1) . "\" y=\"$top\" width=\"" . round($slot, 1) . "\" height=\"$plotHeight\"/>";
        if ($barHeight > 0) {
            $r = min($radius, $barHeight);
            $x1 = round($x, 1);
            $x2 = round($x + $barWidth, 1);
            $yTop = round($baseline - $barHeight, 1);
            $svg[] = "<path d=\"M$x1 $baseline V" . ($yTop + $r) . " Q$x1 $yTop " . ($x1 + $r) . " $yTop H" . ($x2 - $r)
                . " Q$x2 $yTop $x2 " . ($yTop + $r) . " V$baseline Z\"/>";
        }
        $svg[] = '</g>';

        if ($index % $labelEvery === 0) {
            $svg[] = '<text class="tick" x="' . round($x + $barWidth / 2, 1) . '" y="' . ($height - 8) . "\" text-anchor=\"middle\">$date</text>";
        }
    }
    $svg[] = '<line class="axis" x1="' . $left . '" x2="' . $width . '" y1="' . ($top + $plotHeight) . '" y2="' . ($top + $plotHeight) . '"/>';

    $caption = $weekly ? 'Visitors per week' : 'Visitors per day';

    return '<figure class="chart"><figcaption>' . $caption . '</figcaption>'
        . "<div class=\"chart-box\"><svg viewBox=\"0 0 $width $height\" role=\"img\" aria-label=\"" . e($caption . ', ' . STATS_PERIODS[$stats['days']]) . '">'
        . implode('', $svg) . '</svg><div class="chart-tip" hidden></div></div></figure>';
}

/* A two-column table with a proportional bar behind each count. */
function renderStatsTable(string $title, string $column, array $rows, callable $name, ?int $shareOf = null, string $empty = 'Nothing recorded in this period.'): string
{
    $html = '<section class="panel"><h3>' . e($title) . '</h3>';
    if ($rows === []) {
        return $html . '<p class="hint">' . e($empty) . '</p></section>';
    }

    $max = max(1, ...array_map(fn ($row) => (int) $row['visitors'], $rows));
    $html .= '<table class="ranked"><thead><tr><th scope="col">' . e($column) . '</th><th scope="col" class="num">Visitors</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $visitors = (int) $row['visitors'];
        $share = $shareOf ? ' <span class="share">' . round($visitors / max(1, $shareOf) * 100) . '%</span>' : '';
        $html .= '<tr><td class="wrap">' . $name($row) . '</td><td class="num"><span class="meter" style="--share:'
            . round($visitors / $max * 100, 1) . '"></span><span class="value">' . statsNumber($visitors) . $share . '</span></td></tr>';
    }
    return $html . '</tbody></table></section>';
}

function renderStats(array $stats, array $labels, string $selfUrl): string
{
    $html = '<nav class="periods" aria-label="Period">';
    foreach (STATS_PERIODS as $days => $title) {
        $current = $days === $stats['days'] ? ' aria-current="page"' : '';
        $html .= '<a href="' . e($selfUrl . '?view=stats&days=' . $days) . "\"$current>" . e($title) . '</a>';
    }
    $html .= '</nav>';

    $rate = $stats['visitors'] > 0 ? round($stats['signups'] / $stats['visitors'] * 100, 1) . '%' : '–';
    $html .= '<dl class="figures">'
        . '<div><dt>Visitors</dt><dd>' . statsNumber($stats['visitors']) . '</dd></div>'
        . '<div><dt>Page views</dt><dd>' . statsNumber($stats['views']) . '</dd></div>'
        . '<div><dt>Sign-ups</dt><dd>' . statsNumber($stats['signups']) . '</dd></div>'
        . '<div><dt>Visitors who signed up</dt><dd>' . $rate . '</dd></div>'
        . '</dl>';

    if ($stats['views'] === 0) {
        $html .= '<p class="notice">No visits recorded in this period yet. Counting started when this page was added, and your own browser isn’t counted if you’ve switched that off below.</p>';
    } else {
        $html .= renderStatsChart($stats);
        $html .= '<details class="data-table"><summary>Show the daily numbers as a table</summary><div class="table-wrap"><table><thead><tr><th scope="col">Day</th><th scope="col" class="num">Visitors</th><th scope="col" class="num">Page views</th></tr></thead><tbody>';
        foreach (array_reverse($stats['series']) as $point) {
            $html .= '<tr><td>' . e($point['day']->format('D, M j, Y')) . '</td><td class="num">' . statsNumber($point['visitors']) . '</td><td class="num">' . statsNumber($point['views']) . '</td></tr>';
        }
        $html .= '</tbody></table></div></details>';
    }

    $known = fn (string $group) => fn ($row) => e($labels[$group][$row['label']] ?? $row['label']);
    $plain = fn (string $missing) => fn ($row) => $row['label'] === null ? '<span class="hint">' . e($missing) . '</span>' : e($row['label']);

    $html .= '<div class="panels">';
    $html .= renderStatsTable('Where visitors come from', 'Site', $stats['sources'], $plain('Direct, bookmark or app'), $stats['visitors']);
    $html .= renderStatsTable('Articles opened', 'Article', $stats['opens'], $known('open'), $stats['visitors']);
    $html .= renderStatsTable('Links clicked', 'Link', $stats['clicks'], $known('click'), $stats['visitors']);
    $html .= renderStatsTable('How far people scroll', 'Reached', $stats['scroll'], $known('scroll'), $stats['visitors']);
    $html .= renderStatsTable('Devices', 'Device', $stats['devices'], fn ($row) => $row['label'] === null ? '<span class="hint">Unknown</span>' : e(ucfirst($row['label'])), $stats['visitors']);
    $html .= renderStatsTable('Time zones', 'Time zone', $stats['timezones'], fn ($row) => $row['label'] === null ? '<span class="hint">Unknown</span>' : e(str_replace(['_', '/'], [' ', ' / '], $row['label'])), $stats['visitors']);
    $html .= renderStatsTable('Campaign links', 'Source / campaign', $stats['campaigns'], fn ($row) => e(($row['source'] ?? '–') . ' / ' . ($row['campaign'] ?? '–')), $stats['visitors'], 'No visits from tagged links. Add ?utm_source=youtube&utm_campaign=video-name to a link to see it here.');
    $html .= '</div>';

    $html .= '<section class="about"><h3>How visitors are counted</h3>'
        . '<p class="hint">No cookies are used and no IP addresses are stored. A visitor is one browser on one day, so someone who comes back on another day is counted again. Percentages are shares of all visitors in the period. Days follow California time. Sign-ups come from the sign-up list, counted over the same number of days.</p>'
        . '<p class="selected" id="own-visits" aria-live="polite"></p>'
        . '<button class="plain" type="button" id="own-visits-toggle" hidden></button></section>';

    return $html;
}
