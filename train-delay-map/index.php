<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// 初期データが存在しない場合、サンプルデータを生成
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT COUNT(*) FROM delays");
$count = $stmt->fetchColumn();

if ($count == 0) {
    generateSampleData($pdo);
}

// 最新の遅延データを取得
$currentHour = date('H');
$delays = getCurrentDelays($pdo, $currentHour);
$statistics = getDelayStatistics($pdo);
$trainLines = getTrainLines();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>電車遅延情報リアルタイムマップ</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .train-line {
            cursor: pointer;
            transition: stroke-width 0.3s;
        }
        .train-line:hover {
            stroke-width: 8;
        }
        .station {
            cursor: pointer;
        }
        .delay-animation {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="container mx-auto px-4 py-6">
        <!-- ヘッダー -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-train text-3xl text-blue-600"></i>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">電車遅延情報マップ</h1>
                        <p class="text-gray-600">JR東日本主要路線の遅延状況をリアルタイム表示</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>
                        最終更新: <span id="lastUpdate"><?= date('H:i:s') ?></span>
                    </div>
                    <button id="toggleLive" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-play mr-2"></i>ライブ
                    </button>
                    <button id="refreshBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>更新
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- メイン路線図 -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">路線図</h2>
                        <div class="text-sm text-gray-600">
                            現在時刻: <span id="currentTime"><?= $currentHour ?>:00</span>
                        </div>
                    </div>
                    
                    <div class="relative bg-gray-50 rounded-lg p-4 overflow-hidden">
                        <svg id="trainMap" width="500" height="400" class="w-full">
                            <!-- 路線とステーションはJavaScriptで動的生成 -->
                        </svg>
                    </div>
                    
                    <!-- 凡例 -->
                    <div class="flex items-center justify-center gap-6 mt-4 text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 bg-green-500 rounded"></div>
                            <span>正常</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                            <span>軽微な遅延</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 bg-orange-500 rounded"></div>
                            <span>中程度の遅延</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 bg-red-500 rounded"></div>
                            <span>大幅な遅延</span>
                        </div>
                    </div>
                </div>

                <!-- 時間制御 -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-clock text-blue-600 mr-2"></i>時間制御
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                表示時刻: <span id="selectedHourDisplay"><?= $currentHour ?></span>:00
                            </label>
                            <input type="range" id="timeSlider" min="0" max="23" value="<?= $currentHour ?>" 
                                   class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>0:00</span>
                                <span>12:00</span>
                                <span>23:00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- サイドパネル -->
            <div class="space-y-6">
                <!-- 現在の遅延情報 -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>現在の遅延情報
                    </h3>
                    
                    <div id="currentDelays" class="space-y-3 max-h-64 overflow-y-auto">
                        <?php if (empty($delays)): ?>
                            <div class="flex items-center gap-2 text-green-600">
                                <i class="fas fa-check-circle"></i>
                                <span class="text-sm">現在遅延はありません</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($delays as $delay): ?>
                                <div class="p-3 bg-red-50 rounded-lg border-l-4 border-red-500">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($delay['line_name']) ?></div>
                                    <div class="text-sm text-red-600">約<?= $delay['delay_minutes'] ?>分遅れ</div>
                                    <div class="text-xs text-gray-600 mt-1">原因: <?= htmlspecialchars($delay['cause']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 統計情報 -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-chart-line text-green-600 mr-2"></i>統計情報（24時間）
                    </h3>
                    
                    <div class="space-y-4 text-sm">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-3 bg-blue-50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600"><?= $statistics['total_delays'] ?></div>
                                <div class="text-gray-600">総遅延件数</div>
                            </div>
                            <div class="text-center p-3 bg-orange-50 rounded-lg">
                                <div class="text-2xl font-bold text-orange-600"><?= number_format($statistics['avg_delay'], 1) ?>分</div>
                                <div class="text-gray-600">平均遅延時間</div>
                            </div>
                        </div>
                        
                        <div id="lineStats">
                            <h4 class="font-medium text-gray-800 mb-2">路線別遅延回数</h4>
                            <div class="space-y-2">
                                <?php foreach ($statistics['line_stats'] as $lineStat): ?>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-700"><?= htmlspecialchars($lineStat['line_name']) ?></span>
                                        <div class="text-right">
                                            <span class="font-medium"><?= $lineStat['delay_count'] ?>回</span>
                                            <?php if ($lineStat['avg_delay'] > 0): ?>
                                                <span class="text-xs text-gray-500 ml-2">
                                                    (平均<?= number_format($lineStat['avg_delay'], 1) ?>分)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 路線詳細 -->
                <div id="lineDetail" class="bg-white rounded-xl shadow-lg p-6" style="display: none;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4" id="lineDetailTitle">路線詳細情報</h3>
                    <div id="lineDetailContent" class="space-y-3">
                        <!-- 動的コンテンツ -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // 路線データ
        const trainLines = <?= json_encode($trainLines) ?>;
        let isLive = true;
        let currentHour = <?= $currentHour ?>;
        let updateInterval;

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            drawTrainMap();
            updateDelayInfo();
            startLiveUpdate();
            
            // イベントリスナー設定
            document.getElementById('timeSlider').addEventListener('input', function(e) {
                currentHour = parseInt(e.target.value);
                document.getElementById('selectedHourDisplay').textContent = currentHour;
                document.getElementById('currentTime').textContent = currentHour + ':00';
                updateDelayInfo();
            });
            
            document.getElementById('toggleLive').addEventListener('click', toggleLive);
            document.getElementById('refreshBtn').addEventListener('click', refreshData);
        });

        // 路線図描画
        function drawTrainMap() {
            const svg = document.getElementById('trainMap');
            svg.innerHTML = '';
            
            trainLines.forEach(line => {
                // 路線描画
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                const pathData = 'M ' + line.stations.map(s => `${s.x},${s.y}`).join(' L ');
                
                path.setAttribute('d', pathData);
                path.setAttribute('stroke', line.color);
                path.setAttribute('stroke-width', '6');
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke-linecap', 'round');
                path.classList.add('train-line');
                path.dataset.lineName = line.name;
                
                path.addEventListener('click', () => showLineDetail(line.name));
                svg.appendChild(path);
                
                // 駅描画
                line.stations.forEach(station => {
                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', station.x);
                    circle.setAttribute('cy', station.y);
                    circle.setAttribute('r', '4');
                    circle.setAttribute('fill', 'white');
                    circle.setAttribute('stroke', line.color);
                    circle.setAttribute('stroke-width', '2');
                    circle.classList.add('station');
                    svg.appendChild(circle);
                    
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', station.x);
                    text.setAttribute('y', station.y - 10);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('class', 'text-xs font-medium fill-gray-700');
                    text.textContent = station.name;
                    svg.appendChild(text);
                });
            });
        }

        // 遅延情報更新
        function updateDelayInfo() {
            fetch(`api/get_delays.php?hour=${currentHour}`)
                .then(response => response.json())
                .then(data => {
                    updateMapColors(data.delays);
                    updateDelayList(data.delays);
                    updateStatistics(data.statistics);
                })
                .catch(error => console.error('Error:', error));
        }

        // 地図の色更新
        function updateMapColors(delays) {
            // 全路線を正常色にリセット
            trainLines.forEach(line => {
                const path = document.querySelector(`[data-line-name="${line.name}"]`);
                if (path) {
                    path.setAttribute('stroke', line.color);
                    path.classList.remove('delay-animation');
                }
            });
            
            // 遅延路線の色を更新
            delays.forEach(delay => {
                const path = document.querySelector(`[data-line-name="${delay.line_name}"]`);
                if (path) {
                    let color;
                    if (delay.delay_minutes > 10) color = '#FF4444'; // 大幅遅延
                    else if (delay.delay_minutes > 5) color = '#FFA500'; // 中程度遅延
                    else color = '#FFD700'; // 軽微遅延
                    
                    path.setAttribute('stroke', color);
                    path.classList.add('delay-animation');
                }
            });
        }

        // 遅延リスト更新
        function updateDelayList(delays) {
            const container = document.getElementById('currentDelays');
            
            if (delays.length === 0) {
                container.innerHTML = `
                    <div class="flex items-center gap-2 text-green-600">
                        <i class="fas fa-check-circle"></i>
                        <span class="text-sm">現在遅延はありません</span>
                    </div>
                `;
            } else {
                container.innerHTML = delays.map(delay => `
                    <div class="p-3 bg-red-50 rounded-lg border-l-4 border-red-500">
                        <div class="font-medium text-gray-800">${delay.line_name}</div>
                        <div class="text-sm text-red-600">約${delay.delay_minutes}分遅れ</div>
                        <div class="text-xs text-gray-600 mt-1">原因: ${delay.cause}</div>
                    </div>
                `).join('');
            }
        }

        // 統計情報更新
        function updateStatistics(stats) {
            // 統計情報の更新処理
            console.log('Statistics updated:', stats);
        }

        // 路線詳細表示
        function showLineDetail(lineName) {
            fetch(`api/get_line_detail.php?line=${encodeURIComponent(lineName)}`)
                .then(response => response.json())
                .then(data => {
                    const detail = document.getElementById('lineDetail');
                    const title = document.getElementById('lineDetailTitle');
                    const content = document.getElementById('lineDetailContent');
                    
                    title.textContent = `${lineName} 詳細情報`;
                    content.innerHTML = data.delays.map(delay => `
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">${delay.hour}:00</span>
                                <span class="font-medium text-red-600">${delay.delay_minutes}分遅れ</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">${delay.cause}</div>
                        </div>
                    `).join('');
                    
                    detail.style.display = 'block';
                })
                .catch(error => console.error('Error:', error));
        }

        // ライブ更新制御
        function toggleLive() {
            isLive = !isLive;
            const btn = document.getElementById('toggleLive');
            
            if (isLive) {
                btn.innerHTML = '<i class="fas fa-pause mr-2"></i>停止';
                btn.classList.remove('bg-gray-500', 'hover:bg-gray-600');
                btn.classList.add('bg-red-500', 'hover:bg-red-600');
                startLiveUpdate();
            } else {
                btn.innerHTML = '<i class="fas fa-play mr-2"></i>ライブ';
                btn.classList.remove('bg-red-500', 'hover:bg-red-600');
                btn.classList.add('bg-green-500', 'hover:bg-green-600');
                stopLiveUpdate();
            }
        }

        function startLiveUpdate() {
            updateInterval = setInterval(() => {
                if (isLive) {
                    updateDelayInfo();
                    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
                }
            }, 30000); // 30秒間隔
        }

        function stopLiveUpdate() {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        }

        function refreshData() {
            fetch('api/generate_data.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateDelayInfo();
                        document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>