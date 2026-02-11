/**
 * reading-heatmap-cal
 * https://github.com/sorakakeru/reading-heatmap-cal
 * 
 * Copyright (c) 2026 Yamatsu
 * Released under the MIT license
 * https://github.com/sorakakeru/reading-heatmap-cal/blob/main/LICENSE
 */

/**
 * Cal-Heatmap
 * @see https://cal-heatmap.com
 */

const logfile = 'log.json';

//Cal-Heatmapオプション
const calOptions = {
  itemSelector: document.getElementById('cal-heatmap'),
  domain: {
    type: 'month',
    gutter: 3,
    sort: 'asc',
    label: {
      text: 'YY年M月',
      textAlign: 'start',
      position: 'top'
    }
  },
  subDomain: {
    type: 'ghDay',
    width: 14,
    height: 14,
    radius: 2,
    label: null
  },
  date: {
    start: new Date('2026-02-01')
  },
  data: {
    source: logfile,
    x: 'date',
    y: 'count'
  },
  scale: {
    color: {
      range: ['#ededed', '#eaf4e6', '#dae5cf', '#c8d4b7', '#b7c5a0', '#a6b58a', '#94a676', '#819863', '#6f8b53', '#5b8045', '#44753a'],
      type: 'threshold',
      domain: [1, 25, 50, 75, 100, 200, 300, 400, 500, 600, 700]
    }
  }
};

const calTooltip = [
  [Tooltip, {
    enabled: true,
    text: (_, value, dayjsDate) => {
      return `${value ?? 0}ページ ${dayjs(dayjsDate).format('YYYY/MM/DD')}`;
    }
  }]
];

const cal = new CalHeatmap();
cal.paint(calOptions, calTooltip);


/**
 * フォーム送信処理
 */

//フォーム送信処理
const form = document.querySelector('.form_area form');
form.addEventListener('submit', async (e) => {

  const formId = form.getAttribute('id');

  //error&success文言削除
  document.querySelectorAll('.msg').forEach(function(txt) { txt.remove() })

  //バリデーションチェック
  const dd = form.querySelector('dd');
  const inputText = dd.querySelector('input').value;
  if (inputText.length === 0) {
    e.preventDefault();
    dd.insertAdjacentHTML('afterbegin', `<p class="msg error">入力必須項目です</p>`);
  }

  if (formId === 'dataForm') { //ページ入力の場合
    const num = Number(inputText);
    if (Number.isNaN(num) || !Number.isInteger(num)) {
      e.preventDefault();
      dd.insertAdjacentHTML('afterbegin', `<p class="msg error">整数値を入力してください</p>`);
    }

    //フォームデータ送信
    const errText = document.querySelectorAll('.msg.error');
    if (!errText) {
      calOptions.data.source = `${logfile}?t=${Date.now()}`;
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
      });

      if (response.ok) {
        //送信成功時、Cal-Heatmapを再描画
        const cal = new CalHeatmap();
        cal.paint(calOptions, calTooltip);
      } else {
        alert('数値の送信に失敗しました');
      }
    }
  }

});
