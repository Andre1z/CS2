<?php

declare(strict_types=1);

namespace CS2\Steam;

class SteamInventory
{
    private const APP_ID = 730;

    private const CONTEXT_ID = 2;

    private const ITEMS_PER_REQUEST = 2000;

    public function getInventory(
        string $steamId
    ): array {
        if (
            !ctype_digit($steamId)
        ) {
            throw new \RuntimeException(
                'SteamID inválido.'
            );
        }

        $items = [];

        $startAssetId = null;

        $requestNumber = 0;

        $maxRequests = 50;

        do {
            $requestNumber++;

            if (
                $requestNumber > $maxRequests
            ) {
                throw new \RuntimeException(
                    'El inventario es demasiado grande '
                    . 'o Steam no está devolviendo una '
                    . 'paginación válida.'
                );
            }

            $url =
                $this->buildInventoryUrl(
                    $steamId,
                    $startAssetId
                );

            $response =
                $this->request($url);

            if (
                isset($response['success'])
                &&
                (int) $response['success'] !== 1
            ) {
                throw new \RuntimeException(
                    'Steam no ha podido obtener el inventario.'
                );
            }

            $pageItems =
                $this->processInventory(
                    $response,
                    $steamId
                );

            $items =
                array_merge(
                    $items,
                    $pageItems
                );

            if (
                !empty(
                    $response['more_items']
                )
                &&
                !empty(
                    $response['last_assetid']
                )
            ) {
                $startAssetId =
                    (string)
                    $response['last_assetid'];
            } else {
                $startAssetId = null;
            }

        } while (
            $startAssetId !== null
        );

        return $items;
    }

    private function buildInventoryUrl(
        string $steamId,
        ?string $startAssetId = null
    ): string {
        $url =
            sprintf(
                'https://steamcommunity.com/inventory/%s/%d/%d?l=english&count=%d',
                $steamId,
                self::APP_ID,
                self::CONTEXT_ID,
                self::ITEMS_PER_REQUEST
            );

        if (
            $startAssetId !== null
        ) {
            $url .=
                '&start_assetid='
                . urlencode(
                    $startAssetId
                );
        }

        return $url;
    }

    private function request(
        string $url
    ): array {
        $ch =
            curl_init($url);

        if (
            $ch === false
        ) {
            throw new \RuntimeException(
                'No se pudo iniciar la conexión con Steam.'
            );
        }

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_TIMEOUT => 20,

                CURLOPT_CONNECTTIMEOUT => 10,

                CURLOPT_SSL_VERIFYPEER => true,

                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                ],

