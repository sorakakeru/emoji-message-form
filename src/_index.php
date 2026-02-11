<?php
  session_start();

  //include setting
  require_once __DIR__. '/fnc_inc/functions.php';
  require_once __DIR__. '/fnc_inc/config.php';
    
  //token
  if (empty($_SESSION['token'])) {
    $_SESSION['token'] = generate_token();
  }
  $token = h($_SESSION['token']);
?>

<?php
  include_once __DIR__. '/tmpl/header.html';
  echo $HEADER_TXT;
?>

<?php if (!file_exists($log_file)): //$log_fileファイルの存在チェック ?>
  <p class="error">データ保存用jsonファイルを作成してください</p>
<?php else: ?>
  <div class="form_area">
    <form action="" method="post">
      <input type="hidden" name="token" value="<?php echo $token; ?>">
      <ul>
        <?php
          foreach($arr_food as $key => $value) {
            echo '<li><label><input type="radio" name="radio_food" value="' .$value['explain']. '"><span>' .$value['food']. '</span>' .$value['explain']. '</label></li>';
          }
        ?>
      </ul>
      <dl>
        <dt><label for="new_explain">あたらしくプレゼントをつくる</label></dt>
        <dd>
          <div class="btn_choose"><button type="button" name="btn_emoji">choice</button><div class="picker_area"></div></div>
          <input type="text" name="new_food" value="🎁" id="new_food">
          <input type="text" name="new_explain" id="new_explain" placeholder="あたらしくつくるプレゼントの名称">
        </dd>
        <dd><span>絵文字を1つ選んでその名称を入れてください</span></dd>
        <dt><label for="comment">何かメッセージがあれば</label></dt>
        <dd>
          <textarea name="comment" id="comment"></textarea>
        </dd>
      </dl>
      <div class="btn_area">
        <button type="submit" name="send" onclick="return confirm();">送信</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php

  if (file_exists($log_file)) { //$log_fileファイルが存在していたら処理する

    //ボタンが押された時の処理
    if(isset($_REQUEST['send'])) {

      //token確認
      if(!validate_token($_POST['token'])) {
        echo '<p class="error">Error: 送信できません</p>';
        //exit;
      } else { //token認証通ったら行う処理

        if (empty($_REQUEST['radio_food']) && empty($_REQUEST['new_food'])) { //お菓子が入っていない場合
          echo '<p class="error">お菓子を1つ選択または新しく何かを作ってください</p>';
        } else {

          //jsonファイル読み込み
          $send_food = json_decode(file_get_contents($log_file), true);
          $send_food = $send_food ?: []; //ファイル破損や中身が空の場合

          //変数に格納
          $date = date('Y-m-d H:i:s');

          //絵文字処理
          if(!empty($_REQUEST['new_explain'])) {
            $food = $_REQUEST['new_food']. ' ' .mb_substr($_REQUEST['new_explain'], 0, 255);
          } else {
            $food = $_REQUEST['radio_food'];
            if (!in_array($_REQUEST['radio_food'], array_column($arr_food, 'explain'), true)) {
              echo '<p class="error">Error: 不正な絵文字を検知したため送信できません</p>';
            } else {
              $food_key = array_search($_REQUEST['radio_food'], array_column($arr_food, 'explain'));
              $food = $arr_food[$food_key]['food']. ' ' .$arr_food[$food_key]['explain'];
            }
          }

          //コメント500文字まで
          $comment = !empty($_REQUEST['comment']) ? mb_substr($_REQUEST['comment'], 0, 500) : ''; //str_replace(["\r\n", "\r", "\n"], '\n', h($_REQUEST['comment']))

          if (is_string($food) === false || is_string($comment) === false) {
            echo '<p class="error">Error: 不正な文字を検知したため送信できません</p>';
          } else {
            $send_food[] = ['date'=>$date, 'food'=>$food, 'comment'=>$comment];

            //ファイル書き込み
            if (file_put_contents($log_file, json_encode($send_food, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
              echo '<p class="error">Error: 送信内容の保存に失敗しました</p>';
            } else {
              echo '<p class="thanks">ありがとうございます！！！</p>';
            }
          }

        }

      }

    }

  }

?>

<?php
  echo $FOOTER_TXT;
  include_once __DIR__. '/tmpl/footer.html';
?>
