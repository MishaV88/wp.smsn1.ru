/**
 * WP.SMSn1.RU — формы входа, регистрации и подтверждения номера.
 *
 * Зависит от jQuery и объекта wp_smsn1_ru_ajax (ajax_url + nonce).
 */
jQuery(document).ready(function ($) {
    'use strict';

    var countdown = 0;
    var countdownInterval = null;

    /**
     * Единый вывод уведомлений: info | success | warning | error.
     */
    function showNotice(message, type) {
        var $box = $('#wp-smsn1-ru-message');

        if (!$box.length) {
            return;
        }

        type = type || 'info';
        $box.html(
            '<div class="wp-smsn1-ru-notice wp-smsn1-ru-notice--' + type + '" role="status">' +
            message +
            '</div>'
        );
    }

    /**
     * Оставляет в номере только цифры и приводит к +7XXXXXXXXXX.
     */
    function normalizePhone(phone) {
        var digits = String(phone || '').replace(/[^0-9]+/g, '');

        if (digits.length === 11) {
            digits = digits.substring(1);
        }

        return digits.length === 10 ? '+7' + digits : 'not_russian_num';
    }

    function updateButtonText(seconds) {
        $('#send-sms-code').text('Отправить код (' + seconds + ' сек)');
        $('#send-sms-code-checkout').text('Отправить код (' + seconds + ' сек)');
    }

    function startCountdown() {
        countdown = 60;
        $('#send-sms-code, #send-sms-code-checkout').prop('disabled', true).addClass('is-busy');

        countdownInterval = setInterval(function () {
            countdown--;
            updateButtonText(countdown);

            if (countdown <= 0) {
                clearInterval(countdownInterval);
                $('#send-sms-code, #send-sms-code-checkout')
                    .prop('disabled', false)
                    .removeClass('is-busy')
                    .text('Отправить код');
            }
        }, 1000);
    }

    /**
     * Показывает поле кода и делает кнопку отправки формы активной.
     */
    function revealCodeField() {
        $('.wp-smsn1-ru-field--code').show();
        $('.wp-smsn1-ru-form button[type="submit"]').addClass('is-ready');
    }

    /**
     * Переход после успешного входа: поддерживает и slug, и абсолютный URL.
     */
    function redirectAfterLogin() {
        var target = $('#wp_smsn1_ru_use_custom_login_redirect_url').val() || '';

        if (!target) {
            return;
        }

        window.location.href = /^https?:\/\//i.test(target)
            ? target
            : '/' + target.replace(/^\/+/, '');
    }

    function handleAjaxError(xhr) {
        var message = (xhr && xhr.responseJSON && xhr.responseJSON.data)
            ? xhr.responseJSON.data
            : 'Ошибка при отправке запроса. Попробуйте ещё раз.';

        showNotice(message, 'error');
    }

    // Отправка кода на формах входа и регистрации
    $('#send-sms-code').on('click', function () {
        if (countdown > 0) {
            return;
        }

        $.ajax({
            url: wp_smsn1_ru_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'send_sms_code',
                phone_number: $('#phone_number').val(),
                type_of_form: $('#type_of_form').val(),
                nonce: wp_smsn1_ru_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                    revealCodeField();
                    startCountdown();
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: handleAjaxError
        });
    });

    // Проверка кода и вход
    $('#wp-smsn1-ru-register-form, #wp-smsn1-ru-login-form').on('submit', function (e) {
        e.preventDefault();

        var password = $('#user_password').val() || '';
        var passwordConfirm = $('#user_password_confirm').val() || '';

        if (passwordConfirm !== '' && password !== passwordConfirm) {
            showNotice('Пароли не совпадают.', 'error');
            return;
        }

        if (!$('.wp-smsn1-ru-field--code').is(':visible')) {
            showNotice('Сначала получите код из СМС.', 'warning');
            return;
        }

        $.ajax({
            url: wp_smsn1_ru_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_sms_code',
                phone_number: $('#phone_number').val(),
                reg_email: $('#reg_email').val(),
                type_of_form: $('#type_of_form').val(),
                sms_code: $('#sms_code').val(),
                user_password: password,
                user_password_confirm: passwordConfirm,
                nonce: wp_smsn1_ru_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                    redirectAfterLogin();
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: handleAjaxError
        });
    });

    // Отправка кода на странице оформления заказа
    $('#send-sms-code-checkout').on('click', function () {
        if (countdown > 0) {
            return;
        }

        var phoneNumber = normalizePhone($('#custom_phone').val());
        $('#billing_phone').val(phoneNumber);
        $('#shipping_phone').val(phoneNumber);

        $.ajax({
            url: wp_smsn1_ru_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'send_sms_code',
                phone_number: phoneNumber,
                type_of_form: 'checkout',
                nonce: wp_smsn1_ru_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                    revealCodeField();
                    startCountdown();
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: handleAjaxError
        });
    });

    // Чекбокс «Создать аккаунт» на checkout: проверяем, не занят ли номер
    $('#createaccount').on('change', function () {
        if (!$(this).is(':checked')) {
            return;
        }

        $.ajax({
            url: wp_smsn1_ru_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_user_by_phone',
                phone_number: $('#custom_phone').val(),
                nonce: wp_smsn1_ru_ajax.nonce
            },
            success: function (response) {
                if (response.success && response.data.exists) {
                    showNotice(
                        'Пользователь с таким номером телефона уже существует. ' +
                        '<a href="' + response.data.res_url + '">Войдите в систему</a>.',
                        'warning'
                    );
                    $('#createaccount').prop('checked', false);
                } else if (!response.success) {
                    showNotice('Не удалось проверить номер телефона.', 'error');
                }
            },
            error: function (xhr) {
                var message = (xhr && xhr.responseJSON && xhr.responseJSON.data)
                    ? xhr.responseJSON.data
                    : 'Не удалось проверить номер телефона.';

                showNotice(message, 'error');
            }
        });
    });

    // Если скрипт Telegram не загрузился (сеть блокирует telegram.org) — показываем пояснение
    setTimeout(function () {
        $('[data-wp-smsn1-ru-telegram]').each(function () {
            var $block = $(this);

            if ($block.find('iframe').length === 0) {
                $block.find('.wp-smsn1-ru-telegram-fallback').prop('hidden', false);
            }
        });
    }, 3500);
});
