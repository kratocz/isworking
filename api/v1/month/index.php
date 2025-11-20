<?php
require_once(dirname(__FILE__) . "/CalendarTools.php");

$currentDateTime = new DateTime();
$startDateString = $currentDateTime->format('Y-m-01');

$timezoneString = getenv('TZ');
if ($timezoneString) {
    date_default_timezone_set($timezoneString);
}

// Connect to Redis
$redis = new Redis();
$redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));

/**
 * @deprecated replaced by CalendarTools::getPercentagesForDaysInMonth(...)
 * @param $dayInMonth
 * @param $totalDaysInMonth
 * @return float|int
 */
function getPercentageByDayInMonth($dayInMonth, $totalDaysInMonth) {
    $percentage = $dayInMonth / ($totalDaysInMonth - 2);
    if ($percentage < 0) {
        $percentage = 0;
    }
    if ($percentage > 1) {
        $percentage = 1;
    }
    return $percentage;
}

/**
 * Call Toggl GET API with Redis caching
 * @param string $endpoint API endpoint (e.g., "/api/v9/me")
 * @param int $ttl Cache TTL in seconds
 * @return mixed Decoded JSON response
 */
function callGetApi($endpoint, $ttl)
{
    global $redis;

    // Check cache first
    $cacheKey = "toggl:$endpoint";
    $cached = $redis->get($cacheKey);
    if ($cached !== false) {
        $decoded = json_decode($cached);
        // Check if JSON decode was successful (even if result is null)
        if (json_last_error() === JSON_ERROR_NONE) {
            error_log("Toggl API cache HIT: $endpoint");
            return $decoded;
        } else {
            error_log("Toggl API cache HIT but invalid JSON, removing: $endpoint [raw: " . substr($cached, 0, 100) . "]");
            $redis->del($cacheKey);
        }
    }

    // Cache miss - call API
    error_log("Toggl API cache MISS - calling API: $endpoint");
    $url = "https://api.track.toggl.com$endpoint";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
    ]);
    curl_setopt($ch, CURLOPT_USERNAME, getenv('TOGGL_API_TOKEN'));
    curl_setopt($ch, CURLOPT_PASSWORD, "api_token");

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Toggl API curl error for $endpoint: $error");
        die("Toggl API error: " . $error);
    }
    curl_close($ch);

    // Cache all HTTP 200 responses (including null/empty - they are valid responses)
    if ($httpCode === 200) {
        $redis->setex($cacheKey, $ttl, $result);
        error_log("Toggl API response cached: $endpoint (HTTP $httpCode)");
    } else {
        error_log("Toggl API response NOT cached: $endpoint (HTTP $httpCode - non-200 status)");
    }

    return json_decode($result);
}

/**
 * Call Toggl POST API (no caching)
 * @param string $endpoint API endpoint
 * @param array $postBody POST request body
 * @return mixed Decoded JSON response
 */
function callPostApi($endpoint, $postBody)
{
    error_log("Toggl API POST request: $endpoint");
    $url = "https://api.track.toggl.com$endpoint";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Accept: application/json",
    ]);
    curl_setopt($ch, CURLOPT_USERNAME, getenv('TOGGL_API_TOKEN'));
    curl_setopt($ch, CURLOPT_PASSWORD, "api_token");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postBody));

    $result = curl_exec($ch);
    if ($result === false) {
        curl_close($ch);
        die("Toggl API error: " . curl_error($ch));
    }
    curl_close($ch);

    return json_decode($result);
}

header("Access-Control-Allow-Origin: *");

