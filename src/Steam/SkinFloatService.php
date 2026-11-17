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

    /**
     * Obtiene el float de un objeto.
     *
     * Devuelve null cuando:
     * - el inspect link no es válido
     * - Steam devolvió una plantilla
     * - CSGOFloat no responde
     * - el objeto no tiene float
     */
    public function getFloat(
        string $inspectUrl,
        string $steamId
    ): ?array {
        $inspectUrl =
            trim($inspectUrl);

        /*
         * No intentamos procesar enlaces vacíos
         * o plantillas incompletas de Steam.
         */
        if (
            !$this->isValidInspectUrl(
                $inspectUrl
            )
        ) {
            return null;
        }

        /*
         * Los inspect links clásicos contienen:
         *
         * S + SteamID + A + assetid + D + ...
         *
         * Si existe SteamID en el enlace,
         * comprobamos que pertenece al usuario.
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

        /*
         * Caché basada en el inspect link completo.
         */
        $cacheKey =
            hash(
                'sha256',
                $inspectUrl
            );

        $cacheFile =
            $this->cacheDirectory
            . DIRECTORY_SEPARATOR
            . $cacheKey
            . '.json';

        /*
         * Leer caché.
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
                array_key_exists(
                    'float',
                    $cached
                )
            ) {
                return $cached;
            }
        }

        /*
         * 1) Decodificar el certificado del inspect
         *    link (formato nuevo de CS2), sin red.
         * 2) Si no se puede, consultar CSGOFloat.
         */
        $result =
            $this->decodeInspectCertificate(
                $inspectUrl
            )
            ?? $this->requestFloat(
                $inspectUrl
            );

        if (
            $result === null
        ) {
            return null;
        }

        /*
         * Guardar resultado.
         */
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

    /**
     * Comprueba que realmente tenemos un inspect link
     * útil y no una plantilla de Steam.
     */
    private function isValidInspectUrl(
        string $url
    ): bool {
        if ($url === '') {
            return false;
        }

        /*
         * Steam puede devolver estas dos variantes.
         */
        $validScheme =
            str_starts_with(
                $url,
                'steam://rungame/730/'
            )
            ||
            str_starts_with(
                $url,
                'steam://run/730/'
            );

        if (!$validScheme) {
            return false;
        }

        /*
         * Tiene que ser una acción de inspección.
         */
        if (
            stripos(
                $url,
                'csgo_econ_action_preview'
            ) === false
        ) {
            return false;
        }

        /*
         * Steam puede dejar placeholders sin sustituir.
         *
         * Ejemplo:
         *
         * +csgo_econ_action_preview %propid:6%
         */
        $invalidPatterns = [
            '%propid:',
            '%assetid%',
            '%owner_steamid%',
            '%listingid%',
            '%classid%',
            '%instanceid%',
            '%market_id%',
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
                return false;
            }
        }

        /*
         * En los enlaces clásicos esperamos S/A/D.
         *
         * En los inspect links modernos los datos pueden
         * ir codificados de otra manera, por lo que no
         * exigimos obligatoriamente este patrón.
         */
        return true;
    }

    /**
     * Decodifica el certificado hexadecimal de los
     * inspect links nuevos de CS2.
     *
     * Estructura:
     *
     * [clave XOR] + protobuf CEconItemPreviewDataBlock + CRC (4 bytes)
     *
     * Todos los bytes van con XOR de la clave
     * (el primer byte descifrado siempre es 0).
     *
     * Campos usados del protobuf:
     * - 7: paintwear (bits de un float32)
     * - 8: paintseed
     */
    private function decodeInspectCertificate(
        string $inspectUrl
    ): ?array {
        if (
            !preg_match(
                '/csgo_econ_action_preview(?:%20|\s)+([0-9A-F]+)$/i',
                rawurldecode($inspectUrl),
                $matches
            )
        ) {
            return null;
        }

        $hex =
            $matches[1];

        /*
         * Los enlaces clásicos S..A..D.. no son hex puro.
         */
        if (
            strlen($hex) % 2 !== 0
            ||
            strlen($hex) < 12
        ) {
            return null;
        }

        $bytes =
            (string) hex2bin($hex);

        $key =
            ord($bytes[0]);

        $decoded = '';

        for (
            $i = 0;
            $i < strlen($bytes);
            $i++
        ) {
            $decoded .=
                chr(
                    ord($bytes[$i]) ^ $key
                );
        }

        $proto =
            substr($decoded, 1, -4);

        $fields =
            $this->parseProtobuf($proto);

        if (
            $fields === null
            ||
            !isset($fields[7])
        ) {
            return null;
        }

        $float =
            unpack(
                'g',
                pack('V', $fields[7])
            )[1];

        return [
            'float' =>
                (float) $float,

            'paint_seed' =>
                isset($fields[8])
                    ? (int) $fields[8]
                    : null,

            'wear' =>
                null,
        ];
    }

    /**
     * Parser mínimo de protobuf: devuelve los campos
     * escalares (varint / fixed32) indexados por número.
     */
    private function parseProtobuf(
        string $data
    ): ?array {
        $fields = [];

        $offset = 0;

        $length =
            strlen($data);

        $readVarint =
            function () use (
                $data,
                $length,
                &$offset
            ): ?int {
                $result = 0;

                $shift = 0;

                do {
                    if (
                        $offset >= $length
                        ||
                        $shift > 63
                    ) {
                        return null;
                    }

                    $byte =
                        ord($data[$offset++]);

                    $result |=
                        ($byte & 0x7F) << $shift;

                    $shift += 7;

                } while ($byte & 0x80);

                return $result;
            };

        while (
            $offset < $length
        ) {
            $tag =
                $readVarint();

            if (
                $tag === null
            ) {
                return null;
            }

            $field =
                $tag >> 3;

            switch ($tag & 7) {
                case 0:
                    $value =
                        $readVarint();

                    if (
                        $value === null
                    ) {
                        return null;
                    }

                    $fields[$field] =
                        $value;

                    break;

                case 2:
                    $size =
                        $readVarint();

                    if (
                        $size === null
                        ||
                        $offset + $size > $length
                    ) {
                        return null;
                    }

                    $offset += $size;

                    break;

                case 5:
                    if (
                        $offset + 4 > $length
                    ) {
                        return null;
                    }

                    $fields[$field] =
                        unpack(
                            'V',
                            substr($data, $offset, 4)
                        )[1];

                    $offset += 4;

                    break;

                default:
                    return null;
            }
        }

        return $fields;
    }

    /**
     * Consulta CSGOFloat.
     */
    private function requestFloat(
        string $inspectUrl
    ): ?array {
        $url =
            self::API_URL
            . '?url='
            . rawurlencode(
                $inspectUrl
            );

        $ch =
            curl_init($url);

        if (
            $ch === false
        ) {
            return null;
        }

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
            ]
        );

        $response =
            curl_exec($ch);

        $httpCode =
            (int) curl_getinfo(
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
            error_log(
                'Float API returned invalid JSON.'
            );

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