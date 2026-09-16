document.addEventListener(
    'DOMContentLoaded',
    () => {

        /*
         * =====================================================
         * ELEMENTOS
         * =====================================================
         */

        const grid =
            document.getElementById(
                'inventory-grid'
            );

        const searchInput =
            document.getElementById(
                'inventory-search'
            );

        const sortSelect =
            document.getElementById(
                'inventory-sort'
            );

        const selectAllButton =
            document.getElementById(
                'select-all'
            );

        const selectAllCount =
            document.getElementById(
                'select-all-count'
            );

        const selectedItems =
            document.getElementById(
                'selected-items'
            );

        const selectedCount =
            document.getElementById(
                'selected-count'
            );

        const selectedTotal =
            document.getElementById(
                'selected-total'
            );

        const exitGroupSelection =
            document.getElementById(
                'exit-group-selection'
            );

        const sellButton =
            document.getElementById(
                'sell-selected'
            );

        const refreshButton =
            document.getElementById(
                'refresh-inventory'
            );

        const toast =
            document.getElementById(
                'inventory-toast'
            );


        if (!grid) {
            return;
        }


        /*
         * =====================================================
         * ESTADO
         * =====================================================
         */

        let groupSelectionMode =
            null;


        /*
         * =====================================================
         * HELPERS
         * =====================================================
         */

        function getCheckboxes() {

            return [
                ...document.querySelectorAll(
                    '.item-checkbox'
                )
            ];
        }


        function getSelected() {

            return getCheckboxes()
                .filter(
                    checkbox =>
                        checkbox.checked
                        &&
                        checkbox.dataset.assetId
                );
        }


        function getName(
            checkbox
        ) {

            return (
                checkbox.dataset.name
                ||
                'Objeto'
            );
        }


        function getPrice(
            checkbox
        ) {

            const value =
                parseFloat(
                    checkbox.dataset.price
                    || '0'
                );

            if (
                !Number.isFinite(value)
            ) {
                return 0;
            }

            return value;
        }


        function formatPrice(
            value
        ) {

            return value.toLocaleString(
                'es-ES',
                {
                    minimumFractionDigits: 2,

                    maximumFractionDigits: 2
                }
            ) + ' €';
        }


        function showToast(
            message
        ) {

            if (!toast) {
                return;
            }

            toast.textContent =
                message;

            toast.classList.add(
                'show'
            );

            clearTimeout(
                showToast.timer
            );

            showToast.timer =
                setTimeout(
                    () => {

                        toast.classList.remove(
                            'show'
                        );

                    },
                    3000
                );
        }


        /*
         * =====================================================
         * AGRUPAR SELECCIÓN
         * =====================================================
         */

        function getSelectionGroups() {

            const selected =
                getSelected();

            const groups =
                new Map();

            const individual = [];


            selected.forEach(
                checkbox => {

                    const stackable =
                        checkbox.dataset.stackable
                        === '1';


                    /*
                     * Objetos normales:
                     * siempre individuales.
                     */

                    if (!stackable) {

                        individual.push(
                            checkbox
                        );

                        return;
                    }


                    /*
                     * Objetos agrupables:
                     * agrupamos por market hash.
                     */

                    const key =
                        checkbox.dataset.marketHashName
                        ||
                        getName(
                            checkbox
                        );


                    if (
                        !groups.has(key)
                    ) {

                        groups.set(
                            key,
                            []
                        );
                    }


                    groups
                        .get(key)
                        .push(
                            checkbox
                        );
                }
            );


            return {
                groups,
                individual
            };
        }


        /*
         * =====================================================
         * PANEL DERECHO
         * =====================================================
         */

        function renderSelectedItems() {

            const selected =
                getSelected();

            const {
                groups,
                individual
            } =
                getSelectionGroups();


            selectedItems.innerHTML =
                '';


            /*
             * -------------------------------------------------
             * GRUPOS
             * -------------------------------------------------
             */

            groups.forEach(
                (
                    items
                ) => {

                    if (
                        !items.length
                    ) {
                        return;
                    }


                    const row =
                        document.createElement(
                            'div'
                        );

                    row.className =
                        'selected-group-row';


                    const info =
                        document.createElement(
                            'div'
                        );

                    info.className =
                        'selected-group-info';


                    const name =
                        document.createElement(
                            'strong'
                        );

                    name.textContent =
                        getName(
                            items[0]
                        );


                    const quantity =
                        document.createElement(
                            'span'
                        );

                    quantity.className =
                        'selected-group-quantity';

                    quantity.textContent =
                        '× '
                        +
                        items.length;


                    info.appendChild(
                        name
                    );

                    info.appendChild(
                        quantity
                    );


                    const total =
                        items.reduce(
                            (
                                sum,
                                checkbox
                            ) => {

                                return (
                                    sum
                                    +
                                    getPrice(
                                        checkbox
                                    )
                                );

                            },
                            0
                        );


                    const price =
                        document.createElement(
                            'strong'
                        );

                    price.className =
                        'selected-group-price';

                    price.textContent =
                        formatPrice(
                            total
                        );


                    /*
                     * IMPORTANTE:
                     *
                     * No añadimos imagen.
                     */

                    row.appendChild(
                        info
                    );

                    row.appendChild(
                        price
                    );

                    selectedItems.appendChild(
                        row
                    );
                }
            );


            /*
             * -------------------------------------------------
             * INDIVIDUALES
             * -------------------------------------------------
             */

            individual.forEach(
                checkbox => {

                    const row =
                        document.createElement(
                            'div'
                        );

                    row.className =
                        'selected-item-row';


                    const info =
                        document.createElement(
                            'div'
                        );

                    info.className =
                        'selected-item-info';


                    const name =
                        document.createElement(
                            'strong'
                        );

                    name.textContent =
                        getName(
                            checkbox
                        );


                    const price =
                        document.createElement(
                            'span'
                        );

                    price.textContent =
                        formatPrice(
                            getPrice(
                                checkbox
                            )
                        );


                    info.appendChild(
                        name
                    );

                    info.appendChild(
                        price
                    );


                    row.appendChild(
                        info
                    );

                    selectedItems.appendChild(
                        row
                    );
                }
            );


            /*
             * -------------------------------------------------
             * NADA SELECCIONADO
             * -------------------------------------------------
             */

            if (
                selected.length === 0
            ) {

                const empty =
                    document.createElement(
                        'div'
                    );

                empty.className =
                    'selected-empty';

                empty.textContent =
                    'Selecciona objetos para añadirlos aquí.';

                selectedItems.appendChild(
                    empty
                );
            }


            /*
             * -------------------------------------------------
             * CONTADOR
             * -------------------------------------------------
             */

            selectedCount.textContent =
                selected.length;


            /*
             * -------------------------------------------------
             * TOTAL
             * -------------------------------------------------
             */

            const total =
                selected.reduce(
                    (
                        sum,
                        checkbox
                    ) =>
                        sum
                        +
                        getPrice(
                            checkbox
                        ),
                    0
                );


            selectedTotal.textContent =
                formatPrice(
                    total
                );


            /*
             * -------------------------------------------------
             * VENDER
             * -------------------------------------------------
             */

            if (sellButton) {

                sellButton.disabled =
                    selected.length === 0;
            }


            /*
             * -------------------------------------------------
             * MODO GRUPO
             * -------------------------------------------------
             */

            const hasStackableSelected =
                selected.some(
                    checkbox =>
                        checkbox.dataset.stackable
                        === '1'
                );


            if (
                hasStackableSelected
            ) {

                exitGroupSelection.hidden =
                    false;

            } else {

                groupSelectionMode =
                    null;

                exitGroupSelection.hidden =
                    true;
            }


            /*
             * Actualizar estado visual de
             * las tarjetas.
             */

            updateCardStates();

        }


        /*
         * =====================================================
         * ESTADO VISUAL TARJETAS
         * =====================================================
         */

        function updateCardStates() {

            document
                .querySelectorAll(
                    '.inventory-card'
                )
                .forEach(
                    card => {

                        const checkboxes =
                            [
                                ...card.querySelectorAll(
                                    '.item-checkbox'
                                )
                            ];


                        const checked =
                            checkboxes.filter(
                                checkbox =>
                                    checkbox.checked
                            );


                        card.classList.toggle(
                            'is-selected',
                            checked.length > 0
                        );


                        /*
                         * Master checkbox del stack.
                         */

                        const master =
                            card.querySelector(
                                '.stack-master'
                            );


                        if (
                            master
                        ) {

                            if (
                                checked.length ===
                                checkboxes.length
                                &&
                                checkboxes.length > 0
                            ) {

                                master.checked =
                                    true;

                                master.indeterminate =
                                    false;

                            } else if (
                                checked.length > 0
                            ) {

                                master.checked =
                                    false;

                                master.indeterminate =
                                    true;

                            } else {

                                master.checked =
                                    false;

                                master.indeterminate =
                                    false;
                            }
                        }


                        /*
                         * Estado visual de cada
                         * checkbox.
                         */

                        checkboxes.forEach(
                            checkbox => {

                                const label =
                                    checkbox.closest(
                                        'label'
                                    );

                                if (!label) {
                                    return;
                                }

                                label.classList.toggle(
                                    'is-checked',
                                    checkbox.checked
                                );
                            }
                        );

                    }
                );


            updateSelectAllButton();
        }


        /*
         * =====================================================
         * SELECT ALL
         * =====================================================
         */

        function updateSelectAllButton() {

            const all =
                getCheckboxes();

            const selected =
                getSelected();


            selectAllCount.textContent =
                selected.length;


            if (
                all.length === 0
            ) {
                return;
            }


            if (
                selected.length ===
                all.length
            ) {

                selectAllButton.classList.add(
                    'is-active'
                );

            } else {

                selectAllButton.classList.remove(
                    'is-active'
                );
            }
        }


        if (
            selectAllButton
        ) {

            selectAllButton.addEventListener(
                'click',
                () => {

                    const all =
                        getCheckboxes();

                    const selected =
                        getSelected();


                    const shouldSelect =
                        selected.length !==
                        all.length;


                    all.forEach(
                        checkbox => {

                            checkbox.checked =
                                shouldSelect;
                        }
                    );


                    if (
                        shouldSelect
                    ) {

                        /*
                         * Si hay objetos agrupables,
                         * entramos en modo grupo.
                         */

                        const firstStack =
                            all.find(
                                checkbox =>
                                    checkbox.dataset.stackable
                                    === '1'
                            );


                        if (
                            firstStack
                        ) {

                            groupSelectionMode =
                                firstStack.dataset.marketHashName
                                ||
                                getName(
                                    firstStack
                                );
                        }

                    } else {

                        groupSelectionMode =
                            null;
                    }


                    renderSelectedItems();
                }
            );
        }


        /*
         * =====================================================
         * MASTER CHECKBOX DE STACK
         * =====================================================
         */

        document
            .querySelectorAll(
                '.stack-master'
            )
            .forEach(
                master => {

                    master.addEventListener(
                        'change',
                        event => {

                            event.stopPropagation();


                            const key =
                                master.dataset.groupKey;


                            const card =
                                master.closest(
                                    '.inventory-card'
                                );


                            if (!card) {
                                return;
                            }


                            const children =
                                [
                                    ...card.querySelectorAll(
                                        '.item-checkbox'
                                    )
                                ];


                            children.forEach(
                                checkbox => {

                                    checkbox.checked =
                                        master.checked;
                                }
                            );


                            if (
                                master.checked
                            ) {

                                const first =
                                    children[0];

                                if (
                                    first
                                ) {

                                    groupSelectionMode =
                                        first.dataset.marketHashName
                                        ||
                                        getName(
                                            first
                                        );
                                }

                            } else {

                                /*
                                 * Comprobar si todavía
                                 * queda otro stack seleccionado.
                                 */

                                const remaining =
                                    getSelected()
                                        .filter(
                                            checkbox =>
                                                checkbox.dataset.stackable
                                                === '1'
                                        );


                                if (
                                    remaining.length === 0
                                ) {

                                    groupSelectionMode =
                                        null;
                                }
                            }


                            renderSelectedItems();
                        }
                    );
                }
            );


        /*
         * =====================================================
         * CHECKBOXES INDIVIDUALES
         * =====================================================
         */

        getCheckboxes()
            .forEach(
                checkbox => {

                    checkbox.addEventListener(
                        'change',
                        () => {

                            const stackable =
                                checkbox.dataset.stackable
                                === '1';


                            /*
                             * Objeto agrupable.
                             */

                            if (
                                checkbox.checked
                                &&
                                stackable
                            ) {

                                groupSelectionMode =
                                    checkbox.dataset.marketHashName
                                    ||
                                    getName(
                                        checkbox
                                    );
                            }


                            /*
                             * Si deseleccionamos,
                             * comprobar si sigue habiendo
                             * objetos del grupo.
                             */

                            if (
                                !checkbox.checked
                                &&
                                stackable
                            ) {

                                const remaining =
                                    getSelected()
                                        .filter(
                                            item =>
                                                item.dataset.stackable
                                                === '1'
                                                &&
                                                (
                                                    item.dataset.marketHashName
                                                    ||
                                                    getName(
                                                        item
                                                    )
                                                )
                                                ===
                                                (
                                                    checkbox.dataset.marketHashName
                                                    ||
                                                    getName(
                                                        checkbox
                                                    )
                                                )
                                        );


                                if (
                                    remaining.length === 0
                                ) {

                                    groupSelectionMode =
                                        null;
                                }
                            }


                            renderSelectedItems();
                        }
                    );
                }
            );


        /*
         * =====================================================
         * SALIR DEL MODO SELECCIÓN DE GRUPO
         * =====================================================
         */

        if (
            exitGroupSelection
        ) {

            exitGroupSelection.addEventListener(
                'click',
                () => {

                    if (
                        groupSelectionMode === null
                    ) {
                        return;
                    }


                    const currentGroup =
                        groupSelectionMode;


                    getCheckboxes()
                        .forEach(
                            checkbox => {

                                if (
                                    checkbox.dataset.stackable
                                    !== '1'
                                ) {
                                    return;
                                }


                                const group =
                                    checkbox.dataset.marketHashName
                                    ||
                                    getName(
                                        checkbox
                                    );


                                if (
                                    group ===
                                    currentGroup
                                ) {

                                    checkbox.checked =
                                        false;
                                }
                            }
                        );


                    /*
                     * Desmarcar master del stack.
                     */

                    document
                        .querySelectorAll(
                            '.stack-master'
                        )
                        .forEach(
                            master => {

                                master.checked =
                                    false;

                                master.indeterminate =
                                    false;
                            }
                        );


                    groupSelectionMode =
                        null;


                    renderSelectedItems();
                }
            );
        }


        /*
         * =====================================================
         * EXPANDIR STACK
         * =====================================================
         */

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


                            const card =
                                button.closest(
                                    '.inventory-card'
                                );


                            if (!card) {
                                return;
                            }


                            const expanded =
                                card.classList.toggle(
                                    'is-expanded'
                                );


                            button.setAttribute(
                                'aria-label',
                                expanded
                                    ? 'Ocultar objetos'
                                    : 'Mostrar objetos'
                            );


                            button
                                .querySelector(
                                    'span'
                                )
                                ?.classList.toggle(
                                    'rotated',
                                    expanded
                                );
                        }
                    );
                }
            );


        /*
         * =====================================================
         * BÚSQUEDA
         * =====================================================
         */

        if (
            searchInput
        ) {

            searchInput.addEventListener(
                'input',
                () => {

                    const query =
                        searchInput.value
                            .trim()
                            .toLowerCase();


                    document
                        .querySelectorAll(
                            '.inventory-card'
                        )
                        .forEach(
                            card => {

                                const text =
                                    card.dataset.search
                                    || '';


                                const visible =
                                    text.includes(
                                        query
                                    );


                                card.classList.toggle(
                                    'search-hidden',
                                    !visible
                                );
                            }
                        );
                }
            );
        }


        /*
         * =====================================================
         * ORDEN
         * =====================================================
         */

        if (
            sortSelect
        ) {

            sortSelect.addEventListener(
                'change',
                () => {

                    const cards =
                        [
                            ...grid.querySelectorAll(
                                '.inventory-card'
                            )
                        ];


                    const mode =
                        sortSelect.value;


                    cards.sort(
                        (
                            a,
                            b
                        ) => {

                            const priceA =
                                parseFloat(
                                    a.dataset.price
                                    || '0'
                                );

                            const priceB =
                                parseFloat(
                                    b.dataset.price
                                    || '0'
                                );

                            const nameA =
                                (
                                    a.dataset.name
                                    || ''
                                ).toLowerCase();

                            const nameB =
                                (
                                    b.dataset.name
                                    || ''
                                ).toLowerCase();


                            switch (mode) {

                                case 'price-asc':

                                    return (
                                        priceA
                                        -
                                        priceB
                                    );

                                case 'name-asc':

                                    return nameA.localeCompare(
                                        nameB
                                    );

                                case 'name-desc':

                                    return nameB.localeCompare(
                                        nameA
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


                    cards.forEach(
                        card =>
                            grid.appendChild(
                                card
                            )
                    );
                }
            );
        }


        /*
         * =====================================================
         * BOTÓN REFRESH
         * =====================================================
         */

        if (
            refreshButton
        ) {

            refreshButton.addEventListener(
                'click',
                () => {

                    refreshButton.classList.add(
                        'is-loading'
                    );

                    window.location.reload();
                }
            );
        }


        /*
         * =====================================================
         * VENDER
         * =====================================================
         */

        if (
            sellButton
        ) {

            sellButton.addEventListener(
                'click',
                () => {

                    if (
                        sellButton.disabled
                    ) {
                        return;
                    }


                    showToast(
                        'La publicación de ventas todavía no está implementada.'
                    );
                }
            );
        }


        /*
         * =====================================================
         * FLOAT LAZY LOADING
         * =====================================================
         */

        const floatElements =
            [
                ...document.querySelectorAll(
                    '.inventory-float.float-pending[data-inspect]'
                )
            ];


        const floatQueue = [];

        const queued =
            new Set();

        let floatLoading =
            false;


        /*
         * Meter URL en cola.
         */

        function queueFloat(
            element
        ) {

            const inspect =
                element.dataset.inspect;


            if (
                !inspect
            ) {
                return;
            }


            if (
                queued.has(inspect)
            ) {
                return;
            }


            queued.add(
                inspect
            );


            floatQueue.push({
                element,
                inspect
            });


            processFloatQueue();
        }


        /*
         * Una petición cada vez.
         */

        async function processFloatQueue() {

            if (
                floatLoading
            ) {
                return;
            }


            if (
                floatQueue.length === 0
            ) {
                return;
            }


            floatLoading =
                true;


            const task =
                floatQueue.shift();


            try {

                await loadFloat(
                    task.element,
                    task.inspect
                );

            } catch (
                error
            ) {

                console.warn(
                    'Float error:',
                    error
                );
            }


            floatLoading =
                false;


            /*
             * Pequeño espacio entre peticiones.
             */

            setTimeout(
                processFloatQueue,
                1000
            );
        }


        async function loadFloat(
            element,
            inspect
        ) {

            const valueElement =
                element.querySelector(
                    '.float-value'
                );


            const bar =
                element.querySelector(
                    '.float-bar'
                );


            if (
                valueElement
            ) {

                valueElement.textContent =
                    'Float cargando...';
            }


            const url =
                'api/float.php?inspect_url='
                +
                encodeURIComponent(
                    inspect
                );


            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',
                        credentials: 'same-origin'
                    }
                );


            if (
                !response.ok
            ) {
                throw new Error(
                    'HTTP '
                    +
                    response.status
                );
            }


            const data =
                await response.json();


            if (
                data.success
                &&
                data.float !== null
            ) {

                const floatValue =
                    Number(
                        data.float
                    );


                if (
                    valueElement
                ) {

                    valueElement.textContent =
                        'Float '
                        +
                        floatValue.toFixed(
                            6
                        );
                }


                /*
                 * Barra de desgaste.
                 *
                 * 0 = nuevo
                 * 1 = muy desgastado
                 */

                if (
                    bar
                ) {

                    const normalized =
                        Math.max(
                            0,
                            Math.min(
                                1,
                                floatValue
                            )
                        );


                    bar.style.setProperty(
                        '--wear',
                        normalized
                    );
                }


                element.classList.remove(
                    'float-pending'
                );

                element.classList.add(
                    'float-loaded'
                );

            } else {

                if (
                    valueElement
                ) {

                    valueElement.textContent =
                        'Float no disponible';
                }


                element.classList.remove(
                    'float-pending'
                );

                element.classList.add(
                    'float-unavailable'
                );
            }
        }


        /*
         * =====================================================
         * INTERSECTION OBSERVER
         * =====================================================
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
                        rootMargin:
                            '250px 0px'
                    }
                );


            floatElements.forEach(
                element =>
                    observer.observe(
                        element
                    )
            );

        } else {

            /*
             * Fallback.
             */

            floatElements.forEach(
                queueFloat
            );
        }


        /*
         * =====================================================
         * INICIALIZACIÓN
         * =====================================================
         */

        renderSelectedItems();

    }
);