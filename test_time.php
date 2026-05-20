<?php
// Include your config to test the API logic
require_once 'config.php';

// 1. Get the Raw Server Time (The time on your PC/Server)
$raw_server_time = date('Y-m-d H:i:s');

// 2. Get the "Config" Time (The variable $current_time from config.php)
$api_time = $current_time; 

// 3. Calculate the difference
$diff_seconds = strtotime($api_time) - strtotime($raw_server_time);
$status_color = ($diff_seconds == 0) ? 'orange' : 'green';
$status_msg = ($diff_seconds == 0) 
    ? "Identical (Either Server is perfect OR API failed & fallback was used)" 
    : "Different (API is working! Offset: {$diff_seconds} seconds)";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Sync Test</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f3f4f6; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h1 { margin-top: 0; color: #111827; }
        .row { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #e5e7eb; }
        .label { font-weight: bold; color: #4b5563; }
        .value { font-family: monospace; font-size: 1.1em; color: #111827; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 99px; font-size: 0.85em; font-weight: bold; margin-top: 5px; }
        .green { background: #dcfce7; color: #166534; }
        .orange { background: #ffedd5; color: #9a3412; }
    </style>
</head>
<body>

    <div class="card">
        <h1>🕒 Time Synchronization Test</h1>
        
        <div class="row">
            <span class="label">Timezone Used:</span>
            <span class="value"><?php echo date_default_timezone_get(); ?></span>
        </div>

        <div class="row">
            <div>
                <div class="label">Raw Server Time (Internal):</div>
                <small style="color:#666;">(This is your PC/Server clock)</small>
            </div>
            <span class="value"><?php echo $raw_server_time; ?></span>
        </div>

        <div class="row" style="background-color: #f8fafc;">
            <div>
                <div class="label">Config Time ($current_time):</div>
                <small style="color:#666;">(This is what your website uses)</small>
            </div>
            <span class="value" style="color: #2563eb; font-weight:bold;"><?php echo $api_time; ?></span>
        </div>

        <div style="margin-top: 20px; text-align: center;">
            <div class="label">Status Result:</div>
            <span class="badge <?php echo $status_color; ?>">
                <?php echo $status_msg; ?>
            </span>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="test_time.php" style="text-decoration: none; color: white; background: #2563eb; padding: 10px 20px; border-radius: 6px;">Refresh Test</a>
        </div>
    </div>

</body>
</html>