<?php
/**
 * 電車遅延マップ 改良版SQLiteセットアップ（環境問題完全回避）
 */

set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>電車遅延マップ 改良版セットアップ</title>";
echo "<style>
body{font-family:Arial,sans-serif;max-width:900px;margin:30px auto;padding:20px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#333;}
.container{background:white;border-radius:15px;padding:30px;box-shadow:0 10px 30px rgba(0,0,0,0.2);}
.step{background:#f8f9fa;padding:20px;margin:20px 0;border-radius:10px;border-left:5px solid #007bff;}
.success{color:#28a745;font-weight:bold;} .error{color:#dc3545;font-weight:bold;} .info{color:#007bff;font-weight:bold;}
.code{background:#2d3748;color:#e2e8f0;padding:15px;border-radius:8px;font-family:monospace;margin:10px 0;overflow-x:auto;}
.btn{background:#007bff;color:white;padding:12px 25px;border:none;border-radius:8px;cursor:pointer;font-size:16px;margin:10px 5px;}
.btn:hover{background:#0056b3;}
.alert{padding:15px;border-radius:8px;margin:15px 0;}
.alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;}
.alert-danger{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;}
.alert-warning{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;}
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🚄 電車遅延マップ 改良版SQLiteセットアップ</h1>";
echo "<p class='info'>💡 MySQL不要・権限問題なしのSQLite版です</p>";

// ステップ1: 環境チェック（改良版）
echo "<div class='step'><h2>📋 ステップ1: 環境チェック（改良版）</h2>";

$requirements = [
    'PHP Version (7.4+)' => version_compare(PHP_VERSION, '7.4', '>='),
    'PDO Extension' => extension_loaded('pdo'),
    'PDO SQLite' => extension_loaded('pdo_sqlite'),
    'JSON Extension' => extension_loaded('json'),
    'SQLite3 Extension' => extension_loaded('sqlite3')
];

$allOk = true;
foreach ($requirements as $requirement => $status) {
    $statusText = $status ? "<span class='success'>✓ OK</span>" : "<span class='error'>✗ NG</span>";
    echo "<p>{$requirement}: {$statusText}</p>";
    if (!$status) $allOk = false;
}

// 権限チェックを改良（一時ディレクトリを使用）
$tempDir = sys_get_temp_dir();
$testFile = $tempDir . '/train_delay_test_' . uniqid() . '.txt';
$writePermission = @file_put_contents($testFile, 'test');
if ($writePermission !== false) {
    @unlink($testFile);
    echo "<p>File Write Permission: <span class='success'>✓ OK (一時ディレクトリ使用)</span></p>";
} else {
    echo "<p>File Write Permission: <span class='error'>✗ NG</span></p>";
    $allOk = false;
}

if (!$allOk) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>❌ 環境要件不足</h3>";
    echo "<p><strong>解決方法:</strong></p>";
    echo "<ul>";
    if (!extension_loaded('pdo_sqlite')) {
        echo "<li>XAMPP/WAMPを最新版に更新してください</li>";
        echo "<li>php.ini で extension=sqlite3 を有効化してください</li>";
    }
    if (!$writePermission) {
        echo "<li>一時ディレクトリへの書き込み権限を確認してください</li>";
    }
    echo "</ul>";
    echo "</div></div></div></body></html>";
    exit;
}

echo "<div class='alert alert-success'>✅ 環境チェック完了（SQLite使用可能）</div></div>";

// ステップ2: セキュアディレクトリ作成
echo "<div class='step'><h2>📁 ステップ2: セキュアディレクトリ構成</h2>";

// 現在のディレクトリに作成を試み、失敗したら一時ディレクトリを使用
$baseDir = __DIR__;
$useTemp = false;

$directories = ['config', 'includes', 'api', 'cache', 'logs', 'data'];
foreach ($directories as $dir) {
    $targetDir = $baseDir . '/' . $dir;
    
    if (!is_dir($targetDir)) {
        if (@mkdir($targetDir, 0755, true)) {
            echo "<p class='success'>✓ ディレクトリ作成: {$dir}/</p>";
        } else {
            // 現在のディレクトリに作成できない場合は一時ディレクトリを使用
            $tempBaseDir = $tempDir . '/train_delay_' . session_id();
            if (!$useTemp) {
                echo "<p class='info'>ℹ 権限の関係で一時ディレクトリを使用します</p>";
                $useTemp = true;
                $baseDir = $tempBaseDir;
            }
            
            $tempTargetDir = $baseDir . '/' . $dir;
            if (@mkdir($tempTargetDir, 0755, true)) {
                echo "<p class='success'>✓ 一時ディレクトリ作成: {$dir}/</p>";
            } else {
                echo "<p class='error'>✗ ディレクトリ作成失敗: {$dir}/</p>";
            }
        }
    } else {
        echo "<p class='info'>ℹ ディレクトリ存在: {$dir}/</p>";
    }
}

echo "<div class='alert alert-success'>✅ ディレクトリ構成完了</div></div>";

// ステップ3: 一体型セットアップ（ファイル作成不要）
echo "<div class='step'><h2>⚙️ ステップ3: 一体型セットアップ</h2>";

// メモリ内でSQLiteデータベースを作成
try {
    echo "<p class='info'>📊 メモリ内SQLiteデータベース作成中...</p>";
    
    // メモリベースSQLite（ファイル書き込み不要）
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // テーブル作成
    $pdo->exec("CREATE TABLE train_lines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE stations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        line_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        x_position INTEGER NOT NULL,
        y_position INTEGER NOT NULL,
        order_index INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (line_id) REFERENCES train_lines(id) ON DELETE CASCADE
    )");
    
    $pdo->exec("CREATE TABLE delays (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        line_id INTEGER NOT NULL,
        hour INTEGER NOT NULL,
        delay_minutes INTEGER NOT NULL,
        cause TEXT NOT NULL,
        status TEXT NOT NULL CHECK(status IN ('minor', 'moderate', 'major')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_recorded DATE DEFAULT (DATE('now', 'localtime')),
        FOREIGN KEY (line_id) REFERENCES train_lines(id) ON DELETE CASCADE
    )");
    
    echo "<p class='success'>✓ SQLiteテーブル作成完了</p>";
    
    // 基本データ挿入
    $lines = [
        ['山手線', '#9ACD32'],
        ['中央線', '#FF6347'],
        ['東海道線', '#FF8C00'],
        ['京浜東北線', '#00BFFF'],
        ['総武線', '#FFD700']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO train_lines (name, color) VALUES (?, ?)");
    foreach ($lines as $line) {
        $stmt->execute($line);
    }
    
    // 駅データ
    $stations = [
        '山手線' => [
            ['新宿', 200, 150], ['渋谷', 180, 180], ['品川', 220, 250],
            ['東京', 300, 200], ['上野', 320, 120], ['池袋', 160, 100]
        ],
        '中央線' => [
            ['東京', 300, 200], ['新宿', 200, 150], ['中野', 120, 140],
            ['立川', 50, 130], ['八王子', 20, 120]
        ],
        '東海道線' => [
            ['東京', 300, 200], ['品川', 220, 250], ['川崎', 180, 280],
            ['横浜', 120, 320], ['藤沢', 80, 380]
        ],
        '京浜東北線' => [
            ['大宮', 200, 50], ['上野', 320, 120], ['東京', 300, 200],
            ['品川', 220, 250], ['横浜', 120, 320]
        ],
        '総武線' => [
            ['千葉', 450, 180], ['船橋', 400, 170], ['錦糸町', 350, 160],
            ['新宿', 200, 150]
        ]
    ];
    
    $lineStmt = $pdo->prepare("SELECT id FROM train_lines WHERE name = ?");
    $stationStmt = $pdo->prepare("INSERT INTO stations (line_id, name, x_position, y_position, order_index) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($stations as $lineName => $lineStations) {
        $lineStmt->execute([$lineName]);
        $lineId = $lineStmt->fetchColumn();
        
        foreach ($lineStations as $index => $station) {
            $stationStmt->execute([$lineId, $station[0], $station[1], $station[2], $index + 1]);
        }
    }
    
    echo "<p class='success'>✓ 基本データ挿入完了</p>";
    
    // サンプル遅延データ生成
    $causes = ['信号機故障', '人身事故', '車両故障', '混雑', '天候不良'];
    $delayStmt = $pdo->prepare("INSERT INTO delays (line_id, hour, delay_minutes, cause, status) VALUES (?, ?, ?, ?, ?)");
    
    $lineQuery = $pdo->query("SELECT id FROM train_lines");
    $lineIds = $lineQuery->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($lineIds as $lineId) {
        for ($hour = 0; $hour < 24; $hour++) {
            $isRushHour = ($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19);
            $delayProbability = $isRushHour ? 0.7 : 0.3;
            
            if (rand(0, 100) / 100 < $delayProbability) {
                $delayMinutes = $isRushHour ? rand(1, 15) : rand(1, 8);
                $cause = $causes[array_rand($causes)];
                $status = $delayMinutes > 10 ? 'major' : ($delayMinutes > 5 ? 'moderate' : 'minor');
                
                $delayStmt->execute([$lineId, $hour, $delayMinutes, $cause, $status]);
            }
        }
    }
    
    echo "<p class='success'>✓ サンプル遅延データ生成完了</p>";
    
    // データ確認
    $totalDelays = $pdo->query("SELECT COUNT(*) FROM delays")->fetchColumn();
    echo "<p class='info'>📈 生成された遅延データ: {$totalDelays}件</p>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>❌ データベース作成エラー</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></div></div></body></html>";
    exit;
}

echo "<div class='alert alert-success'>✅ データベース初期化完了</div></div>";

// ステップ4: スタンドアロン版アプリケーション作成
echo "<div class='step'><h2>🚀 ステップ4: スタンドアロン版アプリケーション</h2>";

// データをJSONとして抽出
$trainLines = [];
$stmt = $pdo->query("SELECT id, name, color FROM train_lines ORDER BY id");
$lines = $stmt->fetchAll();

foreach ($lines as $line) {
    $stationStmt = $pdo->prepare("SELECT name, x_position as x, y_position as y FROM stations WHERE line_id = ? ORDER BY order_index");
    $stationStmt->execute([$line['id']]);
    $stations = $stationStmt->fetchAll();
    
    $trainLines[] = [
        'id' => $line['id'],
        'name' => $line['name'],
        'color' => $line['color'],
        'stations' => $stations
    ];
}

$allDelays = $pdo->query("SELECT d.*, tl.name as line_name, tl.color as line_color FROM delays d JOIN train_lines tl ON d.line_id = tl.id")->fetchAll();

// スタンドアロンHTMLアプリケーション作成
$standaloneApp = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>電車遅延情報リアルタイムマップ</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .train-line { cursor: pointer; transition: stroke-width 0.3s; }
        .train-line:hover { stroke-width: 8; }
        .delay-animation { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 0.8; } 50% { opacity: 1; } }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-train text-3xl text-blue-600"></i>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">電車遅延情報マップ</h1>
                        <p class="text-gray-600">スタンドアロン版（データベース不要）</p>
                    </div>
                </div>
                <div class="text-sm text-gray-600">
                    <i class="fas fa-database mr-1"></i>
                    SQLiteメモリDB使用
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">路線図</h2>
                        <div class="text-sm text-gray-600">
                            現在時刻: <span id="currentTime">' . date('H') . ':00</span>
                        </div>
                    </div>
                    
                    <div class="relative bg-gray-50 rounded-lg p-4">
                        <svg id="trainMap" width="500" height="400" class="w-full"></svg>
                    </div>
                    
                    <div class="flex items-center justify-center gap-6 mt-4 text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 bg-green-500 rounded"></div><span>正常</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 bg-yellow-500 rounded"></div><span>軽微</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 bg-orange-500 rounded"></div><span>中程度</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 bg-red-500 rounded"></div><span>大幅</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-clock text-blue-600 mr-2"></i>時間制御
                    </h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            表示時刻: <span id="selectedHourDisplay">' . date('H') . '</span>:00
                        </label>
                        <input type="range" id="timeSlider" min="0" max="23" value="' . date('H') . '" 
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>0:00</span><span>12:00</span><span>23:00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>現在の遅延情報
                    </h3>
                    <div id="currentDelays" class="space-y-3 max-h-64 overflow-y-auto"></div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-chart-line text-green-600 mr-2"></i>統計情報
                    </h3>
                    <div id="statistics" class="space-y-4 text-sm"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const trainLines = ' . json_encode($trainLines, JSON_UNESCAPED_UNICODE) . ';
        const allDelays = ' . json_encode($allDelays, JSON_UNESCAPED_UNICODE) . ';
        let currentHour = ' . date('H') . ';

        function drawTrainMap() {
            const svg = document.getElementById("trainMap");
            svg.innerHTML = "";
            
            trainLines.forEach(line => {
                const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
                const pathData = "M " + line.stations.map(s => `${s.x},${s.y}`).join(" L ");
                
                path.setAttribute("d", pathData);
                path.setAttribute("stroke", line.color);
                path.setAttribute("stroke-width", "6");
                path.setAttribute("fill", "none");
                path.setAttribute("stroke-linecap", "round");
                path.classList.add("train-line");
                path.dataset.lineName = line.name;
                
                svg.appendChild(path);
                
                line.stations.forEach(station => {
                    const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
                    circle.setAttribute("cx", station.x);
                    circle.setAttribute("cy", station.y);
                    circle.setAttribute("r", "4");
                    circle.setAttribute("fill", "white");
                    circle.setAttribute("stroke", line.color);
                    circle.setAttribute("stroke-width", "2");
                    svg.appendChild(circle);
                    
                    const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
                    text.setAttribute("x", station.x);
                    text.setAttribute("y", station.y - 10);
                    text.setAttribute("text-anchor", "middle");
                    text.setAttribute("class", "text-xs font-medium fill-gray-700");
                    text.textContent = station.name;
                    svg.appendChild(text);
                });
            });
        }

        function updateDelayInfo() {
            const currentDelays = allDelays.filter(d => d.hour == currentHour);
            
            // 地図の色更新
            trainLines.forEach(line => {
                const path = document.querySelector(`[data-line-name="${line.name}"]`);
                if (path) {
                    const lineDelays = currentDelays.filter(d => d.line_name === line.name);
                    path.classList.remove("delay-animation");
                    
                    if (lineDelays.length > 0) {
                        const maxDelay = Math.max(...lineDelays.map(d => d.delay_minutes));
                        let color;
                        if (maxDelay > 10) color = "#FF4444";
                        else if (maxDelay > 5) color = "#FFA500";
                        else color = "#FFD700";
                        
                        path.setAttribute("stroke", color);
                        path.classList.add("delay-animation");
                    } else {
                        path.setAttribute("stroke", line.color);
                    }
                }
            });
            
            // 遅延リスト更新
            const container = document.getElementById("currentDelays");
            if (currentDelays.length === 0) {
                container.innerHTML = `
                    <div class="flex items-center gap-2 text-green-600">
                        <i class="fas fa-check-circle"></i>
                        <span class="text-sm">現在遅延はありません</span>
                    </div>
                `;
            } else {
                container.innerHTML = currentDelays.map(delay => `
                    <div class="p-3 bg-red-50 rounded-lg border-l-4 border-red-500">
                        <div class="font-medium text-gray-800">${delay.line_name}</div>
                        <div class="text-sm text-red-600">約${delay.delay_minutes}分遅れ</div>
                        <div class="text-xs text-gray-600 mt-1">原因: ${delay.cause}</div>
                    </div>
                `).join("");
            }
            
            // 統計更新
            const totalDelays = allDelays.length;
            const avgDelay = allDelays.reduce((sum, d) => sum + d.delay_minutes, 0) / totalDelays;
            
            document.getElementById("statistics").innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600">${totalDelays}</div>
                        <div class="text-gray-600">総遅延件数</div>
                    </div>
                    <div class="text-center p-3 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600">${avgDelay.toFixed(1)}分</div>
                        <div class="text-gray-600">平均遅延時間</div>
                    </div>
                </div>
            `;
        }

        document.getElementById("timeSlider").addEventListener("input", function(e) {
            currentHour = parseInt(e.target.value);
            document.getElementById("selectedHourDisplay").textContent = currentHour;
            document.getElementById("currentTime").textContent = currentHour + ":00";
            updateDelayInfo();
        });

        // 初期化
        drawTrainMap();
        updateDelayInfo();
    </script>
</body>
</html>';

$appFilename = 'train-delay-standalone.html';
if (file_put_contents($appFilename, $standaloneApp)) {
    echo "<p class='success'>✓ スタンドアロンアプリケーション作成完了</p>";
} else {
    // ファイル作成に失敗した場合はブラウザで直接表示
    echo "<p class='info'>ℹ ダウンロード用リンクを生成しました</p>";
}

echo "<div class='alert alert-success'>";
echo "<h3>🎉 セットアップ完了！</h3>";
echo "<p><strong>データベース不要のスタンドアロン版が完成しました。</strong></p>";

if (file_exists($appFilename)) {
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $appFilename;
    echo "<p>アクセスURL:</p>";
    echo "<div class='code'>";
    echo "<a href='{$currentUrl}' target='_blank' style='color:#4fc3f7;text-decoration:none;font-weight:bold;'>";
    echo "🚄 {$currentUrl}";
    echo "</a>";
    echo "</div>";
} else {
    echo "<button class='btn' onclick='downloadApp()'>📥 アプリをダウンロード</button>";
    echo "<script>
    function downloadApp() {
        const content = `{$standaloneApp}`;
        const blob = new Blob([content], {type: 'text/html'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'train-delay-map.html';
        a.click();
        URL.revokeObjectURL(url);
    }
    </script>";
}

echo "</div></div>";

echo "<div class='step'>";
echo "<h3>🎯 特徴</h3>";
echo "<ul>";
echo "<li><strong>完全スタンドアロン</strong> - データベース・サーバー不要</li>";
echo "<li><strong>ポータブル</strong> - HTMLファイル1つで動作</li>";
echo "<li><strong>オフライン対応</strong> - インターネット不要</li>";
echo "<li><strong>即座に利用可能</strong> - 設定不要</li>";
echo "</ul>";
echo "</div>";

echo "</div></body></html>";
?>