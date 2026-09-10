<?php

declare(strict_types=1);

namespace CS2\Steam;

class SkinFloatService
{
    private const API_URL =
        'https://api.csgofloat.com/';

    private string $cacheDirectory;

    public function __construct()
    {
        $this->cacheDirectory =
            BASE_PATH . '/storage/floats';

        if (
            !is_dir($this->cacheDirectory)
        ) {
            mkdir(
                $this->cacheDirectory,
                0775,
                true
            );
        }
    }

    public function getFloat(
        string $inspectUrl,
        string $steamId
    ): ?array {
        $inspectUrl =
            trim($inspectUrl);

        /*
         * Steam puede devolver plantillas
         * como:
         *
         * steam://run/730//+csgo_econ_action_preview%20%propid:6%
         *
         * No contienen los datos reales del objeto.
         */
        if (
            $this->isInvalidTemplate(
                $inspectUrl
            )
        ) {
            return null;
        }

        if (
            !$this->isInspectUrl(
                $inspectUrl
            )
        ) {
            return null;
        }

        /*
         * Los inspect links clásicos contienen
         * S + SteamID + A + assetid + D + ...
         *
         * Si existe S, comprobamos que pertenece
         * al usuario de la sesión.
         */
        if (
            preg_match(
                '/S(\d{17})A\d+D/i',
                $inspectUrl,
                $matches
            )
        ) {
            if (
                $matches[1] !== $steamId
            ) {
                return null;
            }
        }

        $cacheKey =
            hash(
                'sha256',
                $inspectUrl
            );

        $cacheFile =
            $this->cacheDirectory
            . '/'
            . $cacheKey
            . '.json';

        /*
         * Caché.
         */
        if (
            is_file($cacheFile)
        ) {
            $cached =
                json_decode(
                    (string) file_get_contents(
                        $cacheFile
                    ),
                    true
                );

            if (
                is_array($cached)
                &&
                isset($cached['float'])
            ) {
                return $cached;
            }
        }

        $result =
            $this->requestFloat(
                $inspectUrl
            );

        if (
            $result === null
        ) {
            return null;
        }

        file_put_contents(
            $cacheFile,
            json_encode(
                $result,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
            ),
            LOCK_EX
        );

        return $result;
    }

    private function isInvalidTemplate(
        string $url
    ): bool {
        if ($url === '') {
            return true;
        }

        /*
         * Plantillas que Steam deja sin resolver.
         */
        $invalidPatterns = [
            '%propid:',
            '%assetid%',
            '%owner_steamid%',
            '%listingid%',
            '%classid%',
            '%instanceid%',
        ];

        foreach (
            $invalidPatterns as $pattern
        ) {
            if (
                stripos(
                    $url,
                    $pattern
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function isInspectUrl(
        string $url
    ): bool {
        /*
         * Steam puede utilizar ambas variantes.
         */
        if (
            !str_starts_with(
                $url,
                'steam://rungame/730/'
            )
            &&
            !str_starts_with(
                $url,
                'steam://run/730/'
            )
        ) {
            return false;
        }

        if (
            stripos(
                $url,
                'csgo_econ_action_preview'
            ) === false
        ) {
            return false;
        }

        return true;
    }

    private function requestFloat(
        string $inspectUrl
    ): ?array {
        $ch =
            curl_init(
                self::API_URL
            );

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_TIMEOUT => 12,

                CURLOPT_CONNECTTIMEOUT => 5,

                CURLOPT_SSL_VERIFYPEER => true,

                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                ],

                CURLOPT_USERAGENT =>
                    'CS2Inventory/1.0',

                CURLOPT_URL =>
                    self::API_URL
                    . '?url='
                    . rawurlencode(
                        $inspectUrl
                    ),
            ]
        );

        $response =
            curl_exec($ch);

        $httpCode =
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $curlError =
            curl_error($ch);

        curl_close($ch);

        if (
            $response === false
        ) {
            error_log(
                'Float CURL error: '
                . $curlError
            );

            return null;
        }

        if (
            $httpCode !== 200
        ) {
            error_log(
                'Float API HTTP error: '
                . $httpCode
            );

            return null;
        }

        $data =
            json_decode(
                $response,
                true
            );

        if (
            !is_array($data)
        ) {
            return null;
        }

        $itemInfo =
            $data['iteminfo']
            ?? $data;

        if (
            !isset(
                $itemInfo['floatvalue']
            )
        ) {
            return null;
        }

        return [
            'float' =>
                (float)
                $itemInfo['floatvalue'],

            'paint_seed' =>
                isset(
                    $itemInfo['paintseed']
                )
                    ? (int)
                        $itemInfo['paintseed']
                    : null,

            'wear' =>
                $itemInfo['wear_name']
                ?? null,
        ];
    }
}