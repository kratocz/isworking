<?php
$startDateString = "2022-12-01";

$timezoneString = getenv('TZ');
if ($timezoneString) {
    date_default_timezone_set($timezoneString);
}

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

function api($feature, $postBody = false)
{
    global $ch;
    $url = "https://api.track.toggl.com$feature";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
    ]);
    curl_setopt($ch, CURLOPT_USERNAME, getenv('TOGGL_API_TOKEN')); // secret
    curl_setopt($ch, CURLOPT_PASSWORD, "api_token");
    if ($postBody !== false) {
        $payload = json_encode($postBody);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    }
    $result = curl_exec($ch);
    if ($result === false) {
        die("API error.");
    }
    curl_close($ch);
    return json_decode($result);
}

header("Access-Control-Allow-Origin: *");

$me = api("/api/v9/me");
$workspaceId = $me->default_workspace_id;
$clients = api("/api/v9/workspaces/$workspaceId/clients");
$clientId = null;
foreach ($clients as $client) {
    if ($client->name == getenv('TOGGL_CLIENT_NAME')) {
        $clientId = $client->id;
        break;
    }
}
if (!$clientId) {
    throw new AssertionError("clientId is not set: $clientId");
}
//var_dump($clientId);
$projects = api("/api/v9/workspaces/$workspaceId/projects?client_ids=$clientId");
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
$entries = api($getEntriesApiFeature);
//var_dump($entries);
//$currentEntry = api("/api/v9/me/time_entries/current");

$daysWorkedHours = [0];
$daysLabel = [0];
$minLineHours = [0];
$maxLineHours = [0];
$optimalLineHours = [0];
$redLineHours = [0];
$date = new DateTime($startDateString);
$month = $date->format("m");
$dayInMonth = 1;
while ($month == $date->format("m")) {
    $dateString = $date->format("Y-m-d");
    if ($dateString <= Date("Y-m-d")) {
        $daysWorkedHours[$dateString] = 0;
    }
    $dayInMonthPercentage = getPercentageByDayInMonth($dayInMonth, $totalDaysInMonth);
    //var_dump($dayInMonthPercentage);
    $daysLabel[$dateString] = $dayInMonth;
    $minLineHours[$dateString] = $dayInMonthPercentage * 140;
    $optimalLineHours[$dateString] = $dayInMonthPercentage * 168;
    $maxLineHours[$dateString] = $dayInMonthPercentage * 200;
    $redHours = $dayInMonthPercentage * 140 - ($totalDaysInMonth - 2 - $dayInMonth) * 2;
    $redHoursLimited = max(0, min(140, $redHours));
        $redLineHours[$dateString] = $redHoursLimited;
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
        [
            "label" => "krize",
            "data" => array_values($redLineHours),
            "fill" => false,
            "borderColor" => "#faa",
            "tension" => 0.1,
        ],
    ],
];

$currentEntry = api("/api/v9/me/time_entries/current");
$isCurrentlyWorking = $currentEntry && in_array($currentEntry->project_id, $projectIds);

$data = [
    "chartData" => $chartData,
    "metadata" => [
        'currentlyWorking' => $isCurrentlyWorking,
        'lastEntryBeforeNowStopDateTime' => $lastEntryBeforeNow->stop,
    ],
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_PRETTY_PRINT);
