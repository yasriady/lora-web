# Grafana Time Series Setup

This app stores flexible sensor metrics in a Grafana-friendly long-format table.

## Data model

- `telemetry` — one row per packet (envelope + JSON `metrics`)
- `telemetry_readings` — one row per metric value (best for graphs)
- `grafana_telemetry_readings` — SQL view for Grafana panels

View columns:

| Column | Meaning |
|---|---|
| `time` | Timestamp |
| `gateway_id` | Gateway ID |
| `node_id` | Node ID |
| `metric_key` | Metric name (`temperature`, `co2`, ...) |
| `value` | Numeric value |
| `unit` | Optional unit |
| `telemetry_id` | Source packet id |

## API payload (recommended)

```json
{
  "gateway_id": "GW001",
  "node_id": "NODE01",
  "timestamp": "2026-08-10T12:00:00Z",
  "battery": 3.92,
  "rssi": -82,
  "snr": 8.5,
  "metrics": {
    "temperature": 29.5,
    "humidity": 73,
    "soil_moisture": 41.2
  }
}
```

Legacy flat fields (`temperature`, `humidity`, ...) are still accepted and normalized into `metrics`.

## Grafana MySQL data source

1. Add **MySQL** data source pointing to `lora_db`
2. Create dashboard variables (optional):
   - `gateway_id` from `SELECT DISTINCT gateway_id FROM telemetry_readings`
   - `node_id` from `SELECT DISTINCT node_id FROM telemetry_readings WHERE gateway_id = '$gateway_id'`
   - `metric_key` from `SELECT DISTINCT metric_key FROM telemetry_readings`
3. Add **Time series** panel with query:

```sql
SELECT
  time AS "time",
  value AS value,
  metric_key AS metric
FROM grafana_telemetry_readings
WHERE
  $__timeFilter(time)
  AND gateway_id = '$gateway_id'
  AND node_id = '$node_id'
  AND metric_key IN ($metric_key)
ORDER BY time
```

Format in Grafana:
- Format: **Time series**
- Metric column used as series legend

## Single-metric panel example

```sql
SELECT
  time AS "time",
  value
FROM grafana_telemetry_readings
WHERE
  $__timeFilter(time)
  AND node_id = 'NODE01'
  AND metric_key = 'temperature'
ORDER BY time
```

## Useful indexes

Already created on `telemetry_readings`:

- `(node_id, metric_key, timestamp)`
- `(gateway_id, metric_key, timestamp)`
- `(timestamp, metric_key)`

## Curl test

```bash
curl -X POST https://lora-dev.apache.web.id/api/v1/telemetry \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "gateway_id":"GW001",
    "node_id":"NODE01",
    "timestamp":"2026-08-10T12:00:00Z",
    "battery":3.9,
    "rssi":-80,
    "snr":7.5,
    "metrics":{"temperature":30.1,"humidity":70,"pressure":1012.4}
  }'
```
