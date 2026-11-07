<?php
/**
 * Контракт провайдера входа (VK ID, Telegram, позже MAX).
 *
 * Ядро плагина не должно знать деталей OAuth: оно работает с этим интерфейсом.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface WP_SMSN1_RU_Auth_Provider
{
    /** Идентификатор провайдера: 'vk', 'telegram', 'max'. */
    public function get_id();

    /** Название для кнопки: «VK ID», «Telegram». */
    public function get_label();

    /** Настроен ли провайдер (включён и заполнены ключи). */
    public function is_configured();

    /**
     * URL, куда отправляем пользователя.
     *
     * @param string $state         Одноразовый state.
     * @param string $code_verifier PKCE verifier.
     * @param string $redirect_uri  Точный redirect_uri, зарегистрированный в приложении.
     * @param string $device_id     UUID устройства (нужен VK ID).
     * @return string
     */
    public function get_authorize_url($state, $code_verifier, $redirect_uri, $device_id);

    /**
     * Обмен кода на токен.
     *
     * @return array|WP_Error
     */
    public function exchange_code($code, $code_verifier, $redirect_uri, $device_id);

    /**
     * Профиль пользователя по данным токена.
     *
     * @return array{uid:string,email:string,name:string}|WP_Error
     */
    public function get_profile($token);
}
