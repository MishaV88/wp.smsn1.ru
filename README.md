# WP.SMSn1.RU — OTP-авторизация по номеру телефона

[![CI](https://github.com/MishaV88/wp.smsn1.ru/actions/workflows/ci.yml/badge.svg)](https://github.com/MishaV88/wp.smsn1.ru/actions/workflows/ci.yml)

Плагин WordPress: вход и регистрация по номеру телефона через одноразовый код из СМС (OTP),
SMS-уведомления и интеграция с WooCommerce. Отправка СМС — через сервис [smsn1.ru](https://wp.smsn1.ru/)
(личный кабинет: `direct.docs-group.ru`).

> На текущий момент доставка СМС работает **только на номера Российской Федерации**.

## Возможности

- Вход и регистрация по номеру телефона + OTP из СМС (код живёт 5 минут).
- Быстрая регистрация без e-mail (опция «Разрешить быструю регистрацию»).
- Шорткоды `[wp_smsn1_ru_login_form]` и `[wp_smsn1_ru_register_form]`, шаблоны форм можно
  переопределить в теме: `wp-content/themes/<theme>/wp-smsn1-ru/`.
- Подтверждение номера телефона на оформлении заказа WooCommerce (классическая, не блочная форма).
- Редиректы со стандартных страниц `wp-login.php` и `/my-account/` на форму плагина.
- Настраиваемое время жизни сессии («Запомнить меня»).
- Публичные функции для отправки СМС из своего кода:
  `wp_smsn1_ru_do_send_function()`, `wp_smsn1_ru_get_balance()`, `wp_smsn1_ru_get_senders()`,
  а также хуки `wp_smsn1_ru_send_function`, `wp_smsn1_ru_get_balance`, `wp_smsn1_ru_get_senders`.

## Требования

| Компонент | Версия |
|-----------|--------|
| WordPress | 4.1+ (протестировано на 6.7) |
| PHP | 5.6+ (CI проверяет синтаксис на 7.4–8.3) |
| WooCommerce | опционально |

## Установка

1. Скачайте `wp-smsn1-ru.zip` из раздела [Releases](../../releases) (не из «Code → Download ZIP»:
   тот архив содержит исходники репозитория, а не готовый к установке плагин).
2. «Плагины → Добавить новый → Загрузить плагин» → выберите архив → «Установить» → «Активировать».
3. Зарегистрируйтесь в сервисе smsn1.ru, скопируйте **логин** и **API-ключ** из раздела API.
4. «WP.SMSn1.RU» в админ-меню → введите логин и ключ → выберите отправителя СМС.
5. Проверьте ссылки и редиректы в настройках плагина.

После активации плагин создаёт страницы «Войти на сайт» (`login-by-wp-smsn1-ru`),
«Регистрация» (`registation-by-wp-smsn1-ru`) и «Политика обработки персональных данных» (`privacy-policy`).

## Структура репозитория

```
wp-smsn1-ru.php               Точка входа: заголовок плагина, активация, шорткоды, AJAX-обработчики
includes/
  wp-smsn1-ru-functions.php   Клиент API smsn1.ru: подпись, отправка СМС, баланс, отправители
  wp-smsn1-ru-admin.php       Страница настроек, редиректы, колонка «Телефон», уведомления
  wp-smsn1-ru-woo.php         Интеграция с WooCommerce (подтверждение номера на checkout)
templates/                    Шаблоны форм (переопределяются в теме)
assets/                       CSS, JS, иконки
bin/build.sh, bin/build.ps1   Сборка dist/wp-smsn1-ru.zip
docs/                         Проектные документы
```

## Разработка

```bash
git clone https://github.com/MishaV88/wp.smsn1.ru.git
cd wp.smsn1.ru

# сборка архива плагина (Linux/macOS)
bash bin/build.sh

# сборка архива плагина (Windows PowerShell)
pwsh -File bin/build.ps1
```

Архив собирается в `dist/wp-smsn1-ru.zip` и в git не попадает (см. `.gitignore`).
Релиз публикуется тегом `v<версия>`, совпадающим с заголовком `Version:` в `wp-smsn1-ru.php`:

```bash
git tag v1.0.0
git push origin v1.0.0
```

GitHub Actions соберёт архив и приложит его к релизу.

## Статус разработки

- [x] Вход/регистрация по номеру телефона (OTP по СМС)
- [x] Интеграция с WooCommerce (классический checkout)
- [ ] Telegram Login (для пользователей вне РФ)
- [ ] VK ID (OAuth 2.1 + PKCE)
- [ ] MAX (отложено)

Подробнее о плане интеграции провайдеров входа — [docs/auth-providers.md](docs/auth-providers.md).

## Поддержка

- Сайт и инструкция: <https://wp.smsn1.ru/>
- Контакты поддержки: <https://wp.smsn1.ru/контакты-службы-поддержки/>
- Telegram: <https://t.me/sup_smsn1_ru>

## Лицензия

GPL-2.0-or-later. См. [LICENSE](LICENSE).
