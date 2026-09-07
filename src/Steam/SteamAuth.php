<?php

declare(strict_types=1);

namespace CS2\Steam;

class SteamAuth
{
    /**
     * Endpoint utilizado para iniciar el login de Steam.
     */
    private const STEAM_LOGIN_URL =
        'https://steamcommunity.com/openid/login';

    /**
     * Endpoint utilizado para validar la respuesta.
     */
    private const STEAM_VALIDATE_URL =
        'https://steamcommunity.com/openid/login';

    private string $returnUrl;

    public function __construct(string $returnUrl)
    {
        $this->returnUrl = $returnUrl;
    }

    /**
     * Genera la URL de login de Steam.
     */
    public function getLoginUrl(): string
    {
        $params = [
            'openid.ns' =>
                'http://specs.openid.net/auth/2.0',

            'openid.mode' =>
                'checkid_setup',

            'openid.return_to' =>
                $this->returnUrl,

            'openid.realm' =>
                $this->getRealm(),

            'openid.identity' =>
                'http://specs.openid.net/auth/2.0/identifier_select',

            'openid.claimed_id' =>
                'http://specs.openid.net/auth/2.0/identifier_select'
        ];

        return self::STEAM_LOGIN_URL
            . '?'
            . http_build_query($params);
    }

    /**
     * Valida la respuesta recibida de Steam.
     */
    public function validateResponse(array $data): bool
    {
        if (
            empty($data['openid_mode']) ||
            $data['openid_mode'] !== 'id_res'
        ) {
            return false;
        }

        if (empty($data['openid_claimed_id'])) {
            return false;
        }

        $params = [];

        foreach ($data as $key => $value) {
            if (!str_starts_with($key, 'openid_')) {
                continue;
            }

            $openidKey = str_replace(
                'openid_',
                'openid.',
                $key
            );

            $params[$openidKey] = $value;
        }

        $params['openid.mode'] =
            'check_authentication';

        $ch = curl_init(
            self::STEAM_VALIDATE_URL
        );

        curl_setopt_array($ch, [
            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS =>
                http_build_query($params),

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 15,

            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],

            CURLOPT_USERAGENT =>
                'CS2Inventory/1.0'
        ]);

        $response = curl_exec($ch);

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            error_log(
                'Steam CURL Error: ' . $curlError
            );

            return false;
        }

        if ($httpCode !== 200) {
            error_log(
                'Steam HTTP Error: ' . $httpCode
            );

            return false;
        }

        return str_contains(
            $response,
            'is_valid:true'
        );
    }

    /**
     * Obtiene el SteamID64 del usuario.
     */
    public function getSteamId(
        array $data
    ): ?string {
        if (
            empty($data['openid_claimed_id'])
        ) {
            return null;
        }

        $claimedId =
            $data['openid_claimed_id'];

        $prefix =
            'http://steamcommunity.com/openid/id/';

        $prefixHttps =
            'https://steamcommunity.com/openid/id/';

        if (
            str_starts_with(
                $claimedId,
                $prefix
            )
        ) {
            $steamId = substr(
                $claimedId,
                strlen($prefix)
            );
        } elseif (
            str_starts_with(
                $claimedId,
                $prefixHttps
            )
        ) {
            $steamId = substr(
                $claimedId,
                strlen($prefixHttps)
            );
        } else {
            return null;
        }

        if (!ctype_digit($steamId)) {
            return null;
        }

        return $steamId;
    }

    /**
     * Obtiene el dominio de nuestra web.
     */
    private function getRealm(): string
    {
        $url = parse_url(
            $this->returnUrl
        );

        if (
            !$url ||
            empty($url['scheme']) ||
            empty($url['host'])
        ) {
            throw new \RuntimeException(
                'APP_URL no es válida.'
            );
        }

        $realm =
            $url['scheme']
            . '://'
            . $url['host'];

        /*
         * Importante para localhost:8000.
         */
        if (!empty($url['port'])) {
            $realm .= ':' . $url['port'];
        }

        return $realm;
    }
}