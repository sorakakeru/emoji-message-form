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

  //jsonファイル読み込み
  function loadDatas($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
  }

  //セッション
  function sessionStart() {
    session_start();
    //ログインや重要操作時にID再生成
    if (!isset($_SESSION['initiated'])) {
      session_regenerate_id(true);
      $_SESSION['initiated'] = true;
    }
  }
  
  //CSRFトークン生成
  function generateToken() {
    return bin2hex(random_bytes(32));
  }

  //CSRFトークン検証
  function validateToken($token, $sessionKey = 'token') {
    //送信されてきた$tokenが生成したハッシュと一致するか
    return isset($_SESSION[$sessionKey]) && hash_equals($_SESSION[$sessionKey], $token);
  }

  //ページ数バリデーション
  function validatePageNumber($number) {
    $errors = [];
    if (empty($number)) {
      $errors[] = '入力必須項目です';
    }
    if (!is_numeric($number)) {
      $errors[] = '整数値を入力してください';
    }
    return $errors;
  }

  //データ追加処理
  function addPageCount($data, $date, $count) {
    //日付の空きを埋める処理
    if (!empty($data)) {
      //最後のログの日付
      $lastDate = $data[count($data) - 1]['date'];
      $lastDateObj = new DateTime($lastDate);
      $currentDateObj = new DateTime($date);

      //日付差分
      $diff = $lastDateObj->diff($currentDateObj)->days;

      //1日以上空いていたら
      if ($diff > 1) {
        for ($i = 1; $i < $diff; $i++) {
          $gapDate = $lastDateObj->modify('+1 day')->format('Y-m-d');
          $data[] = [
            'date' => $gapDate,
            'count' => 0
          ];
        }
      }
    }

    //データの存在チェック＆加算処理
    $found = false;
    foreach ($data as &$item) {
      if ($item['date'] === $date) {
        $item['count'] += $count;
        $found = true;
        break;
      }
    }
    unset($item);

    //同じ日付がなければ新規追加
    if (!$found) {
      $data[] = [
        'date' => $date,
        'count' => $count
      ];
    }
    return $data;
  }

  //XSS対策
  function h($str) {
    if (is_array($str)) {
      return array_map('h', $str);
    } else {
      return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
  }

?>