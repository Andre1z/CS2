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
        if (!ctype_digit($steamId)) {
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

            if ($requestNumber > $maxRequests) {
                throw new \RuntimeException(
                    'El inventario es demasiado grande '
                    . 'o Steam no está devolviendo una paginación válida.'
                );
            }

            $url = $this->buildInventoryUrl(
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
        $url = sprintf(
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

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_TIMEOUT =>
                    20,

                CURLOPT_CONNECTTIMEOUT =>
                    10,

                CURLOPT_SSL_VERIFYPEER =>
                    true,

                CURLOPT_HTTPHEADER => [
                    'Accept: application/json'
                ],

                CURLOPT_USERAGENT =>
                    'Mozilla/5.0 '
                    . '(Windows NT 10.0; Win64; x64) '
                    . 'AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) '
                    . 'Chrome/140.0 Safari/537.36'
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
                        ? ' | Respuesta: '
                            . $details
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

        $descriptionMap = [];

        foreach (
            $descriptions
            as $description
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

        foreach (
            $assets
            as $asset
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

            $tags =
                is_array(
                    $description['tags']
                    ?? null
                )
                    ? $description['tags']
                    : [];

            $wear =
                $this->getTagValue(
                    $tags,
                    'Exterior'
                );

            $rarity =
                $this->getTagValue(
                    $tags,
                    'Rarity'
                );

            $weapon =
                $this->getTagValue(
                    $tags,
                    'Weapon'
                );

            $inspectUrl =
                $this->getInspectUrl(
                    $description,
                    $steamId,
                    (string) (
                        $asset['assetid']
                        ?? ''
                    )
                );

            $hasFloat =
                $wear !== null
                &&
                $inspectUrl !== null;

            $marketHashName =
                $description[
                    'market_hash_name'
                ]
                ?? null;

            $itemType =
                $description['type']
                ?? null;

            /*
             * Los objetos con float son
             * individuales.
             *
             * Los objetos sin float pueden
             * agruparse si comparten el
             * mismo market_hash_name.
             */
            $stackable =
                !$hasFloat
                &&
                !empty(
                    $marketHashName
                );

            $nameColor =
                $description['name_color']
                ?? null;

            if (
                !is_string($nameColor)
                ||
                !preg_match(
                    '/^[0-9a-fA-F]{6}$/',
                    $nameColor
                )
            ) {
                $nameColor = null;
            }

            $items[] = [
                'assetid' =>
                    $asset['assetid']
                    ?? null,

                'classid' =>
                    $classId,

                'instanceid' =>
                    $instanceId,

                'name' =>
                    $description['name']
                    ?? 'Objeto desconocido',

                'market_name' =>
                    $description['market_name']
                    ?? (
                        $description['name']
                        ?? 'Objeto desconocido'
                    ),

                'market_hash_name' =>
                    $marketHashName,

                'icon_url' =>
                    $description['icon_url']
                    ?? null,

                'tradable' =>
                    (int) (
                        $description['tradable']
                        ?? 0
                    ),

                'marketable' =>
                    (int) (
                        $description['marketable']
                        ?? 0
                    ),

                'commodity' =>
                    (int) (
                        $description['commodity']
                        ?? 0
                    ),

                'type' =>
                    $itemType,

                'weapon' =>
                    $weapon,

                'wear' =>
                    $wear,

                'rarity' =>
                    $rarity,

                'name_color' =>
                    $nameColor,

                'inspect_url' =>
                    $inspectUrl,

                'has_float' =>
                    $hasFloat,

                'stackable' =>
                    $stackable,

                'price' =>
                    null,

                'price_min' =>
                    null,

                'price_max' =>
                    null,

                'price_count' =>
                    0,

                'price_total_sources' =>
                    0,

                'price_markets' =>
                    [],

                'price_providers' =>
                    []
            ];
        }

        return $items;
    }

    private function getTagValue(
        array $tags,
        string $category
    ): ?string {
        foreach (
            $tags
            as $tag
        ) {
            if (
                ($tag['category'] ?? null)
                === $category
            ) {
                $value =
                    $tag['localized_tag_name']
                    ?? null;

                if (
                    is_string($value)
                    &&
                    trim($value) !== ''
                ) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function getInspectUrl(
        array $description,
        string $steamId,
        string $assetId
    ): ?string {
        $actions =
            $description['actions']
            ?? [];

        if (
            !is_array($actions)
        ) {
            return null;
        }

        foreach (
            $actions
            as $action
        ) {
            $link =
                $action['link']
                ?? null;

            if (
                !is_string($link)
                ||
                $link === ''
            ) {
                continue;
            }

            if (
                !str_contains(
                    $link,
                    'csgo_econ_action_preview'
                )
            ) {
                continue;
            }

            /*
             * Steam puede devolver
             * placeholders.
             */
            $link =
                str_replace(
                    [
                        '%owner_steamid%',
                        '%assetid%'
                    ],
                    [
                        $steamId,
                        $assetId
                    ],
                    $link
                );

            /*
             * Validamos que sigue siendo
             * un inspect link de Steam.
             */
            if (
                str_starts_with(
                    $link,
                    'steam://'
                )
            ) {
                return $link;
            }
        }

        return null;
    }
}