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

  //ページタイトル下の説明文
  //テキストのみでHTMLタグは無効、改行は有効です
  $description = <<<EOT
    絵文字やメッセージを送ることができます（メッセージは任意）
    改行有効です
  EOT;

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

  //ログファイル（ファイル名や場所の変更がなければ、このままでOKです）
  $log_file = __DIR__. '/../../log.json';

?>
