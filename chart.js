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
