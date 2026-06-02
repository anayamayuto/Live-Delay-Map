<?php
/**
 * SQLite版 API ファイル一括作成スクリプト
 * このファイルを実行して必要なAPIファイルを自動生成
 */

// api/get_delays.php
$getDelaysContent = '<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../config/database.php";
require_once "../includes/functions.php";

try {
    $hour = isset($_GET["hour"]) ? intval($_GET["hour"]) : date("H");
    
    if (!validateHour($hour)) {
        jsonResponse(["error" => "無効な時刻が指定されました"], 400);
    }
    
    $pdo = getDBConnection();
    $data = getDelaysByHour($pdo, $hour);
    
    $response = [
        "success" => true,
        "hour" => $hour,
        "delays" => $data["delays"],
        "statistics" => $data["statistics"],
        "timestamp" => $data["timestamp"],
        "count" => count($data["delays"])
    ];
    
    jsonResponse($response);
    
} catch (Exception $e) {
    logError("API Error in get_delays.php", [
        "error" => $e->getMessage(),
        "hour" => $_GET["hour"] ?? null
    ]);
    
    jsonResponse(["error" => "サーバーエラーが発生しました"], 500);
}
?>';

// api/get_line_detail.php
$getLineDetailContent = '<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../config/database.php";
require_once "../includes/functions.php";

try {
    $lineName = isset($_GET["line"]) ? sanitizeInput($_GET["line"]) : "";
    
    if (empty($lineName) || !validateLineName($lineName)) {
        jsonResponse(["error" => "無効な路線名が指定されました"], 400);
    }
    
    $pdo = getDBConnection();
    $delays = getLineDetail($pdo, $lineName);
    
    $todayCount = count($delays);
    $totalDelayMinutes = array_sum(array_column($delays, "delay_minutes"));
    $avgDelay = $todayCount > 0 ? round($totalDelayMinutes / $todayCount, 1) : 0;
    
    $causeCounts = [];
    foreach ($delays as $delay) {
        $cause = $delay["cause"];
        $causeCounts[$cause] = isset($causeCounts[$cause]) ? $causeCounts[$cause] + 1 : 1;
    }
    
    $response = [
        "success" => true,
        "line_name" => $lineName,
        "delays" => $delays,
        "summary" => [
            "total_delays_today" => $todayCount,
            "average_delay" => $avgDelay,
            "total_delay_minutes" => $totalDelayMinutes,
            "cause_breakdown" => $causeCounts
        ],
        "timestamp" => time()
    ];
    
    jsonResponse($response);
    
} catch (Exception $e) {
    logError("API Error in get_line_detail.php", [
        "error" => $e->getMessage(),
        "line" => $_GET["line"] ?? null
    ]);
    
    jsonResponse(["error" => "サーバーエラーが発生しました"], 500);
}
?>';

// api/generate_data.php
$generateDataContent = '<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../config/database.php";
require_once "../includes/functions.php";

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(["error" => "POSTメソッドのみ許可されています"], 405);
    }
    
    $pdo = getDBConnection();
    generateSampleData($pdo);
    
    $statistics = getDelayStatistics($pdo);
    $currentHour = date("H");
    $currentDelays = getCurrentDelays($pdo, $currentHour);
    
    $response = [
        "success" => true,
        "message" => "新しい遅延データが生成されました",
        "generated_at" => date("Y-m-d H:i:s"),
        "statistics" => $statistics,
        "current_delays" => count($currentDelays),
        "timestamp" => time()
    ];
    
    jsonResponse($response);
    
} catch (Exception $e) {
    logError("API Error in generate_data.php", [
        "error" => $e->getMessage(),
        "method" => $_SERVER["REQUEST_METHOD"] ?? "unknown"
    ]);
    
    jsonResponse(["error" => "データ生成中にエラーが発生しました"], 500);
}
?>';

// ディレクトリ作成
if (!is_dir('api')) {
    mkdir('api', 0755, true);
}

// ファイル作成
$files = [
    'api/get_delays.php' => $getDelaysContent,
    'api/get_line_detail.php' => $getLineDetailContent,
    'api/generate_data.php' => $generateDataContent
];

echo "<h2>📁 APIファイル作成結果</h2>";
echo "<ul>";

foreach ($files as $filename => $content) {
    if (file_put_contents($filename, $content)) {
        echo "<li style=\"color: green;\">✓ {$filename} 作成完了</li>";
    } else {
        echo "<li style=\"color: red;\">✗ {$filename} 作成失敗</li>";
    }
}

echo "</ul>";
echo "<p><strong>🎉 APIファイル作成完了！</strong></p>";
echo "<p>次は <code>setup-sqlite.php</code> を実行してください。</p>";
?>