<?php

declare(strict_types=1);

namespace CS2\Steam;

class SteamInventory
{
    private const APP_ID = 730;

    private const CONTEXT_ID = 2;

    /*
     * No vamos a pedir 5000 objetos de golpe.
     * Steam puede devolver HTTP 400 con cantidades grandes.
     */
    private const ITEMS_PER_REQUEST = 2000;

    /**
     * Obtiene el inventario público de CS2.
     */
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

        /*
         * Seguridad para evitar bucles infinitos
         * en caso de que Steam devuelva una respuesta
         * inesperada.
         */
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

            $response = $this->request($url);

            /*
             * Steam puede devolver 200 pero indicar
             * que la operación no ha tenido éxito.
             */
            if (
                isset($response['success']) &&
                (int) $response['success'] !== 1
            ) {
                throw new \RuntimeException(
                    'Steam no ha podido obtener el inventario.'
                );
            }

            $pageItems =
                $this->processInventory($response);

            $items = array_merge(
                $items,
                $pageItems
            );

            /*
             * Si Steam indica que hay más objetos,
             * utilizamos last_assetid para pedir
             * la siguiente página.
             */
            if (
                !empty($response['more_items']) &&
                !empty($response['last_assetid'])
            ) {
                $startAssetId =
                    (string) $response['last_assetid'];
            } else {
                $startAssetId = null;
            }

        } while ($startAssetId !== null);

        return $items;
    }

    /**
     * Construye la URL del inventario.
     */
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

        if ($startAssetId !== null) {
            $url .= '&start_assetid='
                . urlencode($startAssetId);
        }

        return $url;
    }

    /**
     * Realiza la petición a Steam.
     */
    private function request(
        string $url
    ): array {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
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
                . 'Chrome/140.0 Safari/537.36'
        ]);

        $response = curl_exec($ch);

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException(
                'No se pudo conectar con Steam: '
                . $curlError
            );
        }

        /*
         * Intentamos interpretar la respuesta aunque
         * Steam haya devuelto un código HTTP de error.
         */
        $data = json_decode(
            $response,
            true
        );

        if (
            $httpCode !== 200
        ) {
            $details = '';

            if (is_array($data)) {
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

        if (!is_array($data)) {
            throw new \RuntimeException(
                'Steam ha devuelto una respuesta '
                . 'que no es JSON válido.'
            );
        }

        return $data;
    }

    /**
     * Procesa una página del inventario.
     */
    private function processInventory(
        array $data
    ): array {
        $assets =
            $data['assets'] ?? [];

        $descriptions =
            $data['descriptions'] ?? [];

        $items = [];

        /*
         * En lugar de recorrer todas las descriptions
         * para cada asset, creamos un índice.
         */
        $descriptionMap = [];

        foreach ($descriptions as $description) {
            $classId =
                $description['classid'] ?? null;

            $instanceId =
                $description['instanceid'] ?? '0';

            if (!$classId) {
                continue;
            }

            $key =
                $classId . '_' . $instanceId;

            $descriptionMap[$key] =
                $description;
        }

        foreach ($assets as $asset) {
            $classId =
                $asset['classid'] ?? null;

            $instanceId =
                $asset['instanceid'] ?? '0';

            if (!$classId) {
                continue;
            }

            $key =
                $classId . '_' . $instanceId;

            $description =
                $descriptionMap[$key]
                ?? null;

            if (!$description) {
                continue;
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

                'market_hash_name' =>
                    $description['market_hash_name']
                    ?? null,

                'icon_url' =>
                    $description['icon_url']
                    ?? null,

                'tradable' =>
                    $description['tradable']
                    ?? 0,

                'marketable' =>
                    $description['marketable']
                    ?? 0
            ];
        }

        return $items;
    }
}