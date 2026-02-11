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

  //ページタイトル
  $title = 'reading-heatmap-cal';

  //絵文字リスト
  $arr_emoji = [
    '👏',
    '🎉',
    '😊',
    '🐥',
    '☕️'
  ];

  //ページタイトル下の説明文（テキストのみ、改行有効）
  $DESCRIPTION = <<<EOT
    ここにテキストを書きます
    改行有効
  EOT;

  //サイト名（サイトに戻るリンクで使用）
  //空欄の場合はサイトに戻るリンクは表示されません
  $siteName = 'example site name';

  //サイトURL（サイトに戻るリンクで使用）
  $siteURL = 'https://example.com';

  //一度に送信できるコメント数の最大値（全角）
  $maxCount = 500;

  //送信完了時に表示するメッセージ（1行でテキストのみ）
  $thanxMsg = '送信しました、ありがとうございます！';

  //管理画面での1ページの表示件数
  $page = 10;

  //ログファイル（基本的にはこのまま）
  $log_file = __DIR__. '/../../log.json';


?>