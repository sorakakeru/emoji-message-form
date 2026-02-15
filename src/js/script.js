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

/*

function countText(elm) {
  const maxCountElm = elm.closest('dd').querySelector('.count span')
  const currentLength = elm.value.length

  if (maxCountElm) {
    const maxLength = parseInt(maxCountElm.dataset.maxcount, 10) || parseInt(maxCountElm.textContent, 10)
    const remain = maxLength - currentLength
    maxCountElm.textContent = remain

    currentLength > maxLength
      ? maxCountElm.classList.add('error')
      : maxCountElm.classList.remove('error')
  }
}

function addCountEvent(selector) {
  document.querySelectorAll(selector).forEach(function(elm) {
    elm.addEventListener('input', function() { countText(elm) })
  })
}

// テキストエリア（1行）
addCountEvent('dd input[type="text"]')

// テキストエリア（複数行）
addCountEvent('dd textarea')
*/

/**
 * 入力フォームバリデーションチェック
 */

/*
//1行＆複数行テキストエリア
function validateInput(elm, type) {
  const dd = elm.closest('dd')
  const required = dd.previousElementSibling.querySelector('.required')
  const countElm = dd.querySelector('.count span')
  const value = elm.value
  let error = ''

  if (required && value.length === 0) error = '入力必須項目です'

  if (countElm) {
    const maxCount = parseInt(countElm.dataset.maxcount, 10) || parseInt(countElm.textContent, 10)
    if (maxCount && value.length > maxCount) error = '送信できる文字数を超えています'
  }

  if (error) {
    dd.insertAdjacentHTML('beforeend', `<p class="error">${error}</p>`)
    return false
  }
  return true
}

//チェックボックス＆ラジオボタン
function validateChoice(elm, type) {
  const dd = elm.closest('dd')
  const required = dd.previousElementSibling.querySelector('.required')
  const checked = elm.querySelectorAll(`input[type="${type}"]:checked`)
  let error = ''

  if (required && checked.length === 0) {
    error = type === 'radio' ? '1つ選択してください' : '1つ以上選択してください'
    dd.insertAdjacentHTML('beforeend', `<p class="error">${error}</p>`)
    return false
  }
  return true
}

//送信ボタンを押した処理
const form = document.getElementById('enqForm')
form.addEventListener('submit', (e) => {

  //error&success文言削除
  document.querySelectorAll('.form_area p.error').forEach(function(txt) { txt.remove() })
  document.querySelector('.success') && document.querySelector('.success').remove()

  //ラジオボタン
  form.querySelectorAll('dd:has(input[type="radio"])').forEach(elm => {
    if (!validateChoice(elm, 'radio')) e.preventDefault()
  })

  //チェックボックス
  form.querySelectorAll('dd:has(input[type="checkbox"])').forEach(elm => {
    if (!validateChoice(elm, 'checkbox')) e.preventDefault()
  })

  //テキスト（1行）
  form.querySelectorAll('dd input[type="text"]').forEach(elm => {
    if (!validateInput(elm, 'text')) e.preventDefault()
  })

  //テキストエリア（複数行）
  form.querySelectorAll('dd textarea').forEach(elm => {
    if (!validateInput(elm, 'textarea')) e.preventDefault()
  })

})
*/

