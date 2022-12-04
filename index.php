<?php
$title = getenv('CHART_TITLE');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="/common.css">
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
<canvas id="chart">
</canvas>
<script>
    init();
	updateChart();
</script>


</body>
</html>
