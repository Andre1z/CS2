<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceCache
{
    private const CACHE_DIRECTORY =
        __DIR__ . '/../../storage/prices';

    private const PROVIDERS_DIRECTORY =
        self::CACHE_DIRECTORY . '/providers';

    private const CALCULATED_FILE =
        self::CACHE_DIRECTORY . '/calculated.json';

    private const METADATA_FILE =
        self::CACHE_DIRECTORY . '/metadata.json';

    public function __construct()
    {
        $this->ensureDirectories();
    }

    public function getCalculatedPrices(): array
    {
        if (!file_exists(self::CALCULATED_FILE)) {
            return [];
        }

        $content =
            file_get_contents(
                self::CALCULATED_FILE
            );

        if ($content === false) {
            return [];
        }

        $data =
            json_decode(
                $content,
                true
            );

        return is_array($data)
            ? $data
            : [];
    }

    public function saveCalculatedPrices(
        array $prices
    ): void {
        $this->writeJson(
            self::CALCULATED_FILE,
            $prices
        );
    }

    public function getMetadata(): array
    {
        if (!file_exists(self::METADATA_FILE)) {
            return [
                'version' => null,
                'updated_at' => null
            ];
        }

        $content =
            file_get_contents(
                self::METADATA_FILE
            );

        if ($content === false) {
            return [
                'version' => null,
                'updated_at' => null
            ];
        }

        $data =
            json_decode(
                $content,
                true
            );

        if (!is_array($data)) {
            return [
                'version' => null,
                'updated_at' => null
            ];
        }

        return $data;
    }

    public function saveMetadata(
        array $metadata
    ): void {
        $this->writeJson(
            self::METADATA_FILE,
            $metadata
        );
    }

    public function saveProviderPrices(
        string $provider,
        array $prices
    ): void {
        $file =
            self::PROVIDERS_DIRECTORY
            . '/'
            . $provider
            . '.json';

        $this->writeJson(
            $file,
            $prices
        );
    }

    public function getProviderPrices(
        string $provider
    ): array {
        $file =
            self::PROVIDERS_DIRECTORY
            . '/'
            . $provider
            . '.json';

        if (!file_exists($file)) {
            return [];
        }

        $content =
            file_get_contents($file);

        if ($content === false) {
            return [];
        }

        $data =
            json_decode(
                $content,
                true
            );

        return is_array($data)
            ? $data
            : [];
    }

    public function getProviderFile(
        string $provider
    ): string {
        return
            self::PROVIDERS_DIRECTORY
            . '/'
            . $provider
            . '.json';
    }

    private function ensureDirectories(): void
    {
        if (
            !is_dir(self::CACHE_DIRECTORY)
            &&
            !mkdir(
                self::CACHE_DIRECTORY,
                0775,
                true
            )
            &&
            !is_dir(self::CACHE_DIRECTORY)
        ) {
            throw new \RuntimeException(
                'No se pudo crear el directorio de caché.'
            );
        }

        if (
            !is_dir(self::PROVIDERS_DIRECTORY)
            &&
            !mkdir(
                self::PROVIDERS_DIRECTORY,
                0775,
                true
            )
            &&
            !is_dir(self::PROVIDERS_DIRECTORY)
        ) {
            throw new \RuntimeException(
                'No se pudo crear el directorio de proveedores.'
            );
        }
    }

    private function writeJson(
        string $file,
        array $data
    ): void {
        $json =
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
            );

        if ($json === false) {
            throw new \RuntimeException(
                'No se pudo convertir la caché a JSON.'
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
                'No se pudo escribir la caché: '
                . $file
            );
        }
    }
}