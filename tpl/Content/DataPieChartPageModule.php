                <section>
                        <div class="frame">
                                <div class="report">
                                        <div id="marksStatsTable" class="reportTable"></div>
                                        <div class="reportChart" style="width:60%;">
                                                <canvas id="marksStatsChart"></canvas>
                                        </div>
                                </div>
                        </div>
                </section>

<script>
        urlMarksStats = 'base3.php?name=coursemarksstatsconnector&out=json';

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

                $.getJSON(urlMarksStats, function(result) {
                        console.log(result);
                        fillTable($('#marksStatsTable'), result);

                        if (!result.length) return;
                        const labels = [];
                        const data = [];
                        const bgs = [];

                        for (let i in result) {
                                labels.push(result[i]['markstat']);
                                data.push(result[i]['num']);
                                switch (result[i]['markstat']) {
                                        case 'No info':
                                                bgs.push('rgb(127, 127, 127)');
                                                break;
                                        case 'Fail':
                                                bgs.push('rgb(219, 0, 0)');
                                                break;
                                        case 'Intermediate':
                                                bgs.push('rgb(219, 219, 0)');
                                                break;
                                        case 'Pass':
                                                bgs.push('rgb(0, 219, 0)');
                                                break;
                                }
                        }

                        const ctx = document.getElementById('marksStatsChart');
                        new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                        labels: labels,
                                        datasets: [{
                                                label: 'Marks Status',
                                                data: data,
                                                backgroundColor: bgs,
                                                hoverOffset: 4
                                        }]
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

