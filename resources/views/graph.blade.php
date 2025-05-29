<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Chart Viewer</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2.5em;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        button {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        button.active {
            background: linear-gradient(45deg, #764ba2, #667eea);
            transform: scale(1.05);
        }

        .data-info {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #667eea;
        }

        .data-info h3 {
            margin-top: 0;
            color: #2c3e50;
        }

        .data-point {
            display: inline-block;
            margin: 5px 10px;
            padding: 5px 10px;
            background: rgba(118, 75, 162, 0.2);
            border-radius: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 Network Traffic Monitor</h1>

    <div class="controls">
        <button onclick="changeChartType('line')" class="active" id="lineBtn">Line Chart</button>
        <button onclick="changeChartType('bar')" id="barBtn">Bar Chart</button>
        <button onclick="changeChartType('pie')" id="pieBtn">Pie Chart</button>
        <button onclick="changeChartType('doughnut')" id="doughnutBtn">Doughnut Chart</button>
    </div>

    <div class="chart-container">
        <canvas id="myChart"></canvas>
    </div>

    <div class="data-info">
        <h3>Network Traffic Data Points</h3>
        <div id="dataDisplay"></div>
    </div>
</div>

<script>
    // Network traffic data stored in JavaScript variables
    const networkData = {
        labels: {{ Js::from($results->getTimestamps()) }},
        datasets: [{
            label: 'INOCTETS (MB)',
            data: {{ Js::from($results->getSeries('INOCTETS')) }},
            borderColor: 'rgba(34, 197, 94, 1)',
            backgroundColor: 'rgba(34, 197, 94, 0.2)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }, {
            label: 'OUTOCTETS (MB)',
            data: {{ Js::from($results->getSeries('OUTOCTETS')) }},
            borderColor: 'rgba(239, 68, 68, 1)',
            backgroundColor: 'rgba(239, 68, 68, 0.2)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    };

    // Alternative dataset for pie/doughnut charts (traffic distribution)
    const trafficDistribution = {
        labels: ['HTTP', 'HTTPS', 'FTP', 'SSH', 'Other'],
        datasets: [{
            data: [35, 40, 10, 8, 7],
            backgroundColor: [
                'rgba(34, 197, 94, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(168, 85, 247, 0.8)'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };

    let currentChart;
    let currentType = 'line';

    // Initialize chart
    function initChart() {
        const ctx = document.getElementById('myChart').getContext('2d');

        currentChart = new Chart(ctx, {
            type: currentType,
            data: (currentType === 'pie' || currentType === 'doughnut') ? trafficDistribution : networkData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                scales: (currentType === 'pie' || currentType === 'doughnut') ? {} : {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        title: {
                            display: true,
                            text: 'Data (MB)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        title: {
                            display: true,
                            text: 'Timestamp'
                        },
                        ticks: {
                            maxTicksLimit: 6,
                            callback: function(value, index, values) {
                                const label = this.getLabelForValue(value);
                                return label.split(' ')[1]; // Show only time part
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        displayDataInfo();
    }

    // Change chart type
    function changeChartType(type) {
        currentType = type;

        // Update button states
        document.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
        document.getElementById(type + 'Btn').classList.add('active');

        // Destroy existing chart and create new one
        if (currentChart) {
            currentChart.destroy();
        }

        initChart();
    }

    // Display data information
    function displayDataInfo() {
        const dataDisplay = document.getElementById('dataDisplay');
        const data = (currentType === 'pie' || currentType === 'doughnut') ? trafficDistribution : networkData;

        let html = '';
        if (currentType === 'pie' || currentType === 'doughnut') {
            data.labels.forEach((label, index) => {
                html += `<span class="data-point">${label}: ${data.datasets[0].data[index]}%</span>`;
            });
        } else {
            data.labels.forEach((label, index) => {
                const time = label.split(' ')[1];
                const inOctets = data.datasets[0].data[index];
                const outOctets = data.datasets[1].data[index];
                html += `<span class="data-point">${time}: IN=${inOctets}MB, OUT=${outOctets}MB</span>`;
            });
        }

        dataDisplay.innerHTML = html;
    }

    // Initialize the chart when page loads
    window.addEventListener('load', initChart);
</script>
</body>
</html>
