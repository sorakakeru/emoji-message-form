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
  $sendSuccess = false;
  $error = [];

  //ファイルの存在チェック
  $logFileExists = file_exists($log_file);

  if ($logFileExists) {
    //token生成
    if (empty($_SESSION['token'])) {
      $_SESSION['token'] = generateToken();
    }
    $token = $_SESSION['token'];

    //アンケート項目が空欄でなければデータを格納
    $enq_conte = (!empty($enq_conte)) ? $enq_conte : [];
  }


  //フォーム送信処理
  if (isset($_POST['send'])) {

    //token確認
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $validateToken = validateToken($token);

    if (!$validateToken) {
      $error[] = '不正な操作を検出したため送信できませんでした';
    } else {

      //データ取得
      $postData = $_POST;
      unset($postData['token'], $postData['send']);
      $data = [];
      foreach ($postData as $value) {
        if (is_array($value)) $value = implode(',', $value);
        $send_data[] = h($value);
      }
      
      if (count($enq_conte) !== count($send_data)) {
        $error[] = '設問の数と一致しません（管理者にお問い合わせください）';
      } else {
        //整合性とバリデーションチェック
        for ($i=0; $i<count($enq_conte); $i++) {
          if ($enq_conte[$i]['required'] === true && empty($send_data[$i])) {
            if ($enq_conte[$i]['type'] === 'text' || $enq_conte[$i]['type'] === 'textarea') {
              $error[] = "{$enq_conte[$i]['name']}は入力必須項目です";
            } elseif ($enq_conte[$i]['type'] === 'radio') {
              $error[] = "{$enq_conte[$i]['name']}の項目は1つ選択してください";
            } elseif ($enq_conte[$i]['type'] === 'checkbox') {
              $error[] = "{$enq_conte[$i]['name']}の項目は1つ以上選択してください";
            }
          }
          
          if (isset($enq_conte[$i]['maxStr'])) {
            if (mb_strlen($send_data[$i], 'UTF-8') > $enq_conte[$i]['maxStr']) $error[] = "{$enq_conte[$i]['name']}の文字数が{$enq_conte[$i]['maxStr']}文字を超えています";
          }

        }

        //エラーがなければ保存処理
        if (empty($error)) {
          $w_data = [];
          if ($logFileExists) $w_data = loadLogs($log_file);

          $w_data[] = [
            'date' => date('Y-m-d H:i:s'),
            'enqdata' => $send_data
          ];

          //ファイル書き込み
          $sendSuccess = file_put_contents($log_file, json_encode($w_data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;

          //token再生成（削除の代わり）
          $_SESSION['token'] = generateToken();
          $token = $_SESSION['token'];

        }

      }

    }

  }

  // Twigに渡してレンダリング
  echo $template->render([
    'bodyClass' => 'home',
    'title' => $title,
    'description' => $description,
    'siteName' => $siteName,
    'siteURL' => $siteURL,
    'token' => $token,
    'logFileExists' => $logFileExists,
    'enqConte' => $enq_conte,
    'sendSuccess' => $sendSuccess,
    'error' => $error
  ]);
?>
