<?php
  /**
   * emoji-message-form
   * https://github.com/sorakakeru/emoji-message-form
   * 
   * Copyright (c) 2026 Yamatsu
   * Released under the MIT license
   * https://github.com/sorakakeru/emoji-message-form/blob/main/LICENSE
   * 
   * This script uses the PHP dotenv library and the Twig template engine (both under the BSD-3-Clause License).
   * For details about Twig's license, please refer to Twig web site.
   * https://twig.symfony.com/license
   * For details about PHP dotenv's license, please refer to PHP dotenv GitHub repository.
   * https://github.com/vlucas/phpdotenv/blob/master/LICENSE
   */

  require_once __DIR__. '/../_modules/vendor/autoload.php';

  //Twig
  $loader = new \Twig\Loader\FilesystemLoader(__DIR__. '/../_modules/tmpl');
  $twig = new \Twig\Environment($loader, []);
  $template = $twig->load('admin/index.html.twig');

  //phpdotenv
  use Dotenv\Dotenv;
  $dotenv = Dotenv::createImmutable(__DIR__. '/..');
  $dotenv->load();

  //include
  require_once __DIR__. '/../_modules/fnc_inc/config.php';
  require_once __DIR__. '/../_modules/fnc_inc/functions.php';

  sessionStart();
  

  //default
  $token = '';
  $isAdmin = !empty($_SESSION['isAdmin']);
  $results = [];
  $total = 0;
  $pager = 1;
  $totalPages = 1;
  $error = [];

  //ファイルの存在チェック
  $logFileExists = file_exists($log_file);

  if ($logFileExists) {
    //token生成
    if (empty($_SESSION['token'])) {
      $_SESSION['token'] = generateToken();
    }
    $token = $_SESSION['token'];
  }


  //フォーム送信処理
  if (isset($_POST['send'])) {

    //token確認
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $validateToken = validateToken($token);

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
        
        $data = loadLogs($log_file);

        //データ展開
        if (!empty($data)) {
          $data = array_reverse($data);

          //ページャー設定
          $total = count($data);
          $totalPages = (int)ceil($total / $page);
          $pager = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
          if ($pager > $totalPages) $pager = $totalPages;
          $startPage = ($pager - 1) * $page;
          $results = array_slice($data, $startPage, $page, true);
        }

      }

    }

  }

  //ページャー遷移時もデータ取得・ページング処理を行う
  if ($isAdmin && $logFileExists) {
    $data = loadLogs($log_file);

    if (!empty($data)) {
      $data = array_reverse($data);

      //ページャー設定
      $total = count($data);
      $totalPages = (int)ceil($total / $page);
      $pager = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
      if ($pager > $totalPages) $pager = $totalPages;
      $startPage = ($pager - 1) * $page;
      $results = array_slice($data, $startPage, $page, true);
    }
  }

  //Twigに渡してレンダリング
  echo $template->render([
    'bodyClass' => 'admin',
    'current_path' => $_SERVER['REQUEST_URI'],
    'title' => $title,
    'token' => $token,
    'logFileExists' => $logFileExists,
    'isAdmin' => $isAdmin,
    'results' => $results,
    'total' => $total,
    'pager' => $pager,
    'totalPages' => $totalPages,
    'error' => $error
  ]);
?>
