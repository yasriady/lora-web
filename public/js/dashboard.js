(() => {
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
            muted.className = 'text-secondary';
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
                gatewayId.className = 'text-secondary small';
                gatewayId.textContent = row.gateway_id || '';
                node.append(nodeId, gatewayId);
                tr.appendChild(node);

                const metrics = document.createElement('td');
                const wrap = document.createElement('div');
                wrap.className = 'd-flex flex-wrap gap-1';
                (row.metrics || []).forEach((metric) => {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-azure-lt mono';
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
                message.className = 'text-secondary small';
                message.textContent = row.message || '';
                const time = document.createElement('div');
                time.className = 'text-secondary';
                time.style.fontSize = '11px';
                time.textContent = row.time || '';
                event.append(title, message, time);
                tr.appendChild(event);

                const level = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = `badge ${row.levelClass || 'bg-azure-lt'}`;
                badge.textContent = row.level || '';
                level.appendChild(badge);
                tr.appendChild(level);

                tbody.appendChild(tr);
            });
        };

        const palette = ['#206bc4', '#2fb344', '#f59f00', '#d63939', '#4299e1', '#ae3ec9', '#0ca678', '#f76707'];
        const chartCanvas = document.getElementById('envChart');
        const chartEmpty = document.getElementById('envChartEmpty');
        const gatewayFilter = document.getElementById('chartGatewayFilter');
        const nodeFilter = document.getElementById('chartNodeFilter');
        const showTemp = dashboardRoot.getAttribute('data-chart-temp') === '1';
        const showHum = dashboardRoot.getAttribute('data-chart-hum') === '1';
        let envChart = null;
        let chartSince = null;
        let chartFilterKey = '';

        const colorFor = (gatewayId, nodeId) => {
            const key = `${gatewayId}|${nodeId}`;
            let hash = 0;
            for (let i = 0; i < key.length; i += 1) {
                hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
            }
            return palette[hash % palette.length];
        };

        const syncNodeFilterOptions = () => {
            if (!nodeFilter) {
                return;
            }

            const gatewayId = gatewayFilter?.value || '';
            Array.from(nodeFilter.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }
                option.hidden = Boolean(gatewayId) && option.getAttribute('data-gateway') !== gatewayId;
            });

            const selected = nodeFilter.selectedOptions[0];
            if (selected?.hidden) {
                nodeFilter.value = '';
            }
        };

        const resetChart = () => {
            chartSince = null;
            if (envChart) {
                envChart.data.datasets = [];
                envChart.update('none');
            }
        };

        const ensureChart = (showTemperature, showHumidity) => {
            if (!chartCanvas || typeof Chart === 'undefined') {
                return null;
            }

            if (envChart) {
                return envChart;
            }

            const scales = {
                x: {
                    type: 'time',
                    time: { tooltipFormat: 'HH:mm:ss', displayFormats: { minute: 'HH:mm', second: 'HH:mm:ss' } },
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                    grid: { color: 'rgba(210, 214, 222, 0.6)' },
                },
            };

            if (showTemperature) {
                scales.temperature = {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: '°C' },
                    grid: { color: 'rgba(210, 214, 222, 0.45)' },
                };
            }

            if (showHumidity) {
                scales.humidity = {
                    type: 'linear',
                    position: showTemperature ? 'right' : 'left',
                    title: { display: true, text: '%' },
                    min: 0,
                    max: 100,
                    grid: { drawOnChartArea: !showTemperature },
                };
            }

            envChart = new Chart(chartCanvas, {
                type: 'line',
                data: { datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'nearest', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    },
                    scales,
                    datasets: {
                        line: { pointRadius: 0, pointHoverRadius: 3, borderWidth: 2, tension: 0.25 },
                    },
                },
            });

            return envChart;
        };

        const applyChart = (data) => {
            const lastValues = dashboardRoot.querySelector('[data-bind="chartLastValues"]');
            const note = dashboardRoot.querySelector('[data-bind="chartNote"]');
            const series = Array.isArray(data.series) ? data.series : [];
            const incremental = Boolean(chartSince);
            const hasIncomingPoints = series.some((item) => (item.points || []).length > 0);
            const hasExistingPoints = Boolean(envChart?.data.datasets.some((dataset) => dataset.data.length > 0));

            if (chartEmpty) {
                chartEmpty.hidden = hasIncomingPoints || hasExistingPoints;
            }

            if (note) {
                note.textContent = data.truncated ? (dashboardRoot.getAttribute('data-chart-truncated') || '') : '';
            }

            const chart = ensureChart(showTemp, showHum);
            if (!chart) {
                return;
            }

            const windowMs = (Number(data.windowMinutes) || 30) * 60 * 1000;
            const cutoff = Date.now() - windowMs;
            const seen = new Set();

            series.forEach((item) => {
                seen.add(item.id);
                const color = colorFor(item.gateway_id, item.node_id);
                let dataset = chart.data.datasets.find((entry) => entry.id === item.id);
                if (!dataset) {
                    dataset = {
                        id: item.id,
                        label: item.label,
                        unit: item.unit,
                        yAxisID: item.axis,
                        borderColor: color,
                        backgroundColor: color,
                        borderDash: item.metric === 'humidity' ? [6, 4] : [],
                        data: [],
                    };
                    chart.data.datasets.push(dataset);
                } else {
                    dataset.label = item.label;
                    dataset.unit = item.unit;
                }

                const incoming = (item.points || [])
                    .filter((point) => point.t && point.v != null)
                    .map((point) => ({ x: point.t, y: point.v }));

                if (!incremental) {
                    dataset.data = incoming;
                } else if (incoming.length) {
                    const lastX = dataset.data.length ? dataset.data[dataset.data.length - 1].x : null;
                    incoming.forEach((point) => {
                        if (!lastX || point.x > lastX) {
                            dataset.data.push(point);
                        }
                    });
                }

                dataset.data = dataset.data.filter((point) => new Date(point.x).getTime() >= cutoff);
            });

            if (!incremental) {
                chart.data.datasets = chart.data.datasets.filter((dataset) => seen.has(dataset.id));
            }

            chart.update('none');

            if (lastValues) {
                lastValues.replaceChildren();
                chart.data.datasets.forEach((dataset) => {
                    const lastPoint = dataset.data[dataset.data.length - 1];
                    if (!lastPoint) {
                        return;
                    }
                    const chip = document.createElement('span');
                    chip.className = 'chart-chip';
                    const dot = document.createElement('span');
                    dot.className = 'chart-chip-dot';
                    dot.style.background = dataset.borderColor;
                    chip.appendChild(dot);
                    chip.appendChild(document.createTextNode(`${dataset.label}: ${lastPoint.y} ${dataset.unit || ''}`));
                    lastValues.appendChild(chip);
                });
            }

            if (data.latest) {
                chartSince = data.latest;
            }
        };

        const chartUrl = dashboardRoot.getAttribute('data-chart-url');

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

        if (chartUrl && chartCanvas) {
            pollers.push({
                key: 'chart',
                url: chartUrl,
                interval: 7000,
                apply: applyChart,
                buildUrl: () => {
                    const params = new URLSearchParams();
                    if (gatewayFilter?.value) {
                        params.set('gateway_id', gatewayFilter.value);
                    }
                    if (nodeFilter?.value) {
                        params.set('node_id', nodeFilter.value);
                    }
                    if (chartSince) {
                        params.set('since', chartSince);
                    }
                    const query = params.toString();
                    return query ? `${chartUrl}?${query}` : chartUrl;
                },
            });
        }

        const timers = [];
        let stopped = false;

        const poll = async (widget) => {
            const requestUrl = widget.buildUrl ? widget.buildUrl() : widget.url;
            if (stopped || document.hidden || !requestUrl || widget.inflight) {
                return;
            }

            widget.inflight = true;
            setUpdating(widget.key, true);

            try {
                const response = await fetch(requestUrl, {
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

        const chartWidget = pollers.find((widget) => widget.key === 'chart');
        if (chartWidget) {
            poll(chartWidget);
        }

        const onChartFilterChange = () => {
            const nextKey = `${gatewayFilter?.value || ''}|${nodeFilter?.value || ''}`;
            if (nextKey === chartFilterKey) {
                return;
            }
            chartFilterKey = nextKey;
            syncNodeFilterOptions();
            resetChart();
            if (chartWidget) {
                poll(chartWidget);
            }
        };

        gatewayFilter?.addEventListener('change', onChartFilterChange);
        nodeFilter?.addEventListener('change', onChartFilterChange);
        syncNodeFilterOptions();
        chartFilterKey = `${gatewayFilter?.value || ''}|${nodeFilter?.value || ''}`;

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !stopped) {
                pollers.forEach((widget) => poll(widget));
            }
        });
    }
})();
