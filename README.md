# 時空間データベース (Spatio-Temporal Database)

このリポジトリは、授業の課題で作成したWebアプリケーションです。PHPやHTML/CSS、JavaScriptを用いて、位置情報や時間に関わるデータ処理、および基本的なWebアプリケーションの実装を行っています。


### JR4路線 リアルタイム遅延マップ (`train-delay-map.php`)
首都圏の主要4路線（埼京線、りんかい線、中央線、総武線）の運行・遅延状況をリアルタイムに監視し、地図上に可視化する高度なWebアプリケーションです。
- **特徴**: Tailwind CSSとFontAwesomeを利用したモダンで美しいUI。SVGによるインタラクティブな路線図の描画。
- **機能**: API（RTI-Giken等）からの遅延データ取得、または高精度シミュレーションを用いた遅延状況の可視化。遅延の度合いに応じて路線図の色や太さが動的に変化します。

## 動作環境・実行方法

PHPで記述されているため、動作させるにはPHPが実行できるローカルサーバー環境（XAMPP、MAMP、Dockerなど）が必要です。

### PHPビルトインサーバーでの実行例
コマンドライン（ターミナル）でこのプロジェクトのルートディレクトリに移動し、以下のコマンドを実行することで簡単に動作確認ができます。

```bash
cd path/to/時空間データベース
php -S localhost:8000
```

その後、ブラウザで以下のURLにアクセスしてください。
- http://localhost:8000/tdb1.html
- http://localhost:8000/tdb2.php
- http://localhost:8000/tdb21.php
- http://localhost:8000/train-delay-map.php

## 使用技術
- HTML5 / CSS3
- JavaScript (Vanilla JS)
- PHP
- Tailwind CSS / FontAwesome (リアルタイム遅延マップで使用)
