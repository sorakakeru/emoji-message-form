<?php

  $arr_food = [
    ['food' => '🐥', 'explain' => '名菓ひよ子'],
    ['food' => '🐤', 'explain' => 'ショコラひよ子（東京）'],
    ['food' => '🍮', 'explain' => 'モロゾフのプリン'],
    ['food' => '🍩', 'explain' => 'ミスドのオールドファッション（プレーン）'],
    ['food' => '🥔', 'explain' => 'ポテトチップス（関西だししょうゆ）'],
    ['food' => '🍪', 'explain' => 'ステラおばさんのクッキー'],
    ['food' => '🍘', 'explain' => '湿気ったしょうゆせんべい'],
    ['food' => '🧀', 'explain' => 'Kiriのクリームチーズ'],
    ['food' => '🍔', 'explain' => 'チキンタツタ'],
    ['food' => '🍟', 'explain' => 'マックフライポテト'],
    ['food' => '🥤', 'explain' => 'マックシェイク（バニラ）'],
    ['food' => '🐙', 'explain' => 'くくるのたこ焼き'],
    ['food' => '🦀', 'explain' => 'かにぱん'],
    ['food' => '🍜', 'explain' => 'めちゃくちゃニラの入ってる味噌ラーメン'],
    ['food' => '🦒', 'explain' => 'キリンレモン']
  ];

  $page = 10;
  $log_file = __DIR__. '/../log.json';

  $HEADER_TXT = <<<EOT
    <div class="back"><a href="../">nemmmui.info</a></div>
    <h1>gift box 🎁</h1>
    <p>絵文字とメッセージを送れます。拍手代わりに気軽に送っていただけるとうれしいです。</p>
    <p>あらかじめ用意しているもののほか、絵文字とテキストを入力することもできます。どちらかは必ず選んでください。なんでもOKです！<br>
    メッセージは任意です（いただけるととてもうれしいです）</p>
  EOT;

  $FOOTER_TXT = <<<EOT
    <small><a href="../">nemmmui.info</a></small>
  EOT;

?>