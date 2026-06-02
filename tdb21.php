<!DOCTYPE html>
<html>
<head>
    <title>星座チェックプログラム</title>
</head>
<body>
    <form method="post">
        誕生月: <input type="number" name="month" min="1" max="12" required>
        誕生日: <input type="number" name="date" min="1" max="31" required>
        <button type="submit">実行</button>
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $month = intval($_POST["month"]);
        $date = intval($_POST["date"]);
        $starSign = "";

        // 星座を判定
        if (($month == 3 && $date >= 21) || ($month == 4 && $date <= 19)) {
            $starSign = "牡羊座";
        } elseif (($month == 4 && $date >= 20) || ($month == 5 && $date <= 20)) {
            $starSign = "牡牛座";
        } elseif (($month == 5 && $date >= 21) || ($month == 6 && $date <= 20)) {
            $starSign = "双子座";
        } elseif (($month == 6 && $date >= 21) || ($month == 7 && $date <= 22)) {
            $starSign = "蟹座";
        } elseif (($month == 7 && $date >= 23) || ($month == 8 && $date <= 22)) {
            $starSign = "獅子座";
        } elseif (($month == 8 && $date >= 23) || ($month == 9 && $date <= 22)) {
            $starSign = "乙女座";
        } elseif (($month == 9 && $date >= 23) || ($month == 10 && $date <= 22)) {
            $starSign = "天秤座";
        } elseif (($month == 10 && $date >= 23) || ($month == 11 && $date <= 21)) {
            $starSign = "蠍座";
        } elseif (($month == 11 && $date >= 22) || ($month == 12 && $date <= 21)) {
            $starSign = "射手座";
        } elseif (($month == 12 && $date >= 22) || ($month == 1 && $date <= 19)) {
            $starSign = "山羊座";
        } elseif (($month == 1 && $date >= 20) || ($month == 2 && $date <= 18)) {
            $starSign = "水瓶座";
        } elseif (($month == 2 && $date >= 19) || ($month == 3 && $date <= 20)) {
            $starSign = "魚座";
        } else {
            $starSign = "正しい日付を入力してください";
        }

        // 結果を表示
        echo "<p>あなたは" . $starSign . "です。</p>";
        echo "<p>" . $starSign . "のあなたは今日絶好調！</p>";
    }
    ?>
</body>
</html>