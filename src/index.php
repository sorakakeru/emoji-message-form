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

  //include
  require_once __DIR__. '/_modules/fnc_inc/config.php';
  require_once __DIR__. '/_modules/fnc_inc/functions.php';

  require_once __DIR__. '/_modules/vendor/autoload.php';

  //Twig
  $loader = new \Twig\Loader\FilesystemLoader(__DIR__. '/_modules/tmpl');
  $twig = new \Twig\Environment($loader, []);
  $template = $twig->load('index.html.twig');

  sessionStart();
  

  $sendSuccess = false;
  $error = [];
  $logFileExists = is_file($log_file);
  $emoji_list = !empty($emoji_list) ? $emoji_list : [];
  $token = csrfToken('form');

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (requestTooLarge()) {
      http_response_code(413);
      $error[] = '送信データが大きすぎます';
    } elseif (isset($_POST['send'])) {
      if (!validateToken($_POST['token'] ?? null, 'form')) {
        http_response_code(400);
        $error[] = '不正な操作を検出したため送信できませんでした';
      } elseif (!$logFileExists) {
        http_response_code(503);
        $error[] = 'データ保存用jsonファイルがありません';
      } else {
        $submission = validateEmojiSubmission($_POST, $emoji_list);
        $error = $submission['errors'];
        if ($error) {
          http_response_code(400);
        } else {
          try {
            $retry = consumeRateLimit('submission', $submissionLimits);
            if ($retry > 0) {
              http_response_code(429);
              header('Retry-After: ' .$retry);
              $error[] = '送信回数の上限に達しました。時間をおいて再度お試しください';
            } else {
              appendLog($log_file, [
                'date' => date('Y-m-d H:i:s'),
                'emoji' => $submission['emoji'],
                'message' => $submission['message']
              ]);
              $sendSuccess = true;
              $_SESSION['emoji_form']['csrf_form'] = generateToken();
              $token = csrfToken('form');
            }
          } catch (Throwable $exception) {
            http_response_code(503);
            error_log('Emoji message form submission failed: ' .get_class($exception));
            $error[] = '保存できませんでした。時間をおいて再度お試しください';
          }
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
    'adminURL' => $admin_url,
    'adminView' => $admin_view,
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
