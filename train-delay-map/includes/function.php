<?php
/**
 * 電車遅延マップ SQLite版セットアップ（MySQL不要）
 */

set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>電車遅延マップ SQLite版セットアップ</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}";
echo ".step{background:white;padding:20px;margin:15px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:#28a745;} .error{color:#dc3545;} .info{color:#007bff;}";
echo ".code{background:#f8f9fa;padding:10px;border-radius:4px;font-family:monospace;margin:10px 0;}";
echo "</style></head><body>";

echo "<h1>🚄 電車遅延マップ SQLite版セットアップ</h1>";
echo "<p class='info'>📝 MySQLが使えない環境でも動作するSQLite版です</p>";

// ステップ1: 環境チェック
echo "<div class='step'><h2>📋 ステップ1: 環境チェック</h2>";

$requirements = [
    'PHP Version' => version_compare(PHP_VERSION, '7.4', '>='),
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

if (!$allOk) {
    echo "<p class='error'>❌ 環境要件を満たしていません。</p>";
    echo "<div class='code'>";
    echo "<p><strong>解決方法:</strong></p>";
    echo "<ul>";
    echo "<li>XAMPP/WAMPを最新版に更新</li>";
    echo "<li>php.ini で extension=sqlite3 と extension=pdo_sqlite を有効化</li>";
    echo "</ul>";
    echo "</div></div></body></html>";
    exit;
}

echo "<p class='success'>✅ 環境チェック完了（SQLite使用）</p></div>";

// ステップ2: ディレクトリ作成
echo "<div class='step'><h2>📁 ステップ2: ディレクトリ構成</h2>";

$directories = ['config', 'includes', 'api', 'cache', 'logs', 'data'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<p class='success'>✓ ディレクトリ作成: {$dir}/</p>";
        } else {
            echo "<p class='error'>✗ ディレクトリ作成失敗: {$dir}/</p>";
        }
    } else {
        echo "<p class='info'>ℹ ディレクトリ存在: {$dir}/</p>";
    }
}

echo "<p class='success'>✅ ディレクトリ構成完了</p></div>";

// ステップ3: SQLite設定ファイル作成
echo "<div class='step'><h2>⚙️ ステップ3: SQLite データベース設定</h2>";

$databaseConfig = "<?php
// SQLite データベース設定
define('DB_PATH', __DIR__ . '/../data/train_delay.sqlite');

/**
 * SQLite データベース接続を取得
 */
