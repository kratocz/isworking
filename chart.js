let refreshingInterval;
let chart;
const lastUpdateInterval = 30;

function init() {
	const config = {
		type: 'line',
	};
	chart = new Chart(document.getElementById('chart'), config);
	document.getElementById('last-update-interval').innerText = "" + lastUpdateInterval;
	refreshingInterval = setInterval(updateChartFromServer, lastUpdateInterval * 1000);
}

function floorHours(hours) {
	return Math.floor(hours * 100) / 100;
}

function updateChart(response) {
	console.debug(response.metadata);
	chart.data = response.chartData;
	document.getElementById('isCurrentlyWorking-true').style.display = response.metadata.currentlyWorking ? 'inline' : 'none';
	document.getElementById('isCurrentlyWorking-false').style.display = !response.metadata.currentlyWorking ? 'inline' : 'none';
	document.getElementById('isCurrentlyWorking-false-last-datetime').innerText = response.metadata.lastEntryBeforeNowStopDateTime ? new Date(response.metadata.lastEntryBeforeNowStopDateTime).toLocaleString() : "";
	chart.update('none');
	const date = new Date();
	document.getElementById('last-update-text').innerText = date.toLocaleString();
	latestDay = response.metadata.latestDay;
	console.debug('latestDay:', latestDay);
	let alertText = "";
	let htmlBodyBackgroundColor = '';
	let alert3Html = "";
	if (response.metadata.latestDay.reality < response.metadata.latestDay.critical) {
		htmlBodyBackgroundColor = '#fee';
		alertText = "Red alert";
		if (!response.metadata.currentlyWorking) {
			alert3Html = "Napiště mi: <span id='alert3Warning'>Pracuj!</span>";
		}
	}
	document.body.style.backgroundColor = htmlBodyBackgroundColor;
	document.getElementById('alert1').innerText = alertText;
	document.getElementById('alert2').innerText = alertText;
	document.getElementById('alert3').innerHTML = alert3Html;
	document.getElementById('hours-above-red').innerText = "" + floorHours( latestDay.reality - latestDay.critical );
	document.getElementById('hours-above-minimum').innerText = "" + floorHours( latestDay.reality - latestDay.min );
	document.getElementById('hours-above-optimum').innerText = "" + floorHours( latestDay.reality - latestDay.optimal );
	document.getElementById('hours-above-maximum').innerText = "" + floorHours( latestDay.reality - latestDay.max );
	document.getElementById('hours-above-red-label').style.backgroundColor = chart.data.datasets.find(line => line.label == "krize").borderColor;
	document.getElementById('hours-above-minimum-label').style.backgroundColor = chart.data.datasets.find(line => line.label == "minimum").borderColor;
	document.getElementById('hours-above-optimum-label').style.backgroundColor = chart.data.datasets.find(line => line.label == "optimum").borderColor;
	document.getElementById('hours-above-maximum-label').style.backgroundColor = chart.data.datasets.find(line => line.label == "maximum").borderColor;
	document.getElementById('hours-above-red').style.color = latestDay.reality > latestDay.critical ? "#0c0" : "#c00";
	document.getElementById('hours-above-minimum').style.color = latestDay.reality > latestDay.minimum ? "#0c0" : "#c00";
	document.getElementById('hours-above-optimum').style.color = latestDay.reality > latestDay.optimum ? "#0c0" : "#c00";
	document.getElementById('hours-above-maximum').style.color = latestDay.reality > latestDay.maximum ? "#0c0" : "#c00";
}

function updateChartFromServer() {
	const xhttp = new XMLHttpRequest();
	xhttp.onload = function () {
		const response = JSON.parse(this.response);
		console.debug("API response:", response);
		updateChart(response);
	}
	xhttp.open("GET", "/api/v1/month/");
	xhttp.send();
}
