<?php
/**
 * Интерфейс для провайдеров «виджетного» входа (Telegram Login Widget).
 *
 * В отличие от OAuth-редиректа (VK), здесь провайдер сам присылает подписанные
 * данные на наш callback, а мы их проверяем.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface WP_SMSN1_RU_Auth_Provider_Widget
{
    /**
     * HTML кнопки/виджета для формы входа.
     *
     * @param string $callback_url Куда провайдер вернёт пользователя.
     * @return string
     */
    public function get_button_html($callback_url);

    /**
     * Проверка подписи и разбор профиля.
     *
     * @param array $request Параметры GET от провайдера.
     * @return array{uid:string,email:string,phone:string,name:string}|WP_Error
     */
    public function verify_callback($request);
}