function getDBConnection() {
    try {
        \$pdo = new PDO('sqlite:' . DB_PATH);
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // 外部キー制約を有効化
        \$pdo->exec('PRAGMA foreign_keys = ON');
        
        return \$pdo;
    } catch (PDOException \$e) {
        die(\"SQLite 接続エラー: \" . \$e->getMessage());
    }
}

/**
 * データベースとテーブルを初期化
 */
function initializeDatabase() {
    try {
        \$pdo = getDBConnection();
        
        // train_linesテーブル作成
        \$sql = \"CREATE TABLE IF NOT EXISTS train_lines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            color TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )\";
        \$pdo->exec(\$sql);
        
        // stationsテーブル作成
        \$sql = \"CREATE TABLE IF NOT EXISTS stations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            line_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            x_position INTEGER NOT NULL,
            y_position INTEGER NOT NULL,
            order_index INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (line_id) REFERENCES train_lines(id) ON DELETE CASCADE
        )\";
        \$pdo->exec(\$sql);
        
        // stationsテーブルのインデックス
        \$pdo->exec(\"CREATE INDEX IF NOT EXISTS idx_line_order ON stations(line_id, order_index)\");
        
        // delaysテーブル作成
        \$sql = \"CREATE TABLE IF NOT EXISTS delays (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            line_id INTEGER NOT NULL,
            hour INTEGER NOT NULL,
            delay_minutes INTEGER NOT NULL,
            cause TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('minor', 'moderate', 'major')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_recorded DATE DEFAULT (DATE('now', 'localtime')),
            FOREIGN KEY (line_id) REFERENCES train_lines(id) ON DELETE CASCADE
        )\";
        \$pdo->exec(\$sql);
        
        // delaysテーブルのインデックス
        \$pdo->exec(\"CREATE INDEX IF NOT EXISTS idx_line_hour ON delays(line_id, hour)\");
        \$pdo->exec(\"CREATE INDEX IF NOT EXISTS idx_date_hour ON delays(date_recorded, hour)\");
        
        // 基本路線データの挿入
        insertBasicData(\$pdo);
        
        return true;
        
    } catch (PDOException \$e) {
        throw new Exception(\"データベース初期化エラー: \" . \$e->getMessage());
    }
}

/**
 * 基本路線データの挿入
 */
function insertBasicData(\$pdo) {
    // 路線データ
    \$lines = [
        ['name' => '山手線', 'color' => '#9ACD32'],
        ['name' => '中央線', 'color' => '#FF6347'],
        ['name' => '東海道線', 'color' => '#FF8C00'],
        ['name' => '京浜東北線', 'color' => '#00BFFF'],
        ['name' => '総武線', 'color' => '#FFD700']
    ];
    
    // 駅データ
    \$stations = [
        '山手線' => [
            ['name' => '新宿', 'x' => 200, 'y' => 150],
            ['name' => '渋谷', 'x' => 180, 'y' => 180],
            ['name' => '品川', 'x' => 220, 'y' => 250],
            ['name' => '東京', 'x' => 300, 'y' => 200],
            ['name' => '上野', 'x' => 320, 'y' => 120],
            ['name' => '池袋', 'x' => 160, 'y' => 100]
        ],
        '中央線' => [
            ['name' => '東京', 'x' => 300, 'y' => 200],
            ['name' => '新宿', 'x' => 200, 'y' => 150],
            ['name' => '中野', 'x' => 120, 'y' => 140],
            ['name' => '立川', 'x' => 50, 'y' => 130],
            ['name' => '八王子', 'x' => 20, 'y' => 120]
        ],
        '東海道線' => [
            ['name' => '東京', 'x' => 300, 'y' => 200],
            ['name' => '品川', 'x' => 220, 'y' => 250],
            ['name' => '川崎', 'x' => 180, 'y' => 280],
            ['name' => '横浜', 'x' => 120, 'y' => 320],
            ['name' => '藤沢', 'x' => 80, 'y' => 380]
        ],
        '京浜東北線' => [
            ['name' => '大宮', 'x' => 200, 'y' => 50],
            ['name' => '上野', 'x' => 320, 'y' => 120],
            ['name' => '東京', 'x' => 300, 'y' => 200],
            ['name' => '品川', 'x' => 220, 'y' => 250],
            ['name' => '横浜', 'x' => 120, 'y' => 320]
        ],
        '総武線' => [
            ['name' => '千葉', 'x' => 450, 'y' => 180],
            ['name' => '船橋', 'x' => 400, 'y' => 170],
            ['name' => '錦糸町', 'x' => 350, 'y' => 160],
            ['name' => '新宿', 'x' => 200, 'y' => 150]
        ]
    ];
    
    try {
        \$pdo->beginTransaction();
        
        // 路線挿入
        \$stmt = \$pdo->prepare(\"INSERT OR IGNORE INTO train_lines (name, color) VALUES (?, ?)\");
        foreach (\$lines as \$line) {
            \$stmt->execute([\$line['name'], \$line['color']]);
        }
        
        // 駅挿入
        \$lineStmt = \$pdo->prepare(\"SELECT id FROM train_lines WHERE name = ?\");
        \$stationStmt = \$pdo->prepare(\"INSERT OR IGNORE INTO stations (line_id, name, x_position, y_position, order_index) VALUES (?, ?, ?, ?, ?)\");
        
        foreach (\$stations as \$lineName => \$lineStations) {
            \$lineStmt->execute([\$lineName]);
            \$lineId = \$lineStmt->fetchColumn();
            
            if (\$lineId) {
                foreach (\$lineStations as \$index => \$station) {
                    \$stationStmt->execute([
                        \$lineId,
                        \$station['name'],
                        \$station['x'],
                        \$station['y'],
                        \$index + 1
                    ]);
                }
            }
        }
        
        \$pdo->commit();
        
    } catch (Exception \$e) {
        \$pdo->rollBack();
        throw \$e;
    }
}
?>";

if (file_put_contents('config/database.php', $databaseConfig)) {
    echo "<p class='success'>✓ SQLite設定ファイル作成完了</p>";
} else {
    echo "<p class='error'>✗ 設定ファイル作成失敗</p>";
    exit;
}

// ステップ4: SQLite データベース初期化
echo "<p class='success'>✅ 設定ファイル作成完了</p></div>";

echo "<div class='step'><h2>🗄️ ステップ4: SQLite データベース初期化</h2>";

try {
    require_once 'config/database.php';
    
    echo "<p class='info'>📊 SQLite データベース・テーブル作成中...</p>";
    initializeDatabase();
    echo "<p class='success'>✓ SQLite データベース初期化完了</p>";
    
    echo "<p class='info'>📈 サンプルデータ生成中...</p>";
    
    // SQLite版の関数を直接定義
    function generateSampleDataSQLite($pdo) {
        // 今日の既存データを削除
        $pdo->exec("DELETE FROM delays WHERE date_recorded = DATE('now', 'localtime')");
        
        $stmt = $pdo->query("SELECT id, name FROM train_lines");
        $lines = $stmt->fetchAll();
        $causes = ['信号機故障', '人身事故', '車両故障', '混雑', '天候不良'];
        
        $stmt = $pdo->prepare("
            INSERT INTO delays (line_id, hour, delay_minutes, cause, status, date_recorded)
            VALUES (?, ?, ?, ?, ?, DATE('now', 'localtime'))
        ");
        
        foreach ($lines as $line) {
            for ($hour = 0; $hour < 24; $hour++) {
                $isRushHour = ($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19);
                $delayProbability = $isRushHour ? 0.7 : 0.3;
                
                if (rand(0, 100) / 100 < $delayProbability) {
                    $delayMinutes = $isRushHour ? rand(1, 15) : rand(1, 8);
                    $cause = $causes[array_rand($causes)];
                    
                    $status = 'minor';
                    if ($delayMinutes > 10) $status = 'major';
                    elseif ($delayMinutes > 5) $status = 'moderate';
                    
                    $stmt->execute([
                        $line['id'],
                        $hour,
                        $delayMinutes,
                        $cause,
                        $status
                    ]);
                }
            }
        }
    }
    
    $pdo = getDBConnection();
    generateSampleDataSQLite($pdo);
    echo "<p class='success'>✓ サンプルデータ生成完了</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ データベース初期化エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

echo "<p class='success'>✅ SQLite データベース初期化完了</p></div>";

// ステップ5: 完了
echo "<div class='step'><h2>✅ ステップ5: セットアップ完了</h2>";

echo "<div style='background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:20px;border-radius:8px;margin:20px 0;'>";
echo "<h3>🎉 SQLite版セットアップ完了！</h3>";
echo "<p><strong>MySQL不要で動作する電車遅延マップが完成しました。</strong></p>";

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
echo "<p>アクセスURL:</p>";
echo "<div class='code'>";
echo "<a href='{$currentUrl}/index.php' target='_blank' style='color:#007bff;text-decoration:none;font-weight:bold;'>";
echo "🚄 {$currentUrl}/index.php";
echo "</a>";
echo "</div>";
echo "</div>";

echo "<h3>📝 SQLite版の特徴</h3>";
echo "<ul>";
echo "<li><strong>インストール不要</strong> - MySQLサーバー不要</li>";
echo "<li><strong>ポータブル</strong> - data/train_delay.sqlite に全データ保存</li>";
echo "<li><strong>軽量高速</strong> - 小規模データに最適</li>";
echo "<li><strong>開発向け</strong> - 簡単にテスト・デモ可能</li>";
echo "</ul>";

echo "<div class='code'>";
echo "<p><strong>データベースファイル場所:</strong><br>";
echo "data/train_delay.sqlite</p>";
echo "</div>";

echo "</div>";

echo "<div style='text-align:center;margin-top:40px;padding:20px;border-top:1px solid #ddd;color:#666;'>";
echo "<p>🚄 電車遅延情報マップ SQLite版 | v1.0</p>";
echo "<p>MySQL不要で即座に動作します</p>";
echo "</div>";

echo "</body></html>";
?>