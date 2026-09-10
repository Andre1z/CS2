'use strict';


document.addEventListener(
    'DOMContentLoaded',
    () => {

        const inventoryGrid =
            document.getElementById(
                'inventoryGrid'
            );

        const searchInput =
            document.getElementById(
                'searchInventory'
            );

        const sortSelect =
            document.getElementById(
                'sortInventory'
            );

        const selectAllButton =
            document.getElementById(
                'selectAll'
            );

        const clearSelectionButton =
            document.getElementById(
                'clearSelection'
            );

        const selectedItemsContainer =
            document.getElementById(
                'selectedItems'
            );

        const selectedEmpty =
            document.getElementById(
                'selectedEmpty'
            );

        const selectedTotal =
            document.getElementById(
                'selectedTotal'
            );

        const sellButton =
            document.getElementById(
                'sellButton'
            );

        const refreshButton =
            document.getElementById(
                'refreshInventory'
            );

        const inventoryCount =
            document.getElementById(
                'inventoryCount'
            );

        const noSearchResults =
            document.getElementById(
                'noSearchResults'
            );


        if (
            !inventoryGrid
        ) {
            return;
        }


        /*
         * =====================================================
         * HELPERS
         * =====================================================
         */

        function parsePrice(
            value
        ) {
            const number =
                Number.parseFloat(
                    value
                );

            return Number.isFinite(
                number
            )
                ? number
                : 0;
        }


        function formatPrice(
            value
        ) {
            return new Intl.NumberFormat(
                'es-ES',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            ).format(
                value
            ) + ' €';
        }


        function escapeHtml(
            value
        ) {
            const div =
                document.createElement(
                    'div'
                );

            div.textContent =
                value ?? '';

            return div.innerHTML;
        }


        /*
         * =====================================================
         * GROUPS
         * =====================================================
         */

        function getGroups() {
            return Array.from(
                inventoryGrid.querySelectorAll(
                    '.inventory-group'
                )
            );
        }


        /*
         * =====================================================
         * STACKS
         * =====================================================
         */

        function initStackButtons() {

            document
                .querySelectorAll(
                    '.stack-toggle'
                )
                .forEach(
                    button => {

                        button.addEventListener(
                            'click',
                            event => {

                                event.preventDefault();

                                event.stopPropagation();

                                const group =
                                    button.closest(
                                        '.inventory-group'
                                    );

                                if (
                                    !group
                                ) {
                                    return;
                                }

                                group.classList.toggle(
                                    'is-expanded'
                                );

                                const expanded =
                                    group.classList.contains(
                                        'is-expanded'
                                    );

                                button.title =
                                    expanded
                                        ? 'Agrupar objetos'
                                        : 'Desagrupar objetos';

                                button.setAttribute(
                                    'aria-label',
                                    expanded
                                        ? 'Agrupar objetos'
                                        : 'Desagrupar objetos'
                                );

                            }
                        );

                    }
                );

        }


        /*
         * =====================================================
         * SELECTION
         * =====================================================
         */

        function getSelectedCheckboxes() {

            return Array.from(
                document.querySelectorAll(
                    '.item-checkbox:checked'
                )
            ).filter(
                checkbox => {
                    return !checkbox.disabled;
                }
            );

        }


        function getGroupChildren(
            group
        ) {

            return Array.from(
                group.querySelectorAll(
                    '.stack-child-checkbox input'
                )
            );

        }


        function syncMasterCheckbox(
            group
        ) {

            const master =
                group.querySelector(
                    '.stack-master-checkbox'
                );

            if (
                !master
            ) {
                return;
            }

            const children =
                getGroupChildren(
                    group
                );

            if (
                children.length === 0
            ) {
                return;
            }

            const checkedCount =
                children.filter(
                    checkbox =>
                        checkbox.checked
                ).length;

            master.checked =
                checkedCount === children.length;

            master.indeterminate =
                checkedCount > 0
                &&
                checkedCount < children.length;
        }


        function selectGroup(
            group,
            checked
        ) {

            const children =
                getGroupChildren(
                    group
                );

            children.forEach(
                checkbox => {
                    checkbox.checked =
                        checked;
                }
            );

            syncMasterCheckbox(
                group
            );

            updateSelectionUI();
        }


        function initCheckboxes() {

            document
                .querySelectorAll(
                    '.item-checkbox'
                )
                .forEach(
                    checkbox => {

                        checkbox.addEventListener(
                            'change',
                            () => {

                                const group =
                                    checkbox.closest(
                                        '.inventory-group'
                                    );

                                if (
                                    checkbox.classList.contains(
                                        'stack-master-checkbox'
                                    )
                                ) {

                                    if (
                                        group
                                    ) {
                                        selectGroup(
                                            group,
                                            checkbox.checked
                                        );
                                    }

                                    return;
                                }


                                if (
                                    group
                                ) {
                                    syncMasterCheckbox(
                                        group
                                    );
                                }

                                updateSelectionUI();

                            }
                        );

                    }
                );

        }


        /*
         * =====================================================
         * SELECTION DATA
         * =====================================================
         */

        function getItemData(
            checkbox
        ) {

            const group =
                checkbox.closest(
                    '.inventory-group'
                );

            if (
                !group
            ) {
                return null;
            }


            /*
             * Item de stack.
             */
            if (
                checkbox.dataset.assetId
            ) {

                return {
                    assetId:
                        checkbox.dataset.assetId,

                    name:
                        checkbox.dataset.name
                        || 'Objeto',

                    price:
                        parsePrice(
                            checkbox.dataset.price
                        ),

                    image:
                        checkbox.dataset.image
                        || ''
                };

            }


            /*
             * Item individual.
             */
            const nameInput =
                group.querySelector(
                    '.single-item-name'
                );

            const priceInput =
                group.querySelector(
                    '.single-item-price'
                );

            const assetInput =
                group.querySelector(
                    '.single-item-asset'
                );

            const imageInput =
                group.querySelector(
                    '.single-item-image'
                );

            return {
                assetId:
                    assetInput?.value
                    || group.dataset.groupKey,

                name:
                    nameInput?.value
                    || 'Objeto',

                price:
                    parsePrice(
                        priceInput?.value
                    ),

                image:
                    imageInput?.value
                    || ''
            };

        }


        /*
         * =====================================================
         * SIDEBAR
         * =====================================================
         */

        function updateSelectionUI() {

            const selected =
                getSelectedCheckboxes();

            selectedItemsContainer
                .querySelectorAll(
                    '.selected-item'
                )
                .forEach(
                    item => item.remove()
                );


            let total = 0;


            selected.forEach(
                checkbox => {

                    const data =
                        getItemData(
                            checkbox
                        );

                    if (
                        !data
                    ) {
                        return;
                    }

                    total +=
                        data.price;


                    const element =
                        document.createElement(
                            'div'
                        );

                    element.className =
                        'selected-item';


                    const imageHtml =
                        data.image
                            ? `
                                <img
                                    src="https://community.cloudflare.steamstatic.com/economy/image/${encodeURIComponent(data.image)}/64fx64f"
                                    alt=""
                                >
                            `
                            : `
                                <div class="selected-item-no-image">
                                    ?
                                </div>
                            `;


                    element.innerHTML = `
                        <div class="selected-item-image">
                            ${imageHtml}
                        </div>

                        <div class="selected-item-info">

                            <strong>
                                ${escapeHtml(data.name)}
                            </strong>

                            <span>
                                ${formatPrice(data.price)}
                            </span>

                        </div>

                        <button
                            type="button"
                            class="remove-selected-item"
                            title="Quitar"
                        >
                            ×
                        </button>
                    `;


                    const removeButton =
                        element.querySelector(
                            '.remove-selected-item'
                        );

                    removeButton.addEventListener(
                        'click',
                        () => {

                            checkbox.checked =
                                false;

                            const group =
                                checkbox.closest(
                                    '.inventory-group'
                                );

                            if (
                                group
                            ) {
                                syncMasterCheckbox(
                                    group
                                );
                            }

                            updateSelectionUI();

                        }
                    );


                    selectedItemsContainer
                        .appendChild(
                            element
                        );

                }
            );


            selectedEmpty.hidden =
                selected.length > 0;


            selectedTotal.textContent =
                formatPrice(
                    total
                );


            clearSelectionButton.disabled =
                selected.length === 0;


            sellButton.disabled =
                selected.length === 0;


            selectAllButton.classList.toggle(
                'has-selection',
                selected.length > 0
            );


            updateSelectAllState();

        }


        /*
         * =====================================================
         * SELECT ALL
         * =====================================================
         */

        function updateSelectAllState() {

            const groups =
                getGroups()
                    .filter(
                        group =>
                            !group.hidden
                    );

            const allCheckboxes =
                groups.flatMap(
                    group =>
                        Array.from(
                            group.querySelectorAll(
                                '.item-checkbox'
                            )
                        )
                );

            const checked =
                allCheckboxes.filter(
                    checkbox =>
                        checkbox.checked
                );


            selectAllButton.classList.toggle(
                'is-active',
                checked.length > 0
            );


            selectAllButton.classList.toggle(
                'is-all',
                allCheckboxes.length > 0
                &&
                checked.length ===
                    allCheckboxes.length
            );

        }


        selectAllButton?.addEventListener(
            'click',
            () => {

                const groups =
                    getGroups()
                        .filter(
                            group =>
                                !group.hidden
                        );

                const allCheckboxes =
                    groups.flatMap(
                        group =>
                            Array.from(
                                group.querySelectorAll(
                                    '.item-checkbox'
                                )
                            )
                    );

                const shouldSelect =
                    !allCheckboxes.length
                    ||
                    allCheckboxes.some(
                        checkbox =>
                            !checkbox.checked
                    );


                allCheckboxes.forEach(
                    checkbox => {
                        checkbox.checked =
                            shouldSelect;
                    }
                );


                groups.forEach(
                    group => {
                        syncMasterCheckbox(
                            group
                        );
                    }
                );


                updateSelectionUI();

            }
        );


        clearSelectionButton?.addEventListener(
            'click',
            () => {

                document
                    .querySelectorAll(
                        '.item-checkbox'
                    )
                    .forEach(
                        checkbox => {
                            checkbox.checked =
                                false;
                        }
                    );


                document
                    .querySelectorAll(
                        '.inventory-group'
                    )
                    .forEach(
                        group => {
                            syncMasterCheckbox(
                                group
                            );
                        }
                    );


                updateSelectionUI();

            }
        );


        /*
         * =====================================================
         * SEARCH
         * =====================================================
         */

        function applySearch() {

            const query =
                searchInput
                    ?.value
                    .trim()
                    .toLowerCase()
                    || '';


            let visibleCount = 0;


            getGroups()
                .forEach(
                    group => {

                        const name =
                            group.dataset.name
                            || '';

                        const matches =
                            query === ''
                            ||
                            name.includes(
                                query
                            );


                        group.hidden =
                            !matches;

                        if (
                            matches
                        ) {
                            visibleCount++;
                        }

                    }
                );


            if (
                noSearchResults
            ) {
                noSearchResults.hidden =
                    visibleCount !== 0;
            }


            updateSelectAllState();

        }


        searchInput?.addEventListener(
            'input',
            applySearch
        );


        /*
         * =====================================================
         * SORT
         * =====================================================
         */

        function sortInventory() {

            const groups =
                getGroups();

            const mode =
                sortSelect?.value
                || 'price-desc';


            groups.sort(
                (
                    a,
                    b
                ) => {

                    const priceA =
                        parsePrice(
                            a.dataset.price
                        );

                    const priceB =
                        parsePrice(
                            b.dataset.price
                        );

                    const nameA =
                        a.dataset.name
                        || '';

                    const nameB =
                        b.dataset.name
                        || '';


                    switch (
                        mode
                    ) {

                        case 'price-asc':
                            return (
                                priceA
                                -
                                priceB
                            );

                        case 'name-asc':
                            return nameA.localeCompare(
                                nameB,
                                'es'
                            );

                        case 'name-desc':
                            return nameB.localeCompare(
                                nameA,
                                'es'
                            );

                        case 'price-desc':
                        default:
                            return (
                                priceB
                                -
                                priceA
                            );
                    }

                }
            );


            groups.forEach(
                group => {
                    inventoryGrid
                        .appendChild(
                            group
                        );
                }
            );

        }


        sortSelect?.addEventListener(
            'change',
            sortInventory
        );


        /*
         * =====================================================
         * REFRESH
         * =====================================================
         */

        refreshButton?.addEventListener(
            'click',
            () => {

                refreshButton.classList.add(
                    'is-loading'
                );

                window.location.reload();

            }
        );


        /*
         * =====================================================
         * FLOATS
         * =====================================================
         */

        const floatElements =
            Array.from(
                document.querySelectorAll(
                    '.item-float[data-inspect-url]'
                )
            );


        const floatQueue =
            [];

        const floatProcessed =
            new Set();

        let floatBusy =
            false;


        function updateFloatElement(
            element,
            text,
            success = true
        ) {

            const value =
                element.querySelector(
                    'span, b'
                );

            if (
                !value
            ) {
                return;
            }

            value.textContent =
                text;

            element.classList.toggle(
                'float-loaded',
                success
            );

            element.classList.toggle(
                'float-error',
                !success
            );

        }


        async function fetchFloat(
            element
        ) {

            const inspectUrl =
                element.dataset.inspectUrl;

            if (
                !inspectUrl
            ) {
                return;
            }


            const encoded =
                encodeURIComponent(
                    inspectUrl
                );


            try {

                const response =
                    await fetch(
                        `api/float.php?inspect_url=${encoded}`,
                        {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: {
                                'Accept':
                                    'application/json'
                            }
                        }
                    );


                const data =
                    await response.json();


                if (
                    response.ok
                    &&
                    data.success
                    &&
                    typeof data.float ===
                        'number'
                ) {

                    updateFloatElement(
                        element,
                        data.float.toFixed(8),
                        true
                    );

                } else {

                    updateFloatElement(
                        element,
                        'No disponible',
                        false
                    );

                }

            } catch (
                error
            ) {

                updateFloatElement(
                    element,
                    'No disponible',
                    false
                );

            }

        }


        async function processFloatQueue() {

            if (
                floatBusy
            ) {
                return;
            }

            const element =
                floatQueue.shift();

            if (
                !element
            ) {
                return;
            }

            const inspectUrl =
                element.dataset.inspectUrl;

            if (
                !inspectUrl
                ||
                floatProcessed.has(
                    inspectUrl
                )
            ) {
                processFloatQueue();

                return;
            }

            floatBusy = true;

            floatProcessed.add(
                inspectUrl
            );


            await fetchFloat(
                element
            );


            /*
             * El servicio público de Float
             * puede tener límites.
             *
             * Dejamos un pequeño intervalo
             * entre consultas.
             */
            await new Promise(
                resolve =>
                    setTimeout(
                        resolve,
                        350
                    )
            );


            floatBusy = false;

            processFloatQueue();

        }


        function queueFloat(
            element
        ) {

            const inspectUrl =
                element.dataset.inspectUrl;

            if (
                !inspectUrl
            ) {
                return;
            }

            if (
                floatProcessed.has(
                    inspectUrl
                )
            ) {
                return;
            }

            if (
                floatQueue.includes(
                    element
                )
            ) {
                return;
            }

            floatQueue.push(
                element
            );

            processFloatQueue();

        }


        /*
         * Solo cargamos los Floats cuando
         * el elemento entra en pantalla.
         */
        if (
            'IntersectionObserver'
            in window
        ) {

            const observer =
                new IntersectionObserver(
                    entries => {

                        entries.forEach(
                            entry => {

                                if (
                                    entry.isIntersecting
                                ) {

                                    queueFloat(
                                        entry.target
                                    );

                                    observer.unobserve(
                                        entry.target
                                    );

                                }

                            }
                        );

                    },
                    {
                        root: null,

                        rootMargin:
                            '300px 0px',

                        threshold:
                            0.01
                    }
                );


            floatElements.forEach(
                element => {
                    observer.observe(
                        element
                    );
                }
            );

        } else {

            floatElements.forEach(
                element => {
                    queueFloat(
                        element
                    );
                }
            );

        }


        /*
         * =====================================================
         * SELL BUTTON
         * =====================================================
         */

        sellButton?.addEventListener(
            'click',
            () => {

                if (
                    sellButton.disabled
                ) {
                    return;
                }

                alert(
                    'La función de publicación de objetos '
                    + 'todavía no está conectada.'
                );

            }
        );


        /*
         * =====================================================
         * INIT
         * =====================================================
         */

        initStackButtons();

        initCheckboxes();

        sortInventory();

        applySearch();

        updateSelectionUI();


        /*
         * El contador permanece mostrando
         * objetos totales, no grupos.
         */
        if (
            inventoryCount
        ) {
            inventoryCount.textContent =
                String(
                    Number(
                        inventoryCount.textContent
                    ) || 0
                );
        }

    }
);