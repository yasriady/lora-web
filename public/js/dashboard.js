(() => {
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggle = document.getElementById('sidebarToggle');

    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        backdrop?.classList.remove('show');
    };

    toggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        backdrop?.classList.toggle('show');
    });

    backdrop?.addEventListener('click', closeSidebar);

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.getAttribute('data-copy') || '';
            try {
                await navigator.clipboard.writeText(value);
                const original = button.textContent;
                const copiedLabel = button.getAttribute('data-copied-label') || 'Copied';
                button.textContent = copiedLabel;
                setTimeout(() => {
                    button.textContent = original;
                }, 1500);
            } catch (error) {
                console.error(error);
            }
        });
    });

    const fillModalFromButton = (modal, button) => {
        if (!modal || !button) {
            return;
        }

        const form = modal.querySelector('form');
        const action = button.getAttribute('data-action');
        if (form && action) {
            form.action = action;
        }

        const raw = button.getAttribute('data-fill-edit');
        if (!raw) {
            return;
        }

        let payload = {};
        try {
            const decoded = raw && !raw.trim().startsWith('{')
                ? atob(raw)
                : raw;
            payload = JSON.parse(decoded);
        } catch (error) {
            console.error('Gagal membaca data edit:', error, raw);
            return;
        }

        Object.entries(payload).forEach(([key, value]) => {
            const field = modal.querySelector(`[name="${key}"]`);
            if (!field) {
                return;
            }

            if (field.tagName === 'SELECT') {
                field.value = value ?? '';
            } else {
                field.value = value == null ? '' : String(value);
            }
        });
    };

    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('show.bs.modal', (event) => {
            fillModalFromButton(modal, event.relatedTarget);
        });
    });

    const deleteModal = document.getElementById('confirmDeleteModal');
    const deleteForm = document.getElementById('confirmDeleteForm');
    const deleteInput = document.getElementById('confirmDeleteInput');
    const deleteSubmit = document.getElementById('confirmDeleteSubmit');
    const deleteLabel = document.getElementById('confirmDeleteLabel');
    const deleteHint = document.getElementById('confirmDeleteHint');
    const allowedWords = new Set(['delete', 'hapus']);

    const syncDeleteConfirm = () => {
        if (!deleteInput || !deleteSubmit) {
            return;
        }

        const value = deleteInput.value.trim().toLowerCase();
        const matched = allowedWords.has(value);
        deleteSubmit.disabled = !matched;
        deleteHint?.classList.toggle('d-none', matched || value === '');
    };

    deleteModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        if (!button || !deleteForm) {
            return;
        }

        const action = button.getAttribute('data-delete-action');
        const label = button.getAttribute('data-delete-label') || 'item ini';

        if (action) {
            deleteForm.action = action;
        }

        if (deleteLabel) {
            deleteLabel.textContent = label;
        }

        if (deleteInput) {
            deleteInput.value = '';
            deleteInput.focus();
        }

        syncDeleteConfirm();
    });

    deleteModal?.addEventListener('shown.bs.modal', () => {
        deleteInput?.focus();
    });

    deleteInput?.addEventListener('input', syncDeleteConfirm);

    deleteForm?.addEventListener('submit', (event) => {
        const value = (deleteInput?.value || '').trim().toLowerCase();
        if (!allowedWords.has(value)) {
            event.preventDefault();
            syncDeleteConfirm();
            deleteInput?.focus();
        }
    });

    const presetSchemas = {
        env_basic: {
            temperature: { label: 'Temperature', unit: '°C', type: 'number' },
            humidity: { label: 'Humidity', unit: '%', type: 'number' },
        },
        env_bme280: {
            temperature: { label: 'Temperature', unit: '°C', type: 'number' },
            humidity: { label: 'Humidity', unit: '%', type: 'number' },
            pressure: { label: 'Pressure', unit: 'hPa', type: 'number' },
        },
        soil_v1: {
            soil_moisture: { label: 'Soil Moisture', unit: '%', type: 'number' },
            temperature: { label: 'Soil Temp', unit: '°C', type: 'number' },
        },
        air_quality: {
            co2: { label: 'CO2', unit: 'ppm', type: 'number' },
            pm25: { label: 'PM2.5', unit: 'ug/m3', type: 'number' },
            pm10: { label: 'PM10', unit: 'ug/m3', type: 'number' },
            tvoc: { label: 'TVOC', unit: 'ppb', type: 'number' },
        },
        gps_tracker: {
            latitude: { label: 'Latitude', unit: 'deg', type: 'number' },
            longitude: { label: 'Longitude', unit: 'deg', type: 'number' },
            altitude: { label: 'Altitude', unit: 'm', type: 'number' },
            speed: { label: 'Speed', unit: 'km/h', type: 'number' },
        },
        custom: {},
    };

    const syncNodeTypeFields = (modal) => {
        if (!modal) {
            return;
        }

        const typeSelect = modal.querySelector('.js-node-type');
        const schemaWrap = modal.querySelector('.js-schema-wrap');
        const schemaInput = modal.querySelector('.js-schema-json');
        if (!typeSelect || !schemaWrap || !schemaInput) {
            return;
        }

        const selected = typeSelect.value;
        const isCustom = selected === 'custom';
        schemaWrap.style.display = isCustom ? '' : 'none';

        if (!isCustom) {
            schemaInput.value = JSON.stringify(presetSchemas[selected] || {}, null, 2);
        } else if (!schemaInput.value.trim()) {
            schemaInput.value = '{\n  \n}';
        }
    };

    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('shown.bs.modal', () => syncNodeTypeFields(modal));
        modal.querySelector('.js-node-type')?.addEventListener('change', () => syncNodeTypeFields(modal));
    });
})();