                CURLOPT_USERAGENT =>
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                    . 'AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) '
                    . 'Chrome/140.0 Safari/537.36',
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
            throw new \RuntimeException(
                'No se pudo conectar con Steam: '
                . $curlError
            );
        }

        $data =
            json_decode(
                $response,
                true
            );

        if (
            $httpCode !== 200
        ) {
            $details = '';

            if (
                is_array($data)
            ) {
                $details =
                    json_encode(
                        $data,
                        JSON_UNESCAPED_UNICODE
                    );
            } elseif (
                trim($response) !== ''
            ) {
                $details =
                    trim($response);
            }

            throw new \RuntimeException(
                'Steam no ha devuelto el inventario. '
                . 'HTTP: '
                . $httpCode
                . (
                    $details !== ''
                        ? ' | Respuesta: ' . $details
                        : ''
                )
            );
        }

        if (
            !is_array($data)
        ) {
            throw new \RuntimeException(
                'Steam ha devuelto una respuesta '
                . 'que no es JSON válido.'
            );
        }

        return $data;
    }

    private function processInventory(
        array $data,
        string $steamId
    ): array {
        $assets =
            $data['assets']
            ?? [];

        $descriptions =
            $data['descriptions']
            ?? [];

        $items = [];

        /*
         * -------------------------------------------------
         * MAPA DE DESCRIPCIONES
         * -------------------------------------------------
         */

        $descriptionMap = [];

        foreach (
            $descriptions as $description
        ) {
            $classId =
                $description['classid']
                ?? null;

            $instanceId =
                $description['instanceid']
                ?? '0';

            if (
                !$classId
            ) {
                continue;
            }

            $key =
                $classId
                . '_'
                . $instanceId;

            $descriptionMap[$key] =
                $description;
        }

        /*
         * -------------------------------------------------
         * MAPA DE PROPIEDADES
         * -------------------------------------------------
         *
         * Steam devuelve el float (propertyid 2),
         * el pattern (1) y el certificado del inspect
         * link (6) en asset_properties.
         */

        $propertyMap = [];

        foreach (
            $data['asset_properties'] ?? []
            as $entry
        ) {
            $entryAssetId =
                (string)
                (
                    $entry['assetid']
                    ?? ''
                );

            if (
                $entryAssetId === ''
            ) {
                continue;
            }

            foreach (
                $entry['asset_properties'] ?? []
                as $property
            ) {
                $propertyMap[$entryAssetId][
                    (int)
                    (
                        $property['propertyid']
                        ?? 0
                    )
                ] =
                    $property['float_value']
                    ?? $property['int_value']
                    ?? $property['string_value']
                    ?? null;
            }
        }

        /*
         * -------------------------------------------------
         * ASSETS
         * -------------------------------------------------
         */

        foreach (
            $assets as $asset
        ) {
            $classId =
                $asset['classid']
                ?? null;

            $instanceId =
                $asset['instanceid']
                ?? '0';

            if (
                !$classId
            ) {
                continue;
            }

            $key =
                $classId
                . '_'
                . $instanceId;

            $description =
                $descriptionMap[$key]
                ?? null;

            if (
                !$description
            ) {
                continue;
            }

            /*
             * -------------------------------------------------
             * TAGS
             * -------------------------------------------------
             */

            $tags =
                $description['tags']
                ?? [];

            $type = null;

            $weapon = null;

            $rarity = null;

            $rarityColor = null;

            $wear = null;

            $exterior = null;

            foreach (
                $tags as $tag
            ) {
                $category =
                    strtolower(
                        (string)
                        (
                            $tag['category']
                            ?? ''
                        )
                    );

                $localizedName =
                    $tag['localized_tag_name']
                    ?? $tag['name']
                    ?? null;

                $color =
                    $tag['color']
                    ?? null;

                switch ($category) {

                    case 'type':

                        $type =
                            $localizedName;

                        break;

                    case 'weapon':

                        $weapon =
                            $localizedName;

                        break;

                    case 'rarity':

                        $rarity =
                            $localizedName;

                        if (
                            is_string($color)
                            &&
                            preg_match(
                                '/^[a-f0-9]{6}$/i',
                                $color
                            )
                        ) {
                            $rarityColor =
                                '#'
                                . $color;
                        }

                        break;

                    case 'exterior':

                        $exterior =
                            $localizedName;

                        break;
                }
            }

            /*
             * -------------------------------------------------
             * INSPECT URL
             * -------------------------------------------------
             */

            $properties =
                $propertyMap[
                    (string)
                    (
                        $asset['assetid']
                        ?? ''
                    )
                ]
                ?? [];

            $inspectUrl =
                $this->extractInspectUrl(
                    $description,
                    $steamId,
                    (string)
                    (
                        $asset['assetid']
                        ?? ''
                    ),
                    $properties
                );

            /*
             * Un objeto tiene float si Steam lo devuelve
             * o, en su defecto, si tiene inspect real y
             * desgaste (exterior). Agentes, pins, charms,
             * kits de música, etc. tienen inspect pero
             * no float.
             */
            $hasFloat =
                isset($properties[2])
                ||
                (
                    $this->hasValidInspectUrl(
                        $inspectUrl
                    )
                    &&
                    !empty($exterior)
                    &&
                    $exterior !== 'Not Painted'
                );

            /*
             * -------------------------------------------------
             * DATOS VISUALES
             * -------------------------------------------------
             */

            $nameColor =
                $description['name_color']
                ?? null;

            if (
                is_string($nameColor)
                &&
                !preg_match(
                    '/^#[a-f0-9]{6}$/i',
                    $nameColor
                )
            ) {
                $nameColor = null;
            }

            if (
                is_string($nameColor)
                &&
                !str_starts_with(
                    $nameColor,
                    '#'
                )
            ) {
                $nameColor =
                    '#'
                    . $nameColor;
            }

            /*
             * -------------------------------------------------
             * MARKET HASH NAME
             * -------------------------------------------------
             */

            $marketHashName =
                $description['market_hash_name']
                ?? null;

            /*
             * -------------------------------------------------
             * STACKABLE
             * -------------------------------------------------
             *
             * Agrupamos objetos que no necesitan float.
             *
             * Las armas/skins con inspect se mantienen
             * individuales.
             */
            $stackable =
                !$hasFloat
                &&
                !empty(
                    $marketHashName
                );

            /*
             * Commodity es una buena pista adicional
             * para objetos comerciables por cantidad.
             */
            $commodity =
                (int)
                (
                    $description['commodity']
                    ?? 0
                );

            /*
             * -------------------------------------------------
             * ITEM FINAL
             * -------------------------------------------------
             */

            $items[] = [

                'assetid' =>
                    $asset['assetid']
                    ?? null,

                'classid' =>
                    $classId,

                'instanceid' =>
                    $instanceId,

                'amount' =>
                    max(
                        1,
                        (int)
                        (
                            $asset['amount']
                            ?? 1
                        )
                    ),

                'name' =>
                    $description['name']
                    ?? 'Objeto desconocido',

                'market_hash_name' =>
                    $marketHashName,

                'icon_url' =>
                    $description['icon_url']
                    ?? null,

                'tradable' =>
                    (int)
                    (
                        $description['tradable']
                        ?? 0
                    ),

                'marketable' =>
                    (int)
                    (
                        $description['marketable']
                        ?? 0
                    ),

                'commodity' =>
                    $commodity,

                'type' =>
                    $type,

                'weapon' =>
                    $weapon,

                'rarity' =>
                    $rarity,

                'rarity_color' =>
                    $rarityColor,

                'name_color' =>
                    $nameColor,

                'wear' =>
                    $exterior,

                'exterior' =>
                    $exterior,

                'inspect_url' =>
                    $hasFloat
                        ? $inspectUrl
                        : null,

                'has_float' =>
                    $hasFloat,

                'float' =>
                    isset($properties[2])
                        ? (float) $properties[2]
                        : null,

                'paint_seed' =>
                    isset($properties[1])
                        ? (int) $properties[1]
                        : null,

                'stackable' =>
                    $stackable,
            ];
        }

        return $items;
    }

    /**
     * Busca el inspect link en las actions de Steam.
     */
    private function extractInspectUrl(
        array $description,
        string $steamId,
        string $assetId,
        array $properties = []
    ): ?string {
        $actions =
            $description['actions']
            ?? [];

        foreach (
            $actions as $action
        ) {
            $link =
                $action['link']
                ?? '';

            if (
                stripos(
                    $link,
                    'csgo_econ_action_preview'
                ) === false
            ) {
                continue;
            }

            /*
             * Sustituir placeholders clásicos.
             */
            $link =
                str_replace(
                    [
                        '%owner_steamid%',
                        '%assetid%',
                    ],
                    [
                        $steamId,
                        $assetId,
                    ],
                    $link
                );

            /*
             * Formato nuevo de CS2: el inspect link
             * es el certificado (propertyid 6).
             */
            foreach (
                $properties as $propertyId => $value
            ) {
                $link =
                    str_replace(
                        '%propid:' . $propertyId . '%',
                        (string) $value,
                        $link
                    );
            }

            /*
             * Si Steam deja placeholders,
             * lo consideramos inválido.
             */
            if (
                str_contains(
                    $link,
                    '%propid:'
                )
                ||
                str_contains(
                    $link,
                    '%assetid%'
                )
                ||
                str_contains(
                    $link,
                    '%owner_steamid%'
                )
            ) {
                return null;
            }

            return $link;
        }

        return null;
    }

    private function hasValidInspectUrl(
        ?string $inspectUrl
    ): bool {
        if (
            empty($inspectUrl)
        ) {
            return false;
        }

        if (
            !str_starts_with(
                $inspectUrl,
                'steam://rungame/730/'
            )
            &&
            !str_starts_with(
                $inspectUrl,
                'steam://run/730/'
            )
        ) {
            return false;
        }

        if (
            stripos(
                $inspectUrl,
                'csgo_econ_action_preview'
            ) === false
        ) {
            return false;
        }

        $invalidPatterns = [
            '%propid:',
            '%assetid%',
            '%owner_steamid%',
            '%classid%',
            '%instanceid%',
        ];

        foreach (
            $invalidPatterns as $pattern
        ) {
            if (
                stripos(
                    $inspectUrl,
                    $pattern
                ) !== false
            ) {
                return false;
            }
        }

        return true;
    }
}