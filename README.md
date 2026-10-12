# emoji-message-form

絵文字とメッセージを送ることのできる簡易フォーム

## 設置について

1. `/src`ディレクトリ直下に`.env`ファイルを作成する

```txt
ADMIN_PW='[ハッシュ化したパスワード]'
```

2. 同じく`/src`ディレクトリ直下に`.htaccess`ファイルを設置し、`.`ファイルと`log.json`へのアクセスを禁止する

```apache
<Files ~ "^\.">
  Require all denied
</Files>

<Files "log.json">
  Require all denied
</Files>
```

3. 同じく`/src`ディレクトリ直下に`/_runtime`ディレクトリを作成し、その中に`.htaccess`ファイルを置いて以下を記述しアクセス拒否する

```apache
Require all denied
```

4. `/src/_modules/fnc_inc/config.php`で項目を設定する
5. `/src`ディレクトリをサーバーにアップロードする

## 使用ライブラリ

以下のライブラリを利用しています。

- [Twig](https://twig.symfony.com) (BSD-3-Clause License)
- [PHP dotenv](https://github.com/vlucas/phpdotenv) (BSD-3-Clause License)

## 本スクリプトのライセンス

MIT License
