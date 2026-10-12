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
   * setting conf
   */

  //ページ（フォーム）タイトル
  //1行でテキストのみ、HTMLタグは無効です
  $title = '絵文字＆メッセージフォーム';

  //絵文字リスト
  //絵文字は''（シングルクォーテーション）で囲み、複数指定する場合は,（カンマ）で区切ってください
  //個数制限はありません
  $emoji_list = [
    '👏',
    '🎉',
    '😊',
    '🐥',
    '☕️'
  ];

  //保存するメッセージの最大件数（上限に達した場合、新規の送信を拒否します）
  //半角数字で指定してください
  $maxLogEntries = 10000;

  //ページタイトル下の説明文
  //テキストのみでHTMLタグは無効、改行は有効です
  $description = <<<EOT
    絵文字やメッセージを送ることができます（メッセージは任意）
    改行有効です
  EOT;

  //スクリプト設置ディレクトリ
  //例：設置URLが`https://example.com/form/`の場合、`/form/`と指定
  $script_dir = '/form/';

  //管理画面ディレクトリ
  //例：管理画面設置URLが`https://example.com/form/admin/`の場合、`/form/admin/`と指定
  //管理画面のディレクトリを変更する場合は、実際の`/admin`ディレクトリも変更してください
  $admin_url = '/form/admin/';

  //サイト名（サイトに戻るリンクで使用）
  //空欄の場合はサイトに戻るリンクは表示されません
  $siteName = 'example site name';

  //サイトURL（サイトに戻るリンクで使用）
  $siteURL = 'https://example.com';

  //一度に送信できるメッセージの最大文字数（全角でカウント）
  //半角数字で指定してください
  $maxCount = 500;

  //送信完了時に表示するメッセージ
  //1行でテキストのみ、HTMLタグは無効です
  $thanxMsg = '送信しました、ありがとうございます！';

  //管理画面での1ページの表示件数
  //半角数字で指定してください
  $page = 20;

  //`.env`ファイルのディレクトリパス
  //場所の変更がなければ、このままでOKです
  $env_dir = __DIR__. '/..';

  //ログファイル（ファイル名や場所の変更がなければ、このままでOKです）
  $log_file = __DIR__. '/../../log.json';


  /**
   * 以下はPHPに詳しい場合のみ変更してください
   * 通常はこのままでOKです
   */
  //保存上限に達した場合は既存ログを削除せず新規投稿を拒否
  $maxLogBytes = 5 * 1024 * 1024;

  //nullでは接続元とホスト名がループバックのローカルHTTP接続だけSecureを外す
  //本番はHTTPSで運用し、必要に応じてtrueに固定
  $sessionSecure = null;
  //nullでは公開フォームと管理画面に共通するURLパスを自動設定
  $sessionCookiePath = null;
  $adminIdleTimeout = 30 * 60;
  $adminAbsoluteTimeout = 8 * 60 * 60;

  //サーバー側で回数を共有
  //接続元はREMOTE_ADDRで判定（プロキシ由来のヘッダーは信用しない）
  $submissionLimits = ['window' => 60, 'per_ip' => 5, 'global' => 60];
  $loginLimits = ['window' => 15 * 60, 'per_ip' => 5, 'global' => 30];
  $maxPasswordBytes = 1024;
  $maxRequestBytes = 16 * 1024;

?>
