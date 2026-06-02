<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
 <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja" lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ミニ掲示板</title>
</head>
<body>
  <h1>ミニ掲示板</h1>

  <!-- 投稿フォーム -->
  <form action="tdb2.php" method="POST">
    名前: <input type="text" name="name" size="20" /><br>
    コメント: <input type="text" name="comment" size="40" />
    <input type="submit" value="投稿">
  </form>

  <hr>

  <?php
  // 投稿処理
  if (isset($_POST['name']) && isset($_POST['comment'])) {
    $name = htmlspecialchars($_POST['name']);
    $comment = htmlspecialchars($_POST['comment']);
    $line = $name . "：" . $comment . "\n";
    $fp = fopen("tdb2.dat", "a");
    fwrite($fp, $line);
    fclose($fp);
  }

  // 表示処理
  if (file_exists("tdb2.dat")) {
    $lines = file("tdb2.dat");
    foreach ($lines as $l) {
      echo nl2br($l);
    }
  } else {
    echo "まだ投稿はありません。";
  }
  ?>
</body>
</html>