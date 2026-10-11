<?php
// CLIで一時コピーとダミーデータを使い、実際のログや認証情報には触れずに検証します
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

if (($argv[1] ?? '') === '--worker') {
  $fixture = $argv[2];
  $case = $argv[3];
  require_once $fixture. '/_modules/fnc_inc/config.php';
  require_once $fixture. '/_modules/fnc_inc/functions.php';
  ini_set('session.save_path', $fixture. '/sessions');
  ini_set('display_errors', '0');
  $_SERVER['DOCUMENT_ROOT'] = $fixture;
  $_SERVER['REMOTE_ADDR'] = '192.0.2.' .($argv[4] ?? '1');
  $_SERVER['REQUEST_URI'] = '/emoji-message-form/';
  $_SERVER['SCRIPT_NAME'] = '/emoji-message-form/index.php';
  $_SERVER['REQUEST_METHOD'] = 'POST';
  if ($case === 'replay') session_id($argv[4]);
  sessionStart();
  $beforeId = session_id();
  $_SESSION['emoji_form']['csrf_form'] = str_repeat('f', 64);
  $_SESSION['emoji_form']['csrf_admin'] = str_repeat('a', 64);
  $_POST = ['send' => '', 'token' => csrfToken('form'), 'emoji' => 'emoji0',
    'message' => 'dummy'];
  $extra = [];
  ob_start();
  register_shutdown_function(function () use ($fixture, $beforeId, &$extra) {
    $output = ob_get_clean();
    echo json_encode(['status' => http_response_code() ?: 200, 'html' => $output,
      'session' => $_SESSION, 'session_id' => session_id(), 'before_id' => $beforeId,
      'session_name' => session_name(), 'cookie' => session_get_cookie_params(),
      'strict' => ini_get('session.use_strict_mode'),
      'log_hash' => hash_file('sha256', $fixture. '/log.json'),
      'extra' => $extra], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  });
  try {
    $endpoint = 'index.php';
    switch ($case) {
      case 'utf8-message': $_POST['message'] = "\xFF"; break;
      case 'array-message': $_POST['message'] = ['x']; break;
      case 'array-token': $_POST['token'] = ['x']; break;
      case 'null-message': $_POST['message'] = null; break;
      case 'null-emoji': $_POST['emoji'] = null; break;
      case 'null-token': $_POST['token'] = null; break;
      case 'newline-choice': $_POST['emoji'] = "emoji0\n"; break;
      case 'leading-zero-choice': $_POST['emoji'] = 'emoji00'; break;
      case 'negative-choice': $_POST['emoji'] = 'emoji-1'; break;
      case 'overflow-choice': $_POST['emoji'] = 'emoji9223372036854775808'; break;
      case 'last-choice': $_POST['emoji'] = 'emoji4'; break;
      case 'missing-message': unset($_POST['message']); break;
      case 'array-emoji': $_POST['emoji'] = ['x']; break;
      case 'unknown-choice': $_POST['emoji'] = 'emoji999'; break;
      case 'long-emoji': $_POST['emoji'] = 'emoji' .str_repeat('9', 33); break;
      case 'long-message': $_POST['message'] = str_repeat('あ', 501); break;
      case 'blank-choice': $_POST['emoji'] = ''; break;
      case 'bad-token': $_POST['token'] = 'wrong'; break;
      case 'cross-scope': $_POST['token'] = csrfToken('admin'); break;
      case 'oversized': $_SERVER['CONTENT_LENGTH'] = '16385'; break;
      case 'boundary':
        $_POST['message'] = str_repeat('あ', 500);
        break;
      case 'xss':
        $_POST['message'] = '<script>alert(1)</script>';
        break;
      case 'entry-cap': $maxLogEntries = 1; break;
      case 'byte-cap': $maxLogBytes = strlen(file_get_contents($fixture. '/log.json')) + 10; break;
      case 'admin-array-password':
        $endpoint = 'admin/index.php'; $_POST['token'] = csrfToken('admin'); $_POST['password'] = ['x']; break;
      case 'admin-long-password':
        $endpoint = 'admin/index.php'; $_POST['token'] = csrfToken('admin'); $_POST['password'] = str_repeat('x', 1025); break;
      case 'admin-good-password':
        $endpoint = 'admin/index.php'; $_POST['token'] = csrfToken('admin'); $_POST['password'] = 'dummy-password'; break;
      case 'admin-cross-scope':
        $endpoint = 'admin/index.php'; $_POST['password'] = 'dummy-password'; break;
      case 'admin-foreign-session':
        $endpoint = 'admin/index.php'; $_SESSION['isAdmin'] = true; $_POST = []; break;
      case 'admin-read':
        $endpoint = 'admin/index.php'; loginEmojiFormAdmin(); $_POST = []; break;
      case 'admin-page-array':
        $endpoint = 'admin/index.php'; loginEmojiFormAdmin(); $_GET['page'] = ['x']; $_POST = []; break;
      case 'expired-idle':
        $endpoint = 'admin/index.php';
        $_SESSION['emoji_form']['admin'] = ['authenticated_at' => time() - 1800, 'last_seen' => time() - 1800];
        $_POST = []; break;
      case 'expired-absolute':
        $endpoint = 'admin/index.php';
        $_SESSION['emoji_form']['admin'] = ['authenticated_at' => time() - 28800, 'last_seen' => time()];
        $_POST = []; break;
      case 'logout-bad-token':
        $endpoint = 'admin/index.php'; loginEmojiFormAdmin(); $_POST = ['logout' => '', 'token' => ['x']]; break;
      case 'logout-get':
        $endpoint = 'admin/index.php'; loginEmojiFormAdmin(); $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['logout'] = ''; $_POST = []; break;
      case 'logout':
        $endpoint = 'admin/index.php'; loginEmojiFormAdmin();
        $extra['logged_in_id'] = session_id();
        $_POST = ['logout' => '', 'token' => csrfToken('admin')]; break;
      case 'replay': $endpoint = 'admin/index.php'; $_POST = []; break;
      case 'login-limit':
        for ($i = 0; $i < 6; $i++) {
          // セッションを消去してもサーバー側の試行回数は残ります
          $_SESSION['emoji_form'] = [];
          $_POST = ['send' => '', 'token' => csrfToken('admin'), 'password' => 'wrong'];
          require $fixture. '/admin/index.php';
          $extra['statuses'][] = http_response_code() ?: 200;
        }
        return;
      case 'global-login-limit':
        for ($i = 1; $i <= 31; $i++) {
          $_SERVER['REMOTE_ADDR'] = '192.0.2.' .$i;
          $extra['retries'][] = consumeRateLimit('login', $loginLimits);
        }
        return;
      case 'submission-limit':
        for ($i = 0; $i < 6; $i++) {
          $extra['retries'][] = consumeRateLimit('submission', $submissionLimits);
          $_SESSION = [];
        }
        return;
      case 'global-submission-limit':
        for ($i = 1; $i <= 61; $i++) {
          $_SERVER['REMOTE_ADDR'] = '192.0.2.' .$i;
          $extra['retries'][] = consumeRateLimit('submission', $submissionLimits);
        }
        return;
      case 'rate-expiry':
        $limits = ['window' => 10, 'per_ip' => 1, 'global' => 2];
        $extra['first'] = consumeRateLimit('submission', $limits);
        $counter = securityRuntimeDirectory(). '/submission.json';
        $state = json_decode(file_get_contents($counter), true);
        foreach ($state as &$bucket) $bucket['expires'] = time() - 1;
        unset($bucket);
        file_put_contents($counter, json_encode($state));
        $extra['after_expiry'] = consumeRateLimit('submission', $limits);
        return;
      case 'corrupt-counter':
        file_put_contents(securityRuntimeDirectory(). '/submission.json', '{broken'); break;
      case 'atomic-failure':
        mkdir($fixture. '/destination');
        file_put_contents($fixture. '/destination/keep', 'old-data');
        try { atomicWriteFile($fixture. '/destination', 'new-data'); }
        catch (RuntimeException $e) { $extra['rejected'] = true; }
        $extra['preserved'] = file_get_contents($fixture. '/destination/keep') === 'old-data';
        $extra['temporary_files'] = glob($fixture. '/.emoji-message-form-*.tmp');
        return;
      case 'invalid-encoding-append':
        try { appendLog($fixture. '/log.json', ['date' => 'date', 'emoji' => '🎁', 'message' => "\xFF"]); }
        catch (JsonException $e) { $extra['rejected'] = true; }
        return;
      case 'unsafe-runtime':
        rename($fixture. '/_runtime', $fixture. '/saved-runtime');
        mkdir($fixture. '/runtime-target');
        symlink($fixture. '/runtime-target', $fixture. '/_runtime'); break;
      case 'unsafe-runtime-rules':
        file_put_contents($fixture. '/_runtime/.htaccess', "Require all granted\n"); break;
      case 'unsafe-runtime-rules-link':
        rename($fixture. '/_runtime/.htaccess', $fixture. '/saved-rules');
        file_put_contents($fixture. '/runtime-rules', "Require all denied\n");
        symlink($fixture. '/runtime-rules', $fixture. '/_runtime/.htaccess'); break;
      case 'missing-runtime':
        rename($fixture. '/_runtime', $fixture. '/saved-runtime'); break;
      case 'missing-runtime-rules':
        rename($fixture. '/_runtime/.htaccess', $fixture. '/saved-rules'); break;
      case 'empty-runtime-rules':
        file_put_contents($fixture. '/_runtime/.htaccess', "# コメントのみ\n"); break;
      case 'manual-runtime-rules':
        file_put_contents($fixture. '/_runtime/.htaccess', "# 手動のアクセス拒否設定\r\n\r\n  Require all denied  "); break;
      case 'cookie-options':
        $originalServer = $_SERVER;
        $cases = [
          [['HTTP_HOST' => 'localhost:8000', 'REMOTE_ADDR' => '127.0.0.1', 'HTTPS' => 'off'], false],
          [['HTTP_HOST' => '127.0.0.1:8000', 'REMOTE_ADDR' => '127.0.0.1'], false],
          [['HTTP_HOST' => '[::1]:8000', 'REMOTE_ADDR' => '::1'], false],
          [['HTTP_HOST' => 'dev.localhost:8000', 'REMOTE_ADDR' => '127.0.0.1'], false],
          [['HTTP_HOST' => 'localhost', 'REMOTE_ADDR' => '192.0.2.1'], true],
          [['HTTP_HOST' => 'lllo-olll.com', 'REMOTE_ADDR' => '127.0.0.1'], true],
          [['HTTP_HOST' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1', 'HTTPS' => 'on'], true],
          [['HTTP_HOST' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1', 'SERVER_PORT' => '443'], true],
          [['HTTP_HOST' => 'lllo-olll.com', 'REMOTE_ADDR' => '192.0.2.1',
            'HTTP_X_FORWARDED_HOST' => 'localhost', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1'], true],
        ];
        foreach ($cases as [$server, $expected]) {
          $_SERVER = array_merge($originalServer, $server);
          $extra['secure_checks'][] = emojiFormSessionCookieOptions()['secure'] === $expected;
        }
        $_SERVER = $originalServer;
        $_SERVER['SCRIPT_NAME'] = '/nested/emoji-message-form/admin/index.php';
        $_SERVER['SCRIPT_FILENAME'] = $fixture. '/admin/index.php';
        $extra['admin_path'] = emojiFormSessionCookieOptions()['path'];
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = $fixture. '/index.php';
        $extra['root_path'] = emojiFormSessionCookieOptions()['path'];
        $sessionSecure = true;
        $_SERVER['HTTP_HOST'] = 'localhost'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $extra['forced_secure'] = emojiFormSessionCookieOptions()['secure'];
        return;
      case 'concurrent':
      case 'concurrent-reader':
        file_put_contents($fixture. '/ready-' .getmypid(), 'ready');
        $deadline = microtime(true) + 10;
        while (!is_file($fixture. '/go')) {
          if (microtime(true) > $deadline) throw new RuntimeException('Barrier timeout');
          usleep(1000);
        }
        if ($case === 'concurrent-reader') {
          for ($i = 0; $i < 100; $i++) loadLogs($fixture. '/log.json');
          $extra['reads'] = 100;
          return;
        }
        $_POST['message'] = 'writer-' .$argv[4];
        break;
    }
    require $fixture. '/' .$endpoint;
  } catch (Throwable $exception) {
    $extra['uncaught'] = get_class($exception). ': ' .$exception->getMessage();
  }
  return;
}

$source = dirname(__DIR__). '/src';
$root = sys_get_temp_dir(). '/emoji-message-form-tests-' .bin2hex(random_bytes(8));
mkdir($root, 0700);
$fixtures = [];
$failures = 0;
$checks = 0;

function check($condition, $description) {
  global $failures, $checks;
  $checks++;
  if (!$condition) { $failures++; fwrite(STDERR, 'FAIL: ' .$description. PHP_EOL); }
}

function fixture($initial = null) {
  global $source, $root, $fixtures;
  $directory = $root. '/' .bin2hex(random_bytes(6));
  mkdir($directory, 0700);
  foreach (['index.php', 'admin/index.php', '_modules/fnc_inc/config.php', '_modules/fnc_inc/functions.php',
    '_modules/tmpl/base.html.twig', '_modules/tmpl/index.html.twig', '_modules/tmpl/form.html.twig',
    '_modules/tmpl/admin/index.html.twig'] as $file) {
    if (!is_dir(dirname($directory. '/' .$file))) mkdir(dirname($directory. '/' .$file), 0700, true);
    copy($source. '/' .$file, $directory. '/' .$file);
  }
  symlink($source. '/_modules/vendor', $directory. '/_modules/vendor');
  mkdir($directory. '/sessions', 0700);
  mkdir($directory. '/_runtime', 0700);
  file_put_contents($directory. '/_runtime/.htaccess', "Require all denied\n");
  file_put_contents($directory. '/.env', 'ADMIN_PW=' .password_hash('dummy-password', PASSWORD_BCRYPT, ['cost' => 4]). PHP_EOL);
  file_put_contents($directory. '/log.json', $initial ?? json_encode([
    ['date' => '2026-01-01 00:00:00', 'emoji' => '🎁 dummy', 'message' => 'seed-secret-message']
  ], JSON_UNESCAPED_UNICODE));
  $fixtures[] = $directory;
  return $directory;
}

function startWorker($directory, $case, $argument = '1') {
  $process = proc_open([PHP_BINARY, __FILE__, '--worker', $directory, $case, $argument],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  if (!is_resource($process)) throw new RuntimeException('Cannot start worker');
  fclose($pipes[0]);
  return [$process, $pipes];
}

function finishWorker($worker) {
  [$process, $pipes] = $worker;
  $output = stream_get_contents($pipes[1]);
  $diagnostics = stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]);
  $status = proc_close($process);
  $result = json_decode($output, true);
  if ($status !== 0 || !is_array($result)) throw new RuntimeException('Worker failed: ' .$diagnostics. $output);
  check(!isset($result['extra']['uncaught']), 'No uncaught error: ' .($result['extra']['uncaught'] ?? ''));
  check(!str_contains($diagnostics, 'Warning') && !str_contains($diagnostics, 'Fatal'), 'No PHP warning/fatal: ' .$diagnostics);
  return $result;
}

function runWorker($directory, $case, $argument = '1') {
  return finishWorker(startWorker($directory, $case, $argument));
}

function removeOwnedTree($path) {
  if (is_link($path) || !is_dir($path)) { unlink($path); return; }
  foreach (new FilesystemIterator($path) as $entry) removeOwnedTree($entry->getPathname());
  rmdir($path);
}

try {
  foreach (['utf8-message', 'array-message', 'array-token', 'array-emoji', 'unknown-choice',
    'long-emoji', 'long-message', 'blank-choice', 'bad-token', 'cross-scope', 'admin-array-password',
    'admin-long-password', 'admin-cross-scope', 'null-message', 'null-emoji', 'null-token',
    'newline-choice', 'leading-zero-choice', 'negative-choice', 'overflow-choice'] as $case) {
    $directory = fixture(); $before = hash_file('sha256', $directory. '/log.json');
    $result = runWorker($directory, $case);
    check($result['status'] === 400, $case. ' rejects with 400');
    check($result['log_hash'] === $before, $case. ' preserves log bytes');
    check(!str_contains($result['html'], '送信しました、ありがとうございます！'), $case. ' never reports success');
  }
  $directory = fixture(); $result = runWorker($directory, 'oversized');
  check($result['status'] === 413, 'Oversized request rejects with 413');

  foreach (['normal', 'boundary', 'last-choice', 'missing-message', 'xss'] as $case) {
    $directory = fixture(); $result = runWorker($directory, $case);
    check(str_contains($result['html'], '送信しました、ありがとうございます！'), $case. ' succeeds');
    $saved = json_decode(file_get_contents($directory. '/log.json'), true);
    check(count($saved) === 2, $case. ' appends one record');
    check($saved[1]['emoji'] === ($case === 'last-choice' ? '☕️' : '👏'), $case. ' stores the configured emoji');
    if ($case === 'missing-message') check($saved[1]['message'] === '', 'Message is optional');
    check($result['session']['emoji_form']['csrf_form'] !== str_repeat('f', 64), 'Successful submission rotates token');
    if ($case === 'xss') {
      $admin = runWorker($directory, 'admin-read');
      check(!str_contains($admin['html'], '<script>alert(1)</script>')
        && str_contains($admin['html'], '&lt;script&gt;'), 'Stored script is escaped');
      check(!str_contains($admin['html'], '<img src=x onerror=alert(1)>'), 'Stored event attributes are escaped');
    }
  }

  foreach (['{broken', '', '{}', 'null', '[{"date":1,"emoji":"x","message":"x"}]'] as $invalid) {
    $directory = fixture($invalid); $before = hash_file('sha256', $directory. '/log.json');
    $result = runWorker($directory, 'normal');
    check($result['status'] === 503 && $result['log_hash'] === $before, 'Corrupt/invalid log is preserved');
    $result = runWorker($directory, 'admin-read');
    check($result['status'] === 503 && str_contains($result['html'], '読み込めません'), 'Admin reports corrupt log');
  }
  $directory = fixture('[]'); $result = runWorker($directory, 'normal');
  check($result['status'] === 200 && count(json_decode(file_get_contents($directory. '/log.json'), true)) === 1, 'Empty JSON list accepts first submission');
  check(is_dir($directory. '/_runtime') && !is_link($directory. '/_runtime'), 'Manually prepared runtime storage is used inside the application directory');
  check(file_get_contents($directory. '/_runtime/.htaccess') === "Require all denied\n", 'Web access is denied before runtime data is saved');
  check((fileperms($directory. '/_runtime') & 0777) === 0700, 'Runtime directory permissions remain private');
  check(is_file($directory. '/_runtime/submission.json'), 'Rate counters are stored inside the runtime directory');

  foreach (['entry-cap', 'byte-cap', 'corrupt-counter', 'unsafe-runtime', 'unsafe-runtime-rules', 'unsafe-runtime-rules-link',
    'missing-runtime', 'missing-runtime-rules', 'empty-runtime-rules'] as $case) {
    $directory = fixture(); $before = hash_file('sha256', $directory. '/log.json');
    $result = runWorker($directory, $case);
    check($result['status'] === 503 && $result['log_hash'] === $before, $case. ' fails closed and preserves log');
    if ($case === 'missing-runtime') check(!file_exists($directory. '/_runtime'), 'Application does not create a missing runtime directory');
    if ($case === 'missing-runtime-rules') {
      check(!file_exists($directory. '/_runtime/.htaccess'), 'Application does not create a missing access configuration');
      check(count(iterator_to_array(new FilesystemIterator($directory. '/_runtime'))) === 0, 'Unprotected runtime directory remains empty');
    }
  }
  $directory = fixture();
  $result = runWorker($directory, 'manual-runtime-rules');
  check($result['status'] === 200, 'Manual access configuration accepts comments, CRLF and no trailing newline');
  check(file_get_contents($directory. '/_runtime/.htaccess') === "# 手動のアクセス拒否設定\r\n\r\n  Require all denied  ", 'Application preserves manually edited access configuration');
  $result = runWorker(fixture(), 'atomic-failure');
  check(($result['extra']['rejected'] ?? false) && $result['extra']['preserved']
    && !$result['extra']['temporary_files'], 'Failed rename preserves old data and cleans temporary file');
  $directory = fixture(); $before = hash_file('sha256', $directory. '/log.json');
  $result = runWorker($directory, 'invalid-encoding-append');
  check(($result['extra']['rejected'] ?? false) && $result['log_hash'] === $before, 'Encoder failure never overwrites existing log');

  $result = runWorker(fixture(), 'submission-limit');
  check(array_slice($result['extra']['retries'], 0, 5) === [0, 0, 0, 0, 0]
    && $result['extra']['retries'][5] > 0, 'IP submission limit survives session clearing');
  $result = runWorker(fixture(), 'global-submission-limit');
  check(count(array_filter($result['extra']['retries'], fn($retry) => $retry === 0)) === 60
    && $result['extra']['retries'][60] > 0, 'Global submission limit survives IP changes');
  $result = runWorker(fixture(), 'login-limit');
  check($result['extra']['statuses'] === [200, 200, 200, 200, 200, 429], 'Login endpoint limits failed attempts across session clearing');
  $result = runWorker(fixture(), 'global-login-limit');
  check(count(array_filter($result['extra']['retries'], fn($retry) => $retry === 0)) === 30
    && $result['extra']['retries'][30] > 0, 'Global login limit survives IP changes');
  $result = runWorker(fixture(), 'rate-expiry');
  check($result['extra']['first'] === 0 && $result['extra']['after_expiry'] === 0, 'Expired counter permits requests again');

  $result = runWorker(fixture(), 'admin-good-password');
  check(str_contains($result['html'], 'seed-secret-message'), 'Correct password displays admin data');
  check($result['before_id'] !== $result['session_id'], 'Login regenerates session ID');
  check($result['session']['emoji_form']['csrf_admin'] !== str_repeat('a', 64), 'Login rotates admin CSRF token');
  check($result['cookie']['secure'] && $result['cookie']['httponly'] && $result['cookie']['samesite'] === 'Lax'
    && $result['cookie']['path'] === '/emoji-message-form/' && $result['strict'] === '1', 'Session security options are enforced');
  check($result['session_name'] !== 'PHPSESSID' && str_starts_with($result['session_name'], 'EMOJIFORM'), 'Session name is dedicated to emoji-message-form');

  $result = runWorker(fixture(), 'cookie-options');
  foreach ($result['extra']['secure_checks'] as $index => $passed) {
    check($passed, 'Secure selection protects production and handles local HTTP: case ' .$index);
  }
  check($result['extra']['admin_path'] === '/nested/emoji-message-form/', 'Admin uses the public form cookie path');
  check($result['extra']['root_path'] === '/', 'A document-root installation uses the root cookie path');
  check($result['extra']['forced_secure'], 'Explicit Secure setting overrides local HTTP detection');

  foreach (['admin-foreign-session', 'expired-idle', 'expired-absolute'] as $case) {
    $result = runWorker(fixture(), $case);
    check(!str_contains($result['html'], 'seed-secret-message') && !isset($result['session']['emoji_form']['admin']), $case. ' cannot display logs');
  }
  $result = runWorker(fixture(), 'admin-page-array');
  check(str_contains($result['html'], 'seed-secret-message'), 'Array page parameter safely defaults to page one');
  $result = runWorker(fixture(), 'logout-bad-token');
  check($result['status'] === 400 && isset($result['session']['emoji_form']['admin']), 'Logout requires valid CSRF token');
  $result = runWorker(fixture(), 'logout-get');
  check(isset($result['session']['emoji_form']['admin']), 'GET cannot log out');
  $directory = fixture(); $result = runWorker($directory, 'logout');
  check($result['status'] === 303 && !$result['session'], 'Logout destroys session and redirects');
  $replay = runWorker($directory, 'replay', $result['extra']['logged_in_id']);
  check(!str_contains($replay['html'], 'seed-secret-message')
    && $replay['session_id'] !== $result['extra']['logged_in_id'], 'Logged-out session ID cannot be replayed');

  $directory = fixture(); $workers = [];
  for ($i = 1; $i <= 16; $i++) $workers[] = startWorker($directory, 'concurrent', (string)$i);
  $workers[] = startWorker($directory, 'concurrent-reader');
  $deadline = microtime(true) + 10;
  while (count(glob($directory. '/ready-*')) !== 17) {
    if (microtime(true) > $deadline) throw new RuntimeException('Workers did not reach barrier');
    usleep(1000);
  }
  file_put_contents($directory. '/go', 'go');
  foreach ($workers as $index => $worker) {
    $result = finishWorker($worker);
    if ($index < 16) check(str_contains($result['html'], '送信しました、ありがとうございます！'), 'Concurrent writer succeeds');
    else check(($result['extra']['reads'] ?? 0) === 100, 'Concurrent reader observes valid logs');
  }
  $logs = json_decode(file_get_contents($directory. '/log.json'), true, 512, JSON_THROW_ON_ERROR);
  $runtime = $directory. '/_runtime';
  $counter = json_decode(file_get_contents($runtime. '/submission.json'), true);
  check($counter['global']['count'] === 16, 'Simultaneous submissions do not lose rate-counter increments');
  check(count($logs) === 17 && count(array_unique(array_column($logs, 'message'))) === 17, 'All 16 simultaneous submissions and seed are retained');

  echo $checks. ' checks, ' .$failures. ' failures'. PHP_EOL;
} finally {
  removeOwnedTree($root);
}
exit($failures ? 1 : 0);
