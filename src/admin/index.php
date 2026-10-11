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
  require_once __DIR__. '/../_modules/fnc_inc/config.php';
  require_once __DIR__. '/../_modules/fnc_inc/functions.php';

  require_once __DIR__. '/../_modules/vendor/autoload.php';

  //Twig
  $loader = new \Twig\Loader\FilesystemLoader(__DIR__. '/../_modules/tmpl');
  $twig = new \Twig\Environment($loader, []);
  $template = $twig->load('admin/index.html.twig');

  //phpdotenv
  use Dotenv\Dotenv;
  $dotenv = Dotenv::createImmutable($env_dir);
  $dotenv->load();

  sessionStart();
  

  $isAdmin = isEmojiFormAdmin();
  $results = [];
  $total = 0;
  $pager = 1;
  $totalPages = 1;
  $error = [];
  $logFileExists = is_file($log_file);
  $token = csrfToken('admin');

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (requestTooLarge()) {
      http_response_code(413);
      $error[] = '送信データが大きすぎます';
    } elseif (isset($_POST['logout'])) {
      if (!validateToken($_POST['token'] ?? null, 'admin')) {
        http_response_code(400);
        $error[] = '不正な操作を検出したためログアウトできませんでした';
      } else {
        logoutEmojiFormAdmin();
        header('Location: ' .$script_dir, true, 303);
        exit;
      }
    } elseif (isset($_POST['send'])) {
      if (!validateToken($_POST['token'] ?? null, 'admin')) {
        http_response_code(400);
        $error[] = '不正な操作を検出したためログインできませんでした';
      } else {
        $pw = $_POST['password'] ?? null;
        if (!is_string($pw) || strlen($pw) > $maxPasswordBytes) {
          http_response_code(400);
          $error[] = 'パスワードの入力形式が不正です';
        } else {
          try {
            $retry = consumeRateLimit('login', $loginLimits);
            if ($retry > 0) {
              http_response_code(429);
              header('Retry-After: ' .$retry);
              $error[] = 'ログイン試行回数の上限に達しました。時間をおいて再度お試しください';
            } elseif (!is_string($_ENV['ADMIN_PW'] ?? null) || $_ENV['ADMIN_PW'] === '') {
              throw new RuntimeException('Administrator password is not configured');
            } elseif (!password_verify($pw, $_ENV['ADMIN_PW'])) {
              $error[] = 'ログインパスワードが一致しませんでした';
            } else {
              loginEmojiFormAdmin();
              $isAdmin = true;
              $token = csrfToken('admin');
            }
          } catch (Throwable $exception) {
            http_response_code(503);
            error_log('Emoji message form login failed: ' .get_class($exception));
            $error[] = 'ログインできませんでした。時間をおいて再度お試しください';
          }
        }
      }
    }
  }

  if ($isAdmin && $logFileExists) {
    try {
      $data = array_reverse(loadLogs($log_file));
      $total = count($data);
      $totalPages = max(1, (int)ceil($total / $page));
      $requestedPage = $_GET['page'] ?? '1';
      $pager = is_string($requestedPage) && ctype_digit($requestedPage)
        ? max(1, min($totalPages, (int)$requestedPage)) : 1;
      $results = array_slice($data, ($pager - 1) * $page, $page);
    } catch (Throwable $exception) {
      http_response_code(503);
      error_log('Emoji message form log read failed: ' .get_class($exception));
      $error[] = '保存データを読み込めませんでした';
    }
  }

  //Twigに渡してレンダリング
  echo $template->render([
    'bodyClass' => 'admin',
    'current_path' => $_SERVER['REQUEST_URI'],
    'title' => $title,
    'adminURL' => $admin_url,
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
