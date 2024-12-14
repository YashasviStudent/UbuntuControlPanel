<?php
require 'vendor/autoload.php';

use phpseclib3\Net\SSH2;

session_start();

// SSH CREDENTIALS, MAKE SURE TO CHANGE THESE TO YOUR REVELANT SSH CREDENTIALS
define('UBUNTU_HOST', 'your-server-ip');
define('UBUNTU_PORT', 22);
define('UBUNTU_USER', 'your-username');
define('UBUNTU_PASSWORD', 'your-password');


function executeCommand($command) {
    $ssh = new SSH2(UBUNTU_HOST, UBUNTU_PORT);
    if (!$ssh->login(UBUNTU_USER, UBUNTU_PASSWORD)) {
        return 'Failed to authenticate to Ubuntu server.';
    }
    return $ssh->exec($command);
}


$output = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['stop'])) {
        $output = executeCommand('sudo shutdown now');
    } elseif (isset($_POST['reboot'])) {
        $output = executeCommand('sudo reboot');
    } elseif (isset($_POST['fetch'])) {
        // Fetch CPU and RAM usage data
        $cpuUsage = executeCommand("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'");
        $ramUsage = executeCommand("free | grep Mem | awk '{print $3/$2 * 100.0}'");
        echo json_encode([
            'cpu' => trim($cpuUsage),
            'ram' => trim($ramUsage)
        ]);
        exit; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubuntu Control Panel</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .buttons { margin-bottom: 20px; }
        button { padding: 10px 20px; margin-right: 10px; }
        canvas { max-width: 600px; margin: 20px auto; display: block; }
        .output { background: #f4f4f4; padding: 10px; margin-top: 20px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>Ubuntu Control Panel</h1>

    <div class="buttons">
        <form method="POST" style="display: inline;">
            <button type="submit" name="stop" style="background: red; color: white;">Stop</button>
        </form>
        <form method="POST" style="display: inline;">
            <button type="submit" name="reboot" style="background: orange; color: white;">Reboot</button>
        </form>
        <button onclick="fakeStart()" style="background: green; color: white;">Start</button>
    </div>

    <h2>Resource Utilization</h2>
    <canvas id="cpuChart"></canvas>
    <canvas id="ramChart"></canvas>

    <?php if (!empty($output)): ?>
        <div class="output">
            <strong>Server Response:</strong>
            <pre><?php echo htmlspecialchars($output); ?></pre>
        </div>
    <?php endif; ?>

    <script>
        let cpuChart, ramChart;

        // Initialize charts
        function initCharts() {
            const ctxCPU = document.getElementById('cpuChart').getContext('2d');
            const ctxRAM = document.getElementById('ramChart').getContext('2d');

            cpuChart = new Chart(ctxCPU, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'CPU Usage (%)',
                        data: [],
                        borderColor: 'blue',
                        tension: 0.1
                    }]
                },
                options: {
                    scales: { x: { display: false } }
                }
            });

            ramChart = new Chart(ctxRAM, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'RAM Usage (%)',
                        data: [],
                        borderColor: 'green',
                        tension: 0.1
                    }]
                },
                options: {
                    scales: { x: { display: false } }
                }
            });
        }

        // Fetch resource utilization data
        function fetchUtilization() {
            fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'fetch=1' })
                .then(response => response.json())
                .then(data => {
                    const timestamp = new Date().toLocaleTimeString();
                    if (cpuChart.data.labels.length > 20) {
                        cpuChart.data.labels.shift();
                        cpuChart.data.datasets[0].data.shift();
                        ramChart.data.labels.shift();
                        ramChart.data.datasets[0].data.shift();
                    }
                    cpuChart.data.labels.push(timestamp);
                    cpuChart.data.datasets[0].data.push(parseFloat(data.cpu));
                    ramChart.data.labels.push(timestamp);
                    ramChart.data.datasets[0].data.push(parseFloat(data.ram));
                    cpuChart.update();
                    ramChart.update();
                });
        }

        // definetly not a fake start button cause server cannot be started with ssh :d
        function fakeStart() {
            alert('Start button is non-functional (server cannot be started via web after stopping).');
        }

       
        initCharts();
        setInterval(fetchUtilization, 5000);
    </script>
</body>
</html>
