# Подготовка к публикации на WordPress.org

Черновик-чеклист. Здесь зафиксировано, что уже сделано в репозитории и что осталось,
чтобы подать плагин в каталог.

## Решения, которые нужно подтвердить

| Параметр | Текущее значение | Комментарий |
|---|---|---|
| Slug | `wp-smsn1-ru` | совпадает с папкой плагина и префиксом; на 15.11.2026 свободен |
| Text Domain | `wp-smsn1-ru` | задан в заголовке, домен = slug |
| Название | WP.SMSn1.RU — вход по номеру телефона и SMS-уведомления | без keyword stuffing |
| Requires PHP | 7.4 | CI проверяет 7.4–8.3 |
| Requires at least | 5.6 | |
| Tested up to | 7.1 | |

Если slug меняется — придётся синхронно поменять текстовый домен, `Domain Path`
и имя папки в архиве. Дешевле определиться до подачи.

## Сделано

- [x] Заголовок плагина: `Text Domain`, `Domain Path`, `Requires at least`, `Requires PHP`,
      `License: GPL-2.0-or-later`.
- [x] `load_plugin_textdomain()` + папка `languages/`.
- [x] `readme.txt` в формате WordPress.org: стандартные поля, описание, установка, FAQ,
      скриншоты, changelog, upgrade notice, раскрытие внешнего сервиса.
- [x] `uninstall.php`: удаляет опции, транзиенты и служебные мета-поля (номер в `billing_phone`
      сохраняется — это данные WooCommerce).
- [x] Инструменты приватности: экспорт и удаление персональных данных
      (`includes/wp-smsn1-ru-privacy.php`).
- [x] Ассеты в `wporg-assets/`: баннеры 1544×500 и 772×250, иконки 256 и 128.
- [x] Сборка включает `uninstall.php` и `languages/`.

## Осталось

### 1. i18n (главный блокер)

Сейчас 13 вызовов `__()` и почти все строки — жёстко по-русски в PHP и шаблонах.

Нужно:

- [ ] перевести исходные строки на английский и обернуть в `__()`/`esc_html__()`
      с доменом `wp-smsn1-ru` (около 250 строк в `wp-smsn1-ru.php`, `includes/*`, `templates/*`);
- [ ] строки JS — через `wp_localize_script` (сейчас часть текстов прямо в `script.js`);
- [ ] сгенерировать `languages/wp-smsn1-ru.pot`;
- [ ] сделать русский перевод `wp-smsn1-ru-ru_RU.po` + `.mo`.

Инструменты: `wp i18n make-pot`, Poedit или `xgettext`. Без WP-CLI —
`php -r` с обходом файлов тоже подойдёт.

### 2. Скриншоты

Нужны 2–3 реальных скриншота 1200×900 (или больше):

- `screenshot-1.png` — форма входа с кнопками VK и Telegram;
- `screenshot-2.png` — настройки плагина;
- `screenshot-3.png` — блок «Способы входа» в личном кабинете.

Кладутся в `wporg-assets/`, при публикации — в SVN `/assets/`.

### 3. Код-ревью мелочи

- [ ] `wp_smsn1_ru_get_balance()` вызывается на фронте в шорткодах — ревьюеры спрашивают,
      зачем внешний запрос на странице. Лучше оставить только в админке и в момент отправки.
- [ ] Прогнать PHPCS с `WordPress` стандартом, поправить отступы и inline-стили.
- [ ] Проверить, что нигде нет `$_POST` без `wp_unslash` и `sanitize_*`.
- [ ] Убедиться, что админ-нотисы можно закрыть (сейчас уведомление Backuply — чужое, наше ок).

## Как подать заявку

1. Собрать архив: `bash bin/build.sh` → `dist/wp-smsn1-ru.zip`.
2. Проверить состав: `unzip -l dist/wp-smsn1-ru.zip` — только файлы плагина,
   папка `wp-smsn1-ru/` в корне, без `wporg-assets/`, `tests/`, `docs/`, `.github/`.
3. Заполнить форму: <https://wordpress.org/plugins/developers/add/>
   - имя и описание;
   - загрузить zip;
   - указать, что плагин использует внешний сервис smsn1.ru (обязательно).
4. Дождаться письма ревьювера. Типичные замечания: i18n, экранирование, запросы к API,
   раскрытие сервиса, лицензия ассетов.
5. После одобрения — SVN:

```bash
svn co https://plugins.svn.wordpress.org/wp-smsn1-ru/ wp-smsn1-ru-svn
cp -R dist/wp-smsn1-ru/* wp-smsn1-ru-svn/trunk/
svn add --force wp-smsn1-ru-svn/trunk
svn ci -m "1.8.0" wp-smsn1-ru-svn/trunk
svn cp wp-smsn1-ru-svn/trunk wp-smsn1-ru-svn/tags/1.8.0
svn ci -m "tag 1.8.0" wp-smsn1-ru-svn/tags/1.8.0
cp wporg-assets/* wp-smsn1-ru-svn/assets/
svn add --force wp-smsn1-ru-svn/assets
svn ci -m "assets" wp-smsn1-ru-svn/assets
```

6. Дальнейшие релизы: обновить `Version`, `Stable tag`, `CHANGELOG.md`, затем
   `trunk` + новая папка в `tags/`.

## Полезное

- Требования: <https://wordpress.org/plugins/developers/#guidelines>
- Readme-валидатор: <https://wordpress.org/plugins/developers/readme-validator/>
- Пример «правильного» readme: <https://wordpress.org/plugins/sms-ru/>
