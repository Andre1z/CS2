<?php

declare(strict_types=1);

namespace CS2\Steam;

class SteamAuth
{
    private string $steamOpenIdUrl;
    private string $returnUrl;

    public function __construct(string $returnUrl)
    {
        $this->steamOpenIdUrl = 'https://steamcommunity.com/openid/';
        $this->returnUrl = $returnUrl;
    }

    /**
     * Genera la URL que nos llevará a Steam.
     */
    public function getLoginUrl(): string
    {
        $params = [
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',

            'openid.return_to' => $this->returnUrl,

            'openid.realm' => $this->getRealm(),

            'openid.identity' =>
                'http://specs.openid.net/auth/2.0/identifier_select',

            'openid.claimed_id' =>
                'http://specs.openid.net/auth/2.0/identifier_select'
        ];

        return $this->steamOpenIdUrl . '?' . http_build_query($params);
    }

    /**
     * Comprueba la respuesta que devuelve Steam.
     */
    public function validateResponse(array $data): bool
    {
        if (
            empty($data['openid_claimed_id']) ||
            empty($data['openid_response_nonce']) ||
            empty($data['openid_assoc_handle'])
        ) {
            return false;
        }

        $params = [];

        foreach ($data as $key => $value) {

            if (str_starts_with($key, 'openid_')) {

                $openidKey = str_replace(
                    'openid_',
                    'openid.',
                    $key
                );

                $params[$openidKey] = $value;
            }
        }

        $params['openid.mode'] = 'check_authentication';

        $ch = curl_init($this->steamOpenIdUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS =>
                http_build_query($params),

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 10,

            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);

        if ($response === false) {

            curl_close($ch);

            return false;
        }

        curl_close($ch);

        return str_contains(
            $response,
            'is_valid:true'
        );
    }

    /**
     * Extrae el SteamID64.
     */
    public function getSteamId(array $data): ?string
    {
        if (empty($data['openid_claimed_id'])) {
            return null;
        }

        $prefix = 'https://steamcommunity.com/openid/id/';

        if (
            !str_starts_with(
                $data['openid_claimed_id'],
                $prefix
            )
        ) {
            return null;
        }

        $steamId = substr(
            $data['openid_claimed_id'],
            strlen($prefix)
        );

        if (!ctype_digit($steamId)) {
            return null;
        }

        return $steamId;
    }

    private function getRealm(): string
    {
        $url = parse_url($this->returnUrl);

        if (
            !$url ||
            empty($url['scheme']) ||
            empty($url['host'])
        ) {
            throw new \RuntimeException(
                'APP_URL no es válida.'
            );
        }

        return $url['scheme'] . '://' . $url['host'];
    }
}