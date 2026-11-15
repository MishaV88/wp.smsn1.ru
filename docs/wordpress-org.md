# Подготовка к публикации на WordPress.org

Черновик-чеклист. Здесь зафиксировано, что уже сделано в репозитории и что осталось,
чтобы подать плагин в каталог.

## Принятые решения

| Параметр | Значение | Комментарий |
|---|---|---|
| Slug | `wp-smsn1-ru` | **зафиксирован**: совпадает с доменом основного сайта, папкой плагина и префиксом функций |
| Text Domain | `wp-smsn1-ru` | домен = slug |
| Название | WP.SMSn1.RU — вход по номеру телефона и SMS-уведомления | бренд основного сайта |
| Основная площадка | <https://wp.smsn1.ru/> | документация, поддержка, аккаунты, релизы |
| WordPress.org | дополнительный канал | каталог и автообновления, но не единственная точка входа |
| Requires PHP | 7.4 | CI проверяет 7.4–8.3 |
| Requires at least | 5.6 | |
| Tested up to | 7.1 | |

Что следует из «основной сайт — wp.smsn1.ru»:

- бренд и slug не меняются, текстовый домен остаётся `wp-smsn1-ru`;
- в `readme.txt` ссылки ведут на сайт (условия, поддержка, инструкция);
- плагин **не должен** содержать собственный механизм обновлений с внешнего сервера —
  обновления идут из каталога WP.org (это требование ревью, сейчас у нас такого кода нет);
- релизы на GitHub остаются для разработки и быстрой установки, но публичной
  «точкой правды» для пользователей считается сайт.

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

Часть работы уже сделана: строки обёрнуты в `__()`/`esc_html__()` с доменом `wp-smsn1-ru`,
собран `languages/wp-smsn1-ru.pot` (70 строк).

- [x] шаблоны форм (`templates/*.php`);
- [x] тексты JS — вынесены в `wp_localize_script` (`wp_smsn1_ru_ajax.strings`);
- [x] сообщения AJAX-обработчиков в `wp-smsn1-ru.php`;
- [x] тексты кодов и лимитов в `includes/wp-smsn1-ru-otp.php`;
- [x] интерфейс привязки VK/Telegram и уведомления в `includes/wp-smsn1-ru-oauth.php`;
- [x] `languages/wp-smsn1-ru.pot`.
- [ ] настройки в админке (`includes/wp-smsn1-ru-admin.php`) — около 120 строк, самый
      большой оставшийся кусок;
- [ ] строки `wp_die()` в oauth-модуле (сообщения об ошибках входа);
- [ ] решить судьбу языка исходника: оставить русский (сейчас так, UI не меняется)
      или перевести на английский и добавить `wp-smsn1-ru-ru_RU.po/.mo`.

Пересобрать POT: `wp i18n make-pot . languages/wp-smsn1-ru.pot --domain=wp-smsn1-ru`
или Poedit.

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
