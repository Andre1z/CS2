<?php

declare(strict_types=1);

namespace CS2\Steam;

class SteamInventory
{
    private const APP_ID = 730;
    private const CONTEXT_ID = 2;

    /**
     * Obtiene el inventario público de CS2.
     */
    public function getInventory(
        string $steamId
    ): array {

        $url = sprintf(
            'https://steamcommunity.com/inventory/%s/%d/%d?l=english&count=5000',
            $steamId,
            self::APP_ID,
            self::CONTEXT_ID
        );

        $ch = curl_init($url);

        curl_setopt_array($ch, [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 15,

            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_USERAGENT =>
                'CS2 Inventory Website/1.0'

        ]);

        $response = curl_exec($ch);

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        if ($response === false) {

            throw new \RuntimeException(
                'No se pudo conectar con Steam.'
            );
        }

        if ($httpCode !== 200) {

            throw new \RuntimeException(
                'Steam no ha devuelto el inventario.'
            );
        }

        $data = json_decode(
            $response,
            true
        );

        if (!is_array($data)) {

            throw new \RuntimeException(
                'Respuesta de Steam inválida.'
            );
        }

        return $this->processInventory($data);
    }

    /**
     * Convierte la respuesta de Steam
     * en una estructura más sencilla.
     */
    private function processInventory(
        array $data
    ): array {

        $assets = $data['assets'] ?? [];
        $descriptions = $data['descriptions'] ?? [];

        $items = [];

        foreach ($assets as $asset) {

            $classId = $asset['classid'] ?? null;
            $instanceId = $asset['instanceid'] ?? '0';

            if (!$classId) {
                continue;
            }

            $description = $this->findDescription(
                $descriptions,
                $classId,
                $instanceId
            );

            if (!$description) {
                continue;
            }

            $items[] = [

                'assetid' =>
                    $asset['assetid'] ?? null,

                'classid' =>
                    $classId,

                'instanceid' =>
                    $instanceId,

                'name' =>
                    $description['name'] ?? 'Objeto desconocido',

                'market_hash_name' =>
                    $description['market_hash_name']
                    ?? null,

                'icon_url' =>
                    $description['icon_url'] ?? null,

                'tradable' =>
                    $description['tradable'] ?? 0,

                'marketable' =>
                    $description['marketable'] ?? 0
            ];
        }

        return $items;
    }

    private function findDescription(
        array $descriptions,
        string $classId,
        string $instanceId
    ): ?array {

        foreach ($descriptions as $description) {

            if (
                ($description['classid'] ?? '') === $classId &&
                ($description['instanceid'] ?? '0') === $instanceId
            ) {
                return $description;
            }
        }

        return null;
    }
}