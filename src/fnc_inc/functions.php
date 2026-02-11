<?php
//CSRFトークン生成
function generate_token() {
  return bin2hex(random_bytes(32));
}

//CSRFトークン検証
function validate_token($token) {
  //送信されてきた$tokenが生成したハッシュと一致するか
  return isset($_SESSION['token']) && hash_equals($_SESSION['token'], $token);
}

//XSS対策
function h($str) {
	if (is_array($str)) {
		return array_map('h', $str);
	} else {
		return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
	}
}

//ヒアドキュメント内での展開用（{$_(定数名)}）
$_ = function($s) { return $s; };
?>