(() => {
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggle = document.getElementById('sidebarToggle');
    const body = document.body;

    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        backdrop?.classList.remove('show');
        body.classList.remove('sidebar-open');
    };

    toggle?.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 767px)').matches) {
            sidebar?.classList.toggle('open');
            backdrop?.classList.toggle('show');
            body.classList.toggle('sidebar-open', sidebar?.classList.contains('open'));
            return;
        }

        body.classList.toggle('sidebar-collapse');
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

    const dashboardRoot = document.getElementById('dashboardLive');
    if (dashboardRoot) {
        const formatCount = (value) => Number(value || 0).toLocaleString();

        const setUpdating = (widget, on) => {
            dashboardRoot.querySelectorAll(`[data-widget="${widget}"]`).forEach((node) => {
                node.classList.toggle('is-updating', on);
            });
        };

        const applyKpis = (data) => {
            const map = {
                statusText: data.statusText,
                gatewayCount: formatCount(data.gatewayCount),
                nodeCount: formatCount(data.nodeCount),
                onlineNodeCount: formatCount(data.onlineNodeCount),
                offlineNodeCount: formatCount(data.offlineNodeCount),
                todayPacketCount: formatCount(data.todayPacketCount),
                packetCount: formatCount(data.packetCount),
            };

            Object.entries(map).forEach(([key, value]) => {
                dashboardRoot.querySelectorAll(`[data-bind="${key}"]`).forEach((node) => {
                    if (node.textContent !== String(value)) {
                        node.textContent = value;
                    }
                });
            });
        };

        const emptyRow = (colspan, text) => {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = colspan;
            const wrap = document.createElement('div');
            wrap.className = 'empty-state';
            const muted = document.createElement('div');
            muted.className = 'muted';
            muted.textContent = text;
            wrap.appendChild(muted);
            td.appendChild(wrap);
            tr.appendChild(td);
            return tr;
        };

        const applyTelemetry = (data) => {
            const tbody = dashboardRoot.querySelector('[data-bind-rows="telemetry"]');
            if (!tbody) {
                return;
            }

            tbody.replaceChildren();
            if (data.empty || !Array.isArray(data.rows) || data.rows.length === 0) {
                tbody.appendChild(emptyRow(4, data.emptyText || ''));
                return;
            }

            data.rows.forEach((row) => {
                const tr = document.createElement('tr');

                const time = document.createElement('td');
                time.className = 'mono';
                time.textContent = row.time || '—';
                tr.appendChild(time);

                const node = document.createElement('td');
                const nodeId = document.createElement('div');
                nodeId.className = 'mono';
                nodeId.textContent = row.node_id || '';
                const gatewayId = document.createElement('div');
                gatewayId.className = 'muted';
                gatewayId.style.fontSize = '12px';
                gatewayId.textContent = row.gateway_id || '';
                node.append(nodeId, gatewayId);
                tr.appendChild(node);

                const metrics = document.createElement('td');
                const wrap = document.createElement('div');
                wrap.className = 'd-flex flex-wrap gap-1';
                (row.metrics || []).forEach((metric) => {
                    const badge = document.createElement('span');
                    badge.className = 'badge-pill badge-info mono';
                    badge.textContent = `${metric.key}: ${metric.value}`;
                    wrap.appendChild(badge);
                });
                metrics.appendChild(wrap);
                tr.appendChild(metrics);

                const rssi = document.createElement('td');
                rssi.className = 'mono';
                rssi.textContent = row.rssi || '—';
                tr.appendChild(rssi);

                tbody.appendChild(tr);
            });
        };

        const applyLogs = (data) => {
            const tbody = dashboardRoot.querySelector('[data-bind-rows="logs"]');
            if (!tbody) {
                return;
            }

            tbody.replaceChildren();
            if (data.empty || !Array.isArray(data.rows) || data.rows.length === 0) {
                tbody.appendChild(emptyRow(2, data.emptyText || ''));
                return;
            }

            data.rows.forEach((row) => {
                const tr = document.createElement('tr');

                const event = document.createElement('td');
                const title = document.createElement('div');
                title.className = 'mono';
                title.textContent = row.event || '';
                const message = document.createElement('div');
                message.className = 'muted';
                message.style.fontSize = '12px';
                message.textContent = row.message || '';
                const time = document.createElement('div');
                time.className = 'muted';
                time.style.fontSize = '11px';
                time.textContent = row.time || '';
                event.append(title, message, time);
                tr.appendChild(event);

                const level = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = `badge-pill ${row.levelClass || 'badge-info'}`;
                badge.textContent = row.level || '';
                level.appendChild(badge);
                tr.appendChild(level);

                tbody.appendChild(tr);
            });
        };

        const pollers = [
            {
                key: 'kpis',
                url: dashboardRoot.getAttribute('data-kpis-url'),
                interval: 15000,
                apply: applyKpis,
            },
            {
                key: 'telemetry',
                url: dashboardRoot.getAttribute('data-telemetry-url'),
                interval: 7000,
                apply: applyTelemetry,
            },
            {
                key: 'logs',
                url: dashboardRoot.getAttribute('data-logs-url'),
                interval: 12000,
                apply: applyLogs,
            },
        ];

        const timers = [];
        let stopped = false;

        const poll = async (widget) => {
            if (stopped || document.hidden || !widget.url || widget.inflight) {
                return;
            }

            widget.inflight = true;
            setUpdating(widget.key, true);

            try {
                const response = await fetch(widget.url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (response.status === 401 || response.status === 419) {
                    stopped = true;
                    timers.forEach((id) => clearInterval(id));
                    return;
                }

                if (!response.ok) {
                    return;
                }

                widget.apply(await response.json());
            } catch (error) {
                console.error(`Gagal refresh widget ${widget.key}`, error);
            } finally {
                widget.inflight = false;
                setUpdating(widget.key, false);
            }
        };

        pollers.forEach((widget) => {
            const timer = setInterval(() => poll(widget), widget.interval);
            timers.push(timer);
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !stopped) {
                pollers.forEach((widget) => poll(widget));
            }
        });
    }
})();
