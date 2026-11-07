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
     * @param string $state         Одноразовый state (не короче 32 символов).
     * @param string $code_verifier PKCE verifier.
     * @param string $redirect_uri  Точный redirect_uri, зарегистрированный в приложении.
     * @return string
     */
    public function get_authorize_url($state, $code_verifier, $redirect_uri);

    /**
     * Обмен кода на токен.
     *
     * @param string $code          Код подтверждения из callback.
     * @param string $code_verifier PKCE verifier.
     * @param string $redirect_uri  Тот же redirect_uri, что и в авторизации.
     * @param string $device_id     device_id, который вернул провайдер.
     * @param string $state         State из callback.
     * @return array|WP_Error
     */
    public function exchange_code($code, $code_verifier, $redirect_uri, $device_id, $state);

    /**
     * Профиль пользователя по данным токена.
     *
     * @return array{uid:string,email:string,name:string}|WP_Error
     */
    public function get_profile($token);
}
