                <section>
                        <div class="frame">
                                <div class="report">
                                        <div id="loginStatsTable" class="reportTable hidden"></div>
                                        <div class="reportChart" style="width:100%;">
                                                <canvas id="loginStatsChart"></canvas>
                                        </div>
				</div>
                        </div>
                </section>

<script>
	urlLoginStats = 'base3.php?name=courseloginstatsconnector&out=json';

        function fillTable(container, result) {
                if (!result.length) return;
                var table = $('<table>').appendTo(container);
                var trHead = $('<tr>').appendTo(table);
                for (var key in result[0]) $('<th>' + key + '</th>').appendTo(trHead);
                for (let i in result) {
                        var trBody = $('<tr>').appendTo(table);
                        for (var key in result[i]) $('<td>' + result[i][key] + '</td>').appendTo(trBody);
                }
        }

        document.addEventListener('DOMContentLoaded', () => {

            $.loadScript('components/Base3/DataHawk/chart/chart.js', function () {

                console.log('chart.js loaded');

                $.getJSON(urlLoginStats, function (result) {
                        console.log("Last Login per Month:", result);
                        fillTable($('#loginStatsTable'), result);

                        if (!result.length) return;

                        const labels = result.map(entry => entry.month);
                        const data = result.map(entry => entry.num);

                        new Chart(document.getElementById('loginStatsChart'), {
                                type: 'bar',
                                data: {
                                        labels: labels,
                                        datasets: [{
                                                label: 'Last Logins per month',
                                                data: data,
                                                borderWidth: 1,
                                                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                                                borderColor: 'rgba(54, 162, 235, 1)'
                                        }]
                                },
                                options: {
                                        scales: {
                                                y: {
                                                        beginAtZero: true,
                                                        ticks: { stepSize: 1 }
                                                }
                                        }
                                }
                        });
                });

            });

        });
</script>

<style>
	.hidden {
		display:none;
	}

        .report {
                height:350px;
        }

        .reportChart {
                height: 350px;
        }

        .reportChart canvas {
                width: auto;
                height: 350px;
        }

	.reportTable {
                float: right;
                width: 30%;
		height: 350px;
		overflow-x: hidden;
        }

        .reportTable table {
                border-collapse: collapse;
                width: 100%;
        }

        .reportTable table th {
                background: #eee;
        }

        .reportTable table th,
        .reportTable table td {
                border: 1px solid #000;
                padding: 4px;
        }

        @media only screen and (min-width: 600px) {
        }
</style>

