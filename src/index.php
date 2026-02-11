<?php
  /**
   * reading-heatmap-cal
   * https://github.com/sorakakeru/reading-heatmap-cal
   * 
   * Copyright (c) 2026 Yamatsu
   * Released under the MIT license
   * https://github.com/sorakakeru/reading-heatmap-cal/blob/main/LICENSE
   * 
   * This script uses the PHP dotenv library and the Twig template engine (both under the BSD-3-Clause License).
   * For details about Twig's license, please refer to Twig web site.
   * https://twig.symfony.com/license
   * For details about PHP dotenv's license, please refer to PHP dotenv GitHub repository.
   * https://github.com/vlucas/phpdotenv/blob/master/LICENSE
   */

  require_once __DIR__. '/_modules/vendor/autoload.php';

  //Twig
  $loader = new \Twig\Loader\FilesystemLoader(__DIR__. '/_modules/tmpl');
  $twig = new \Twig\Environment($loader, []);
  $template = $twig->load('index.html.twig');

  //phpdotenv
  use Dotenv\Dotenv;
  $dotenv = Dotenv::createImmutable(__DIR__);
  $dotenv->load();

  //include
  require_once __DIR__. '/_modules/fnc_inc/config.php';
  require_once __DIR__. '/_modules/fnc_inc/functions.php';

  sessionStart();
  

  //default
  $token = '';
  $isAdmin = !empty($_SESSION['isAdmin']);
  $sendSuccess = false;
  $error = [];

  //token生成
  if (empty($_SESSION['token'])) {
    $_SESSION['token'] = generateToken();
  }
  $token = $_SESSION['token'];


  //フォーム送信処理（ログイン）
  if (isset($_POST['send_login'])) {

    //token確認
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $validateToken = validateToken($token);

    //tokenチェック
    if (!$validateToken) {
      $error[] = '不正な操作を検出したためログインできませんでした';
    } else {

      //パスワードの整合性チェック（PWはハッシュ化されている必要がある）
      $pw = $_POST['password'] ?? '';
      if (!password_verify($pw, $_ENV['ADMIN_PW'])) {
        $error[] = 'ログインパスワードが一致しませんでした';
      } else {
        session_regenerate_id(true);
        $isAdmin = true;
        $_SESSION['isAdmin'] = true;
      }

    }

  }

  //フォーム送信処理（ページ数）
  if (isset($_POST['send_num'])) {

    //token確認
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $validateToken = validateToken($token);

    //tokenチェック
    if (!$validateToken) {
      $error[] = '不正な操作を検出したためログインできませんでした';
    } else {

      //ファイルの存在チェック
      $logFileExists = file_exists($log_file);

      if (!$logFileExists) {
        $error[] = 'ログファイルが存在しません';
      } else {

        //バリデーションチェック
        $error = array_merge($error, validatePageNumber($_POST['number'] ?? ''));

        if (empty($error)) {
          //jsonファイル読み込み
          $data = loadDatas($log_file);

          //日付
          date_default_timezone_set('Asia/Tokyo');
          $date = date('Y-m-d');

          //カウント
          $count = isset($_POST['number']) && is_numeric($_POST['number']) ? (int)$_POST['number'] : 0;

          //データ追加処理
          $data = addPageCount($data, $date, $count);

          //ファイル書き込み
          $sendSuccess = file_put_contents($log_file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
          if ($sendSuccess) {
            session_regenerate_id(true);
          }
        }

      }

    }

  }

  //Twigに渡してレンダリング
  echo $template->render([
    'title' => $title,
    'token' => $token,
    'isAdmin' => $isAdmin,
    'sendSuccess' => $sendSuccess,
    'error' => $error
  ]);
?>
