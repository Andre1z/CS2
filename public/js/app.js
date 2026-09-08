(() => {
    'use strict';

    const VERSION_KEY =
        'cs2_price_version';

    const PRICES_KEY =
        'cs2_inventory_prices';

    async function getPriceVersion() {
        const response =
            await fetch(
                'api/price-version.php',
                {
                    method: 'GET',
                    cache: 'no-store'
                }
            );

        if (!response.ok) {
            throw new Error(
                'No se pudo comprobar la versión de precios.'
            );
        }

        return response.json();
    }

    function getLocalPrices() {
        try {
            const value =
                localStorage.getItem(
                    PRICES_KEY
                );

            if (!value) {
                return {};
            }

            const prices =
                JSON.parse(value);

            return
                prices &&
                typeof prices === 'object'
                    ? prices
                    : {};

        } catch (error) {
            console.error(
                'Error leyendo precios locales:',
                error
            );

            return {};
        }
    }

    function saveLocalPrices(
        version,
        prices
    ) {
        localStorage.setItem(
            VERSION_KEY,
            version
        );

        localStorage.setItem(
            PRICES_KEY,
            JSON.stringify(prices)
        );
    }

    async function updatePricesIfNeeded() {
        try {
            const server =
                await getPriceVersion();

            if (
                !server.success ||
                !server.version
            ) {
                return;
            }

            const localVersion =
                localStorage.getItem(
                    VERSION_KEY
                );

            /*
             * Si tenemos exactamente la misma
             * versión, no descargamos precios.
             */
            if (
                localVersion ===
                server.version
            ) {
                return;
            }

            /*
             * Recogemos los market_hash_name
             * de los objetos mostrados.
             */
            const elements =
                document.querySelectorAll(
                    '[data-market-hash-name]'
                );

            const names = [];

            elements.forEach(
                (element) => {
                    const name =
                        element.dataset
                            .marketHashName;

                    if (
                        name &&
                        !names.includes(name)
                    ) {
                        names.push(name);
                    }
                }
            );

            if (!names.length) {
                return;
            }

            const response =
                await fetch(
                    'api/prices.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json'
                        },

                        body: JSON.stringify({
                            items: names
                        })
                    }
                );

            if (!response.ok) {
                throw new Error(
                    'No se pudieron descargar los precios.'
                );
            }

            const data =
                await response.json();

            if (
                !data.success
            ) {
                throw new Error(
                    data.error
                    ||
                    'Error actualizando precios.'
                );
            }

            saveLocalPrices(
                data.version,
                data.prices
            );

            applyLocalPrices(
                data.prices
            );

        } catch (error) {
            console.error(
                'Error actualizando precios:',
                error
            );
        }
    }

    function applyLocalPrices(
        prices
    ) {
        const elements =
            document.querySelectorAll(
                '[data-market-hash-name]'
            );

        elements.forEach(
            (element) => {
                const name =
                    element.dataset
                        .marketHashName;

                if (
                    !prices[name]
                ) {
                    return;
                }

                const priceElement =
                    element.querySelector(
                        '.js-price'
                    );

                if (!priceElement) {
                    return;
                }

                const price =
                    prices[name].average;

                priceElement.textContent =
                    Number(price).toLocaleString(
                        'es-ES',
                        {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    ) + ' €';
            }
        );
    }

    document.addEventListener(
        'DOMContentLoaded',
        () => {
            updatePricesIfNeeded();
        }
    );
})();