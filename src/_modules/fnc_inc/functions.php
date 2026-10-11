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

  /**
   * 関数：ディレクトリと拒否設定の確認
   */
  function securityRuntimeDirectory() {
    $projectRoot = realpath(dirname(__DIR__, 2));
    if ($projectRoot === false) throw new RuntimeException('Project directory is unavailable');
    $directory = $projectRoot. '/_runtime';
    if (is_link($directory) || !is_dir($directory)) {
      throw new RuntimeException('`/_runtime`ディレクトリを設置してください');
    }
    clearstatcache(true, $directory);
    if (is_link($directory) || !@chmod($directory, 0700)
      || (fileperms($directory) & 0777) !== 0700) {
      throw new RuntimeException('`/_runtime`ディレクトリは外部からアクセスできないようにしてください');
    }

    $accessFile = $directory. '/.htaccess';
    if (is_link($accessFile) || !is_file($accessFile)) {
      throw new RuntimeException('アクセス拒否するための`.htaccess`が設置されていません');
    }
    $accessRules = @file_get_contents($accessFile, false, null, 0, 4097);
    if ($accessRules === false || strlen($accessRules) > 4096) {
      throw new RuntimeException('ランタイムアクセス設定が無効です');
    }
    $hasDenyRule = false;
    foreach (preg_split('/\r\n|\r|\n/', $accessRules) as $line) {
      $line = trim($line);
      if ($line === '' || str_starts_with($line, '#')) continue;
      if (!preg_match('/\ARequire[ \t]+all[ \t]+denied\z/i', $line)) {
        throw new RuntimeException('ランタイムアクセスは保護されていません');
      }
      $hasDenyRule = true;
    }
    if (!$hasDenyRule) {
      throw new RuntimeException('ランタイムアクセスは保護されていません');
    }
    return $directory;
  }

  /**
   * 関数：セキュリティロック設定
   */
  function withSecurityLock($key, $exclusive, callable $operation) {
    $path = securityRuntimeDirectory(). '/' .hash('sha256', $key). '.lock';
    if (is_link($path)) throw new RuntimeException('無効なロックファイルです');
    $lock = @fopen($path, 'c+b');
    if ($lock === false) throw new RuntimeException('ロックを開けませんでした');
    try {
      if (!@chmod($path, 0600) || !flock($lock, $exclusive ? LOCK_EX : LOCK_SH)) {
        throw new RuntimeException('ロックを取得できませんでした');
      }
      return $operation();
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }

  /**
   * 関数：一時保存
   */
  function atomicWriteFile($file, $contents) {
    if (!is_string($contents)) throw new InvalidArgumentException('内容が文字列ではありません');
    $temporary = dirname($file). '/.emoji-message-form-' .bin2hex(random_bytes(16)). '.tmp';
    $stream = @fopen($temporary, 'xb');
    if ($stream === false) throw new RuntimeException('一時ファイルを作成できませんでした');
    try {
      if (!@chmod($temporary, 0600)) throw new RuntimeException('一時ファイルを保護できません');
      $length = strlen($contents);
      $offset = 0;
      while ($offset < $length) {
        $written = @fwrite($stream, substr($contents, $offset));
        if ($written === false || $written === 0) throw new RuntimeException('書きかけの状態です');
        $offset += $written;
      }
      if (!fflush($stream) || (function_exists('fsync') && !fsync($stream))) {
        throw new RuntimeException('データをフラッシュできません');
      }
      fclose($stream);
      $stream = null;
      if (!@rename($temporary, $file)) throw new RuntimeException('データを置き換えできません');
    } finally {
      if (is_resource($stream)) fclose($stream);
      if (is_file($temporary)) @unlink($temporary);
    }
  }

  /**
   * 関数：ログ保存先の検証
   */
  function logLockKey($file) {
    $directory = realpath(dirname($file));
    if ($directory === false || is_link($file)) throw new RuntimeException('ログファイルのパスが無効です');
    return 'log:' .$directory. '/' .basename($file);
  }

  /**
   * 関数：空文字・破損ファイルなどをログ無しとして扱わない
   */
  function readLogsUnlocked($file, $maxBytes, $maxEntries) {
    if (!is_file($file) || is_link($file)) throw new RuntimeException('ログファイルを利用できません');
    $json = @file_get_contents($file, false, null, 0, $maxBytes + 1);
    if ($json === false || strlen($json) > $maxBytes) throw new RuntimeException('ログサイズの上限を超過しました');

    // {}と[]の区別をたもつ
    if (!str_starts_with(ltrim($json), '[')) throw new RuntimeException('ログ構造が無効です');
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !array_is_list($data) || count($data) > $maxEntries) {
      throw new RuntimeException('ログ構造が無効、または投稿数の上限を超過しました');
    }
    foreach ($data as $item) {
      foreach (['date', 'emoji', 'message'] as $field) {
        if (!is_array($item) || !isset($item[$field]) || !is_string($item[$field])) {
          throw new RuntimeException('ログレコードが無効です');
        }
      }
    }
    return $data;
  }

  /**
   * 関数：ログの読み込み
   */
  function loadLogs($file) {
    global $maxLogBytes, $maxLogEntries;
    return withSecurityLock(logLockKey($file), false, function () use ($file, $maxLogBytes, $maxLogEntries) {
      return readLogsUnlocked($file, $maxLogBytes, $maxLogEntries);
    });
  }

  /**
   * 関数：ファイル差し替え
   */
  function appendLog($file, array $record) {
    global $maxLogBytes, $maxLogEntries;
    withSecurityLock(logLockKey($file), true, function () use ($file, $record, $maxLogBytes, $maxLogEntries) {
      $data = readLogsUnlocked($file, $maxLogBytes, $maxLogEntries);
      if (count($data) >= $maxLogEntries) throw new RuntimeException('ログ数が上限を超過しました');
      $data[] = $record;
      $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      if (strlen($json) > $maxLogBytes) throw new RuntimeException('ログサイズが上限を超過しました');
      atomicWriteFile($file, $json);
    });
  }

  /**
   * 関数：レートリミット
   */
  function consumeRateLimit($category, array $limits) {
    if (!in_array($category, ['login', 'submission'], true)) throw new InvalidArgumentException('Invalid limit category');
    $window = $limits['window'];
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    $address = is_string($address) && filter_var($address, FILTER_VALIDATE_IP) ? $address : 'unknown';
    $rules = ['global' => $limits['global'], 'ip:' .hash('sha256', $address) => $limits['per_ip']];
    return withSecurityLock('rate:' .$category, true, function () use ($category, $window, $rules) {
      $file = securityRuntimeDirectory(). '/' .$category. '.json';
      $now = time();
      $state = [];
      if (is_link($file)) throw new RuntimeException('無効なカウンタファイル');
      if (is_file($file)) {
        $raw = @file_get_contents($file, false, null, 0, 1024 * 1024 + 1);
        if ($raw === false || strlen($raw) > 1024 * 1024) throw new RuntimeException('無効なカウンタファイル');
        $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state) || count($state) > 2048) throw new RuntimeException('無効なカウンタステータス');
        foreach ($state as $key => $bucket) {
          if (!is_array($bucket) || !isset($bucket['expires'], $bucket['count'])
            || !is_int($bucket['expires']) || !is_int($bucket['count']) || $bucket['count'] < 0) {
            throw new RuntimeException('無効なカウンタステータス');
          }
          if ($bucket['expires'] <= $now) unset($state[$key]);
        }
      }
      $retry = 0;
      foreach ($rules as $key => $limit) {
        if (($state[$key]['count'] ?? 0) >= $limit) {
          $retry = max($retry, $state[$key]['expires'] - $now);
        }
      }
      if ($retry > 0) return $retry;
      if (count(array_unique(array_merge(array_keys($state), array_keys($rules)))) > 2048) return $window;
      foreach ($rules as $key => $limit) {
        if (!isset($state[$key])) $state[$key] = ['expires' => $now + $window, 'count' => 0];
        $state[$key]['count']++;
      }
      atomicWriteFile($file, json_encode($state, JSON_THROW_ON_ERROR));
      return 0;
    });
  }

  /**
   * 関数：送信リクエストの不正or上限チェック
   */
  function requestTooLarge() {
    global $maxRequestBytes;
    $length = $_SERVER['CONTENT_LENGTH'] ?? '0';
    return !is_string($length) || !ctype_digit($length) || (int)$length > $maxRequestBytes;
  }

  /**
   * 関数：絵文字チェック
   */
  function validateEmojiSubmission(array $input, array $choices) {
    global $maxCount;
    $emoji = array_key_exists('emoji', $input) ? $input['emoji'] : '';
    $message = array_key_exists('message', $input) ? $input['message'] : '';
    $errors = [];
    if (!is_string($message) || strlen($message) > $maxCount * 4
      || !mb_check_encoding($message, 'UTF-8')) {
      $errors[] = 'メッセージの入力内容が不正です';
    } elseif (mb_strlen($message, 'UTF-8') > $maxCount) {
      $errors[] = 'メッセージは' .$maxCount. '文字以内で入力してください';
    }
    if (!is_string($emoji) || strlen($emoji) > 32
      || !preg_match('/\Aemoji(0|[1-9][0-9]*)\z/', $emoji, $matches)
      || !isset($choices[$matches[1]]) || !is_string($choices[$matches[1]])) {
      $errors[] = '絵文字は設定されているものを1つ選択してください';
    }
    if ($errors) return ['errors' => $errors];
    return ['errors' => [], 'emoji' => $choices[$matches[1]], 'message' => $message];
  }

  /**
   * 関数：設置単位でのセッション設定
   */
  function emojiFormSessionName() {
    return 'EMOJIFORM' .substr(hash('sha256', realpath(dirname(__DIR__, 2))), 0, 16);
  }

  /**
   * 関数：IPアドレスチェック
   */
  function isLoopbackAddress($address) {
    return is_string($address) && ($address === '::1'
      || (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && str_starts_with($address, '127.')));
  }

  /**
   * 関数：接続環境に応じたセッションCookieの保護設定
   */
  function emojiFormSessionCookieOptions() {
    global $sessionSecure, $sessionCookiePath;
    $secure = $sessionSecure;
    if ($secure === null) {
      $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
      $isHttps = !in_array($https, ['', 'off', '0'], true) || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
      $host = parse_url('http://' .($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''), PHP_URL_HOST);
      $host = is_string($host) ? strtolower(trim($host, '[]')) : '';
      $localHost = $host === 'localhost' || str_ends_with($host, '.localhost') || isLoopbackAddress($host);
      
      $secure = $isHttps || !($localHost && isLoopbackAddress($_SERVER['REMOTE_ADDR'] ?? null));
    }
    $path = $sessionCookiePath;
    if ($path === null) {
      $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
      if (!is_string($script) || !str_starts_with($script, '/')) $script = '/index.php';
      $directory = dirname($script);
      if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(dirname(__DIR__, 2). '/admin/index.php')) {
        $directory = dirname($directory);
      }
      
      $segments = array_map(fn($segment) => rawurlencode(rawurldecode($segment)), explode('/', trim($directory, '/')));
      $path = rtrim('/' .implode('/', $segments), '/'). '/';
    }
    return ['lifetime' => 0, 'path' => $path, 'domain' => '',
      'secure' => (bool)$secure, 'httponly' => true, 'samesite' => 'Lax'];
  }

  /**
   * 関数：セッション開始
   */
  function sessionStart() {
    $name = emojiFormSessionName();
    if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $name) {
      throw new RuntimeException('An unrelated session is already active');
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
      ini_set('session.use_strict_mode', '1');
      ini_set('session.use_cookies', '1');
      ini_set('session.use_only_cookies', '1');
      ini_set('session.use_trans_sid', '0');
      session_name($name);
      session_set_cookie_params(emojiFormSessionCookieOptions());
      if (!session_start()) throw new RuntimeException('Cannot start session');
    }
    if (!isset($_SESSION['emoji_form']) || !is_array($_SESSION['emoji_form'])) $_SESSION['emoji_form'] = [];
    if (empty($_SESSION['emoji_form']['initiated'])) {
      if (!session_regenerate_id(true)) throw new RuntimeException('Cannot rotate session');
      $_SESSION['emoji_form']['initiated'] = true;
    }
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
  }

  /**
   * 関数：CSRFトークンを生成
   */
  function generateToken() {
    return bin2hex(random_bytes(32));
  }

  /**
   * 関数：CSRFトークン取得
   */
  function csrfToken($scope) {
    $key = 'csrf_' .$scope;
    if (!is_string($_SESSION['emoji_form'][$key] ?? null)) $_SESSION['emoji_form'][$key] = generateToken();
    return $_SESSION['emoji_form'][$key];
  }

  /**
   * 関数：CSRFトークン検証
   */
  function validateToken($token, $scope = 'form') {
    $stored = $_SESSION['emoji_form']['csrf_' .$scope] ?? null;
    return is_string($token) && is_string($stored) && hash_equals($stored, $token);
  }

  /**
   * 関数：管理画面の認証状態と有効期限の確認
   */
  function isEmojiFormAdmin() {
    global $adminIdleTimeout, $adminAbsoluteTimeout;
    $admin = $_SESSION['emoji_form']['admin'] ?? null;
    $now = time();
    if (!is_array($admin) || !is_int($admin['authenticated_at'] ?? null)
      || !is_int($admin['last_seen'] ?? null)
      || $admin['authenticated_at'] > $now || $admin['last_seen'] > $now
      || $now - $admin['authenticated_at'] >= $adminAbsoluteTimeout
      || $now - $admin['last_seen'] >= $adminIdleTimeout) {
      unset($_SESSION['emoji_form']['admin']);
      return false;
    }
    $_SESSION['emoji_form']['admin']['last_seen'] = $now;
    return true;
  }

  /**
   * 関数：関数：管理画面の認証状態の保存、CSRFトークン更新
   */
  function loginEmojiFormAdmin() {
    if (!session_regenerate_id(true)) throw new RuntimeException('セッションを切り替えることができません');
    $_SESSION['emoji_form']['admin'] = ['authenticated_at' => time(), 'last_seen' => time()];
    $_SESSION['emoji_form']['csrf_admin'] = generateToken();
  }

  /**
   * 関数：ログアウト処理
   */
  function logoutEmojiFormAdmin() {
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'],
      'domain' => $params['domain'], 'secure' => $params['secure'],
      'httponly' => $params['httponly'], 'samesite' => $params['samesite']]);
    if (!session_destroy()) throw new RuntimeException('Cannot destroy session');
  }
