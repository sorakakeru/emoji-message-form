<?php
  /**
   * simple-enquete
   * https://github.com/sorakakeru/simple-enquete
   * 
   * Copyright (c) 2025 Yamatsu
   * Released under the MIT license
   * https://github.com/sorakakeru/simple-enquete/blob/main/LICENSE
   * 
   * This script uses the Twig template engine (BSD-3-Clause License).
   * For details about Twig's license, please refer to LICENSE_TWIG.
   */

  //Twig
  require_once __DIR__. '/../_modules/vendor/autoload.php';
  $loader = new \Twig\Loader\FilesystemLoader(__DIR__. '/../_modules/tmpl');
  $twig = new \Twig\Environment($loader, []);
  $template = $twig->load('admin/index.html.twig');

  //include
  require_once __DIR__. '/../_modules/fnc_inc/config.php';
  require_once __DIR__. '/../_modules/fnc_inc/functions.php';

  sessionStart();
  

  //default
  $token = '';
  $isAdmin = !empty($_SESSION['isAdmin']);
  $label = [];
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

          //アンケートのラベル格納
          foreach ($enq_conte as $value) {
            $label[] = $value['name'];
          }

          //ページャー設定
          $total = count($data);
          $totalPages = (int)ceil($total / $page);
          $pager = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
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

      //アンケートのラベル格納
      foreach ($enq_conte as $value) {
        $label[] = $value['name'];
      }

      //ページャー設定
      $total = count($data);
      $totalPages = (int)ceil($total / $page);
      $pager = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
      $startPage = ($pager - 1) * $page;
      $results = array_slice($data, $startPage, $page, true);
    }
  }

  //Twigに渡してレンダリング
  echo $template->render([
    'bodyClass' => 'admin',
    'title' => $title,
    'token' => $token,
    'logFileExists' => $logFileExists,
    'isAdmin' => $isAdmin,
    'label' => $label,
    'results' => $results,
    'total' => $total,
    'pager' => $pager,
    'totalPages' => $totalPages,
    'error' => $error
  ]);
?>
