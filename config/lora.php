<?php

return [
    'show_temperature_graph' => filter_var(env('SHOW_TEMPERATURE_GRAPH', true), FILTER_VALIDATE_BOOLEAN),
    'show_humidity_graph' => filter_var(env('SHOW_HUMIDITY_GRAPH', true), FILTER_VALIDATE_BOOLEAN),
    'graph_window_minutes' => (int) env('GRAPH_WINDOW_MINUTES', 30),
    'graph_max_series' => (int) env('GRAPH_MAX_SERIES', 8),
];
