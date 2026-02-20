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

    //絵文字リストが空欄でなければデータを格納
    $emoji_list = (!empty($emoji_list)) ? $emoji_list : [];
  }


  //フォーム送信処理
  if (isset($_POST['send'])) {

    //token確認
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $validateToken = validateToken($token);

    if (!$validateToken) {
      $error[] = '不正な操作を検出したため送信できませんでした';
    } else {

      if (!$logFileExists) {
        $error[] = 'データ保存用jsonファイルがありません';
      } else {

        //データ取得
        $post_emoji = $_POST['emoji'] ?? '';
        $post_message = $_POST['message'] ?? '';

        //バリデーションチェック
        if (empty($post_emoji)) $error[] = '絵文字は1つ選択してください';
        if (!is_string($post_emoji)) $error[] = '不正な絵文字を検出したため送信できませんでした';
        if (mb_strlen($post_message, 'UTF-8') > $maxCount) $error[] = 'コメントの文字数が' .$maxCount. '文字を超えています';

        //エラーがなければ保存処理
        if (empty($error)) {
          $w_data = [];
          $w_data = loadLogs($log_file);

          $w_data[] = [
            'date' => date('Y-m-d H:i:s'),
            'emoji' => $emoji_list[str_replace('emoji', '', $post_emoji)],
            'message' => $post_message
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
    'bodyClass' => 'form_page',
    'current_path' => $_SERVER['REQUEST_URI'],
    'title' => $title,
    'description' => $description,
    'siteName' => $siteName,
    'siteURL' => $siteURL,
    'token' => $token,
    'logFileExists' => $logFileExists,
    'emojiList' => $emoji_list,
    'maxCount' => $maxCount,
    'thanxMsg' => $thanxMsg,
    'sendSuccess' => $sendSuccess,
    'error' => $error
  ]);
?>
