<?php
/**
 * Провайдер входа VK ID (OAuth 2.1 + PKCE).
 *
 * Документация: https://id.vk.com/about/business/go/docs
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SMSN1_RU_Provider_VK implements WP_SMSN1_RU_Auth_Provider
{
    const AUTHORIZE_URL = 'https://id.vk.com/authorize';
    const TOKEN_URL     = 'https://id.vk.com/oauth2/auth';
    const API_URL       = 'https://api.vk.com/method/';
    const API_VERSION   = '5.199';

    public function get_id()
    {
        return 'vk';
    }

    public function get_label()
    {
        return 'VK ID';
    }

    public function is_configured()
    {
        return get_option('wp_smsn1_ru_vk_enabled') === 'yes'
            && '' !== (string) get_option('wp_smsn1_ru_vk_client_id');
    }

    public function get_authorize_url($state, $code_verifier, $redirect_uri, $device_id)
    {
        return add_query_arg(
            array(
                'response_type'         => 'code',
                'client_id'             => get_option('wp_smsn1_ru_vk_client_id'),
                'redirect_uri'          => $redirect_uri,
                'scope'                 => 'vkid.personal_info email',
                'state'                 => $state,
                'code_challenge'        => wp_smsn1_ru_pkce_challenge($code_verifier),
                'code_challenge_method' => 'S256',
                'device_id'             => $device_id,
            ),
            self::AUTHORIZE_URL
        );
    }

    public function exchange_code($code, $code_verifier, $redirect_uri, $device_id)
    {
        $body = array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => $code_verifier,
            'client_id'     => get_option('wp_smsn1_ru_vk_client_id'),
            'device_id'     => $device_id,
            'redirect_uri'  => $redirect_uri,
        );

        // Защищённый ключ нужен только «конфиденциальным» приложениям.
        $secret = (string) get_option('wp_smsn1_ru_vk_client_secret');
        if ($secret !== '') {
            $body['client_secret'] = $secret;
        }

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'timeout' => 15,
                'body'    => $body,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || empty($data['access_token'])) {
            $error = is_array($data) && isset($data['error_description'])
                ? $data['error_description']
                : 'VK ID не вернул access_token';

            return new WP_Error('wp_smsn1_ru_vk_token', $error);
        }

        return $data;
    }

    public function get_profile($token)
    {
        $user_id = isset($token['user_id']) ? (string) $token['user_id'] : '';

        if ($user_id === '') {
            return new WP_Error('wp_smsn1_ru_vk_uid', 'VK ID не вернул идентификатор пользователя');
        }

        $profile = array(
            'uid'   => $user_id,
            'email' => !empty($token['email']) && is_email($token['email']) ? $token['email'] : '',
            'name'  => '',
        );

        $url = add_query_arg(
            array(
                'user_ids'     => $user_id,
                'fields'       => 'photo_200,email',
                'access_token' => $token['access_token'],
                'v'            => self::API_VERSION,
            ),
            self::API_URL . 'users.get'
        );

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return $profile; // данных достаточно, имя не критично
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($data['response'][0]) && is_array($data['response'][0])) {
            $user = $data['response'][0];

            $name = trim(
                (isset($user['first_name']) ? $user['first_name'] : '') . ' ' .
                (isset($user['last_name']) ? $user['last_name'] : '')
            );

            if ($name !== '') {
                $profile['name'] = $name;
            }

            if ($profile['email'] === '' && !empty($user['email']) && is_email($user['email'])) {
                $profile['email'] = $user['email'];
            }
        }

        return $profile;
    }
}
