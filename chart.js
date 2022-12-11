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

function updateChart(response) {
	console.debug(response.metadata);
	chart.data = response.chartData;
	document.getElementById('isCurrentlyWorking-true').style.display = response.metadata.currentlyWorking ? 'inline' : 'none';
	document.getElementById('isCurrentlyWorking-false').style.display = !response.metadata.currentlyWorking ? 'inline' : 'none';
	document.getElementById('isCurrentlyWorking-false-last-datetime').innerText = response.metadata.lastEntryBeforeNowStopDateTime ? new Date(response.metadata.lastEntryBeforeNowStopDateTime).toLocaleString() : "";
	chart.update('none');
	const date = new Date();
	document.getElementById('last-update-text').innerText = date.toLocaleString();
	console.debug('latestDay:', response.metadata.latestDay);
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