$me = callGetApi("/api/v9/me", 900); // Cache for 15 minutes
if (!$me || !isset($me->default_workspace_id)) {
    error_log('Toggl API error - /api/v9/me failed: ' . json_encode($me));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Service temporarily unavailable']);
    exit;
}
$workspaceId = $me->default_workspace_id;
$clients = callGetApi("/api/v9/workspaces/$workspaceId/clients", 900); // Cache for 15 minutes
if (!is_array($clients)) {
    error_log('Toggl API error - clients endpoint failed for workspace ' . $workspaceId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Failed to retrieve client list']);
    exit;
}
$clientId = null;
foreach ($clients as $client) {
    if ($client->name == getenv('TOGGL_CLIENT_NAME')) {
        $clientId = $client->id;
        break;
    }
}
if (!$clientId) {
    error_log('Toggl client "' . getenv('TOGGL_CLIENT_NAME') . '" not found in workspace');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Client configuration error']);
    exit;
}
//var_dump($clientId);
$projects = callGetApi("/api/v9/workspaces/$workspaceId/projects?client_ids=$clientId", 900); // Cache for 15 minutes
$projectIds = [];
foreach ($projects as $project) {
    $projectIds[$project->id] = $project->id;
}
//var_dump($projectIds);

//$requestConfig = ["billable" => true, "client_ids" => [$clientId], "end_time" => "23:59:59", "end_date" => "2022-12-02", "grouped" => false, "hide_amounts" => true, "order_by" => "date", "rounding_minutes" => true, "startTime" => "00:00", "start_date" => "2022-11-01"];
//$requestConfig = ['client_ids' => [$clientId], "start_date" => "2022-11-01", "bill_id" => true, "include_time_entry_ids" => true];
//$entries = api("/reports/api/v3/workspace/$workspaceId/summary/time_entries", $requestConfig);
//$startDateString = date("Y-11-01");
$beforeStartDateString = date("Y-m-d", strtotime($startDateString . " - 1 day"));
$endDateString = date("Y-m-d", strtotime($startDateString . " + 1 month - 1 day"));
$totalDaysInMonth = substr($endDateString, -2);
$afterEndDateString = date("Y-m-d", strtotime($startDateString . " + 1 month"));
$getEntriesApiFeature = "/api/v9/me/time_entries?start_date=$startDateString&end_date=$afterEndDateString";
//var_dump($getEntriesApiFeature);
$entries = callGetApi($getEntriesApiFeature, 30); // Cache for 30 seconds
//var_dump($entries);
//$currentEntry = api("/api/v9/me/time_entries/current");

$daysWorkedHours = [0];
$daysLabel = [0];
$minLineHours = [0];
$maxLineHours = [0];
$optimalLineHours = [0];
$redLineHours = [0];
$date = new DateTime($startDateString);
$percentages = CalendarTools::getPercentagesForDaysInMonth($date);
$hoursInDays = [];
foreach ($percentages as $dayOfMonth => $percentage) {
    $hoursInDays[$dayOfMonth] = new \stdClass();
    $hoursInDays[$dayOfMonth]->min = max($percentage * 140, 0); // default: 140
    //$hoursInDays[$dayOfMonth]->min = max($percentage * 140 - 35, 0); // default: 140
    $hoursInDays[$dayOfMonth]->optimal = $percentage * 170; // default: 176
    $hoursInDays[$dayOfMonth]->max = $percentage * 200; // default: 200
    //$hoursInDays[$dayOfMonth]->optimal = ($percentage <= 0.5) ? $percentage * (135 - 35) * 2 : (135 - 35) + ($percentage - 0.5) * (160 - 135) * 2;
    //$hoursInDays[$dayOfMonth]->max = ($percentage <= 0.5) ? $percentage * (150 - 35) * 2 : (150 - 35) + ($percentage - 0.5) * (200 - 150) * 2;
}
$redExtraHours = 0;
foreach (array_reverse($hoursInDays, true) as $dayOfMonth => $hoursInDay) {
    $redHours = $hoursInDays[$dayOfMonth]->min - $redExtraHours;
    $hoursInDay->critical = max(0, min(140, $redHours)); // default: 180
    if ($dayOfMonth == 1 || $hoursInDays[$dayOfMonth - 1]->min < $hoursInDays[$dayOfMonth]->min) {
        $redExtraHours = $redExtraHours + 2;
    }
}

$month = $date->format("m");
$dayInMonth = 1;
while ($month == $date->format("m")) {
    $dateString = $date->format("Y-m-d");
    if ($dateString <= Date("Y-m-d")) {
        $daysWorkedHours[$dateString] = 0;
    }
    $hoursInDay = $hoursInDays[$dayInMonth];
    $daysLabel[$dateString] = $dayInMonth;
    $minLineHours[$dateString] = $hoursInDay->min;
    $optimalLineHours[$dateString] = $hoursInDay->optimal;
    $maxLineHours[$dateString] = $hoursInDay->max;
    //$redHours = $percentagesForRedLine[$dayInMonth] * 140;
    //$redHoursLimited = max(0, min(140, $redHours));
    //    $redLineHours[$dateString] = $redHoursLimited;
    $redLineHours[$dateString] = $hoursInDay->critical;
    $date->modify("+1 day");
    $dayInMonth++;
}

$lastEntryBeforeNow = null;
foreach ($entries as $entry) {
    //var_dump($entry->start);
    //var_dump($entry->stop);
    //var_dump($entry);
    if ($entry->billable && in_array($entry->project_id, $projectIds)) {
        $entryStartDateString = substr($entry->start, 10);
        $duration = $entry->duration >= 0 ? $entry->duration : $entry->duration = time() + $entry->duration;
        $daysWorkedHours[substr($entry->start, 0, 10)] += $duration / 3600;
        //var_dump([$entry->duration, $duration / 3600]);
    }
    if (($entry->stop ?? false) && in_array($entry->project_id, $projectIds)) {
        if (!$lastEntryBeforeNow || $lastEntryBeforeNow->stop < $entry->stop) {
            $lastEntryBeforeNow = $entry;
        }
    }
}
//var_dump($durations);

$cumulative = 0;
$cumulativeWorkedHours = [0];

$date = new DateTime($startDateString);
while ($month == $date->format("m")) {
    $dateString = $date->format("Y-m-d");
    // if ($dateString > '2024-01-26') { $date->modify("+1 day"); continue; } // HACK
    if (isset($daysWorkedHours[$dateString])) {
        $cumulative += $daysWorkedHours[$dateString];
        $cumulativeWorkedHours[$dateString] = $cumulative;
    }
    $date->modify("+1 day");
}

$chartData = [
    'labels' => array_values($daysLabel),
    'datasets' => [
        [
            "label" => "REALITA",
            "data" => array_values($cumulativeWorkedHours),
            "fill" => false,
            "borderColor" => "#777",
            "tension" => 0.1,
        ],
        [
            "label" => "krize",
            "data" => array_values($redLineHours),
            "fill" => false,
            "borderColor" => "#faa",
            "tension" => 0.1,
        ],
        [
            "label" => "minimum",
            "data" => array_values($minLineHours),
            "fill" => false,
            "borderColor" => "#ffa",
            "tension" => 0.1,
        ],
        [
            "label" => "optimum",
            "data" => array_values($optimalLineHours),
            "fill" => false,
            "borderColor" => "#afa",
            "tension" => 0.1,
        ],
        [
            "label" => "maximum",
            "data" => array_values($maxLineHours),
            "fill" => false,
            "borderColor" => "#aaf",
            "tension" => 0.1,
        ],
    ],
];

$currentEntry = callGetApi("/api/v9/me/time_entries/current", 30); // Cache for 30 seconds
$isCurrentlyWorking = $currentEntry && in_array($currentEntry->project_id, $projectIds);

$cumulativeWorkedHoursDays = array_keys($cumulativeWorkedHours);
$latestDay = end($cumulativeWorkedHoursDays);
$data = [
    "chartData" => $chartData,
    "metadata" => [
        'currentlyWorking' => $isCurrentlyWorking,
        'lastEntryBeforeNowStopDateTime' => $lastEntryBeforeNow->stop,
        "latestDay" => [
            'reality' => $cumulativeWorkedHours[$latestDay],
            'min' => $minLineHours[$latestDay],
            'optimal' => $optimalLineHours[$latestDay],
            'max' => $maxLineHours[$latestDay],
            'critical' => $redLineHours[$latestDay],
        ],
    ],
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_PRETTY_PRINT);
