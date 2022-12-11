<?php
$title = getenv('CHART_TITLE');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="/common.css">
    <link rel="icon" type="image/x-icon" href="/favicon.png">
    <script src="/lib/chart.umd.min.js"></script>
    <script src="/chart.js"></script>
</head>
<body>
<h1><?= $title ?></h1>
<p>
    Poslední aktualizace grafu
    <small>(aktualizuje se každých <span id="last-update-interval"></span> s)</small>:
    <span id="last-update-text"></span>
</p>
<div style="margin-bottom: 20px;">
    <span style="font-size: 200%;">
        <span id="isCurrentlyWorking-true" style="color: #2c2; display: none;">aktuálně pracuje</span>
        <span id="isCurrentlyWorking-false" style="color: #f44; display: none;">aktuálně nepracuje
            (naposledy pracoval: <span id="isCurrentlyWorking-false-last-datetime"></span>)
        </span>
    </span>
</div>
<canvas id="chart">
</canvas>
<div class="alert" id="alert1"></div>
<div class="alert" id="alert2"></div>
<script>
    init();
	updateChartFromServer();
</script>


</body>
</html>
