<?php

declare(strict_types=1);

namespace CS2\Steam;

class SkinFloatService
{
    private const API_URL =
        'https://api.csgofloat.com/';

    private const CACHE_DIRECTORY =
        __DIR__ . '/../../storage/floats';

    private const CACHE_TTL =
        2592000;

    public function __construct()
    {
        $this->ensureDirectory();
    }

    public function getFloat(
        string $inspectUrl,
        string $steamId
    ): ?array {
        $inspectUrl =
            trim($inspectUrl);

        if (
            $inspectUrl === ''
        ) {
            return null;
        }

        if (
            !str_starts_with(
                $inspectUrl,
                'steam://'
            )
        ) {
            throw new \RuntimeException(
                'Inspect link inválido.'
            );
        }

        /*
         * Comprobamos que el link pertenece
         * al usuario actual cuando usa
         * el formato S + SteamID.
         */
        $decoded =
            urldecode(
                $inspectUrl
            );

        if (
            preg_match(
                '/S(\d+)A/i',
                $decoded,
                $matches
            )
        ) {
            if (
                isset($matches[1])
                &&
                $matches[1] !== $steamId
            ) {
                throw new \RuntimeException(
                    'El inspect link no pertenece '
                    . 'al usuario actual.'
                );
            }
        }

        $cacheFile =
            $this->getCacheFile(
                $inspectUrl
            );

        if (
            file_exists($cacheFile)
        ) {
            $cached =
                $this->readJson(
                    $cacheFile
                );

            if (
                is_array($cached)
                &&
                isset(
                    $cached['float']
                )
                &&
                is_numeric(
                    $cached['float']
                )
            ) {
                $fetchedAt =
                    (int) (
                        $cached['fetched_at']
                        ?? 0
                    );

                if (
                    $fetchedAt > 0
                    &&
                    (
                        time()
                        - $fetchedAt
                    ) < self::CACHE_TTL
                ) {
                    return $cached;
                }
            }
        }

        $data =
            $this->request(
                $inspectUrl
            );

        if (
            !isset(
                $data['iteminfo']
            )
            ||
            !is_array(
                $data['iteminfo']
            )
        ) {
            return null;
        }

        $itemInfo =
            $data['iteminfo'];

        if (
            !isset(
                $itemInfo['floatvalue']
            )
            ||
            !is_numeric(
                $itemInfo['floatvalue']
            )
        ) {
            return null;
        }

        $result = [
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

            'wear_name' =>
                $itemInfo['wear_name']
                ?? null,

            'weapon_type' =>
                $itemInfo['weapon_type']
                ?? null,

            'item_name' =>
                $itemInfo['item_name']
                ?? null,

            'fetched_at' =>
                time()
        ];

        $this->writeJson(
            $cacheFile,
            $result
        );

        return $result;
    }

    private function request(
        string $inspectUrl
    ): array {
        $url =
            self::API_URL
            . '?url='
            . rawurlencode(
                $inspectUrl
            );

        $ch =
            curl_init($url);

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_TIMEOUT =>
                    15,

                CURLOPT_CONNECTTIMEOUT =>
                    8,

                CURLOPT_SSL_VERIFYPEER =>
                    true,

                CURLOPT_HTTPHEADER => [
                    'Accept: application/json'
                ],

                CURLOPT_USERAGENT =>
                    'CS2Inventory/1.0'
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
            throw new \RuntimeException(
                'No se pudo consultar el Float.'
                . (
                    $curlError !== ''
                        ? ' ' . $curlError
                        : ''
                )
            );
        }

        $data =
            json_decode(
                $response,
                true
            );

        if (
            $httpCode < 200
            ||
            $httpCode >= 300
        ) {
            $message =
                'El servicio de Float no '
                . 'ha respondido correctamente.';

            if (
                is_array($data)
                &&
                isset(
                    $data['error']
                )
            ) {
                $message .=
                    ' '
                    . (string)
                        $data['error'];
            }

            throw new \RuntimeException(
                $message
            );
        }

        if (
            !is_array($data)
        ) {
            throw new \RuntimeException(
                'Respuesta inválida del servicio '
                . 'de Float.'
            );
        }

        return $data;
    }

    private function getCacheFile(
        string $inspectUrl
    ): string {
        return
            self::CACHE_DIRECTORY
            . '/'
            . hash(
                'sha256',
                $inspectUrl
            )
            . '.json';
    }

    private function ensureDirectory(): void
    {
        if (
            is_dir(
                self::CACHE_DIRECTORY
            )
        ) {
            return;
        }

        if (
            !mkdir(
                self::CACHE_DIRECTORY,
                0775,
                true
            )
            &&
            !is_dir(
                self::CACHE_DIRECTORY
            )
        ) {
            throw new \RuntimeException(
                'No se pudo crear el directorio '
                . 'de caché de Floats.'
            );
        }
    }

    private function readJson(
        string $file
    ): ?array {
        $content =
            file_get_contents(
                $file
            );

        if (
            $content === false
        ) {
            return null;
        }

        $data =
            json_decode(
                $content,
                true
            );

        return is_array(
            $data
        )
            ? $data
            : null;
    }

    private function writeJson(
        string $file,
        array $data
    ): void {
        $json =
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
                |
                JSON_PRETTY_PRINT
            );

        if (
            $json === false
        ) {
            throw new \RuntimeException(
                'No se pudo guardar el Float.'
            );
        }

        if (
            file_put_contents(
                $file,
                $json,
                LOCK_EX
            ) === false
        ) {
            throw new \RuntimeException(
                'No se pudo escribir la caché '
                . 'de Float.'
            );
        }
    }
}