/**
 * emoji-message-form
 * https://github.com/sorakakeru/emoji-message-form
 * 
 * Copyright (c) 2026 Yamatsu
 * Released under the MIT license
 * https://github.com/sorakakeru/emoji-message-form/blob/main/LICENSE
 */

/**
 * 文字数カウント
 */
function countText(elm) {
  const maxCountElm = elm.closest('dd').querySelector('.count span');
  const currentLength = elm.value.length;

  if (maxCountElm) {
    const maxLength = parseInt(maxCountElm.dataset.maxcount, 10) || parseInt(maxCountElm.textContent, 10);
    const remain = maxLength - currentLength;
    maxCountElm.textContent = remain;

    currentLength > maxLength
      ? maxCountElm.classList.add('msg', 'error')
      : maxCountElm.classList.remove('msg', 'error');
  }
}

function addCountEvent(selector) {
  document.querySelectorAll(selector).forEach(function(elm) {
    elm.addEventListener('input', function() { countText(elm) });
  })
}
addCountEvent('dd textarea');


/**
 * 入力フォームバリデーションチェック
 */
//ラジオボタン（絵文字）
function validateChoice(elm) {
  const checked = elm.querySelectorAll('input[type="radio"]:checked');
  let error = '';

  if (checked.length === 0) {
    error = '絵文字をどれか1つ選択してください';
    elm.insertAdjacentHTML('afterend', `<p class="msg error">${error}</p>`);
    return false;
  }
  return true;
}

//メッセージ（複数行テキスト）
function validateInput(elm) {
  const dd = elm.closest('dd');
  const countElm = dd.querySelector('.count span');
  const value = elm.value;
  let error = '';

  if (countElm) {
    const maxCount = parseInt(countElm.dataset.maxcount, 10);
    if (maxCount && value.length > maxCount) {
      error = '送信できる文字数を超えています';
    }
  }

  if (error) {
    dd.insertAdjacentHTML('beforeend', `<p class="msg error">${error}</p>`);
    return false;
  }
  return true;
}

//送信ボタンを押した処理
const form = document.getElementById('msgForm');
form.addEventListener('submit', (e) => {

  //error&success文言削除
  document.querySelectorAll('p.error').forEach(function(txt) { txt.remove() });
  document.querySelector('p.success') && document.querySelector('p.success').remove();

  if (!validateChoice(form.querySelector('.emoji_list'))) e.preventDefault();
  if (!validateInput(form.querySelector('dd textarea'))) e.preventDefault();

});
