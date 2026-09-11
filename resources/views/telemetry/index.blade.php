@extends('layouts.app')

@section('title', __('ui.telemetry.title'))
@section('kicker', __('ui.telemetry.kicker'))
@section('heading', __('ui.telemetry.title'))
@section('subtitle', __('ui.telemetry.subtitle'))

@section('actions')
<a class="btn btn-soft" href="{{ route('telemetry.export', request()->query()) }}">{{ __('ui.telemetry.export') }}</a>
@endsection

@section('content')
<div
    id="telemetryLive"
    data-live-url="{{ route('telemetry.index', request()->query()) }}"
>
<form class="toolbar panel panel-body" method="GET">
    <input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.telemetry.search_placeholder') }}">
    <select class="form-select" name="gateway_id" style="max-width:200px">
        <option value="">{{ __('ui.telemetry.all_gateways') }}</option>
        @foreach ($gateways as $gateway)
            <option value="{{ $gateway->gateway_id }}" @selected(request('gateway_id') === $gateway->gateway_id)>{{ $gateway->gateway_id }}</option>
        @endforeach
    </select>
    <select class="form-select" name="node_id" style="max-width:200px">
        <option value="">{{ __('ui.telemetry.all_nodes') }}</option>
        @foreach ($nodes as $node)
            <option value="{{ $node->node_id }}" @selected(request('node_id') === $node->node_id)>{{ $node->node_id }}</option>
        @endforeach
    </select>
    <select class="form-select" name="metric_key" style="max-width:200px">
        <option value="">{{ __('ui.telemetry.all_metrics') }}</option>
        @foreach ($metricKeys as $metricKey)
            <option value="{{ $metricKey }}" @selected(request('metric_key') === $metricKey)>{{ $metricKey }}</option>
        @endforeach
    </select>
    <button class="btn btn-soft" type="submit">{{ __('ui.filter') }}</button>
    @if (request()->hasAny(['search', 'gateway_id', 'node_id', 'metric_key']))
        <a href="{{ route('telemetry.index') }}" class="btn btn-outline-secondary">{{ __('ui.reset') }}</a>
    @endif
</form>

<section class="panel" data-widget="telemetry-live">
    <div class="panel-header">
        <h2 class="panel-title">{{ __('ui.telemetry.history') }}</h2>
        <span class="muted">
            <span data-bind="total">{{ $telemetry->total() }}</span>
            <span data-bind="rowsLabel">{{ __('ui.rows') }}</span>
        </span>
        <span
            class="badge-pill badge-info mono{{ $pdr ? '' : ' d-none' }}"
            data-bind="pdr"
        >
            @if ($pdr)
                {{ __('ui.telemetry.pdr') }}:
                {{ number_format($pdr['ratio'] * 100, 1) }}%
                ({{ number_format($pdr['received']) }}/{{ number_format($pdr['expected']) }})
            @endif
        </span>
        <span class="live-badge">{{ __('ui.dashboard.live') }}</span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('ui.telemetry.time') }}</th>
                    <th>{{ __('ui.nav.gateway') }}</th>
                    <th>{{ __('ui.nav.node') }}</th>
                    <th>{{ __('ui.telemetry.seq') }}</th>
                    <th>{{ __('ui.telemetry.metrics') }}</th>
                    <th>{{ __('ui.telemetry.battery') }}</th>
                    <th>RSSI</th>
                    <th>SNR</th>
                </tr>
            </thead>
            <tbody data-bind-rows="telemetry">
                @forelse ($telemetry as $item)
                    <tr>
                        <td>
                            <div class="mono">{{ $item->timestamp }}</div>
                            <div class="muted" style="font-size:0.75rem;">{{ $item->timestamp?->diffForHumans() }}</div>
                        </td>
                        <td class="mono">{{ $item->gateway_id }}</td>
                        <td class="mono">{{ $item->node_id }}</td>
                        <td class="mono">{{ $item->seq ?? '—' }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @forelse ($item->displayMetrics() as $key => $value)
                                    <span class="badge-pill badge-info mono">{{ $key }}: {{ $value }}</span>
                                @empty
                                    <span class="muted">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="mono">{{ $item->battery ?? '—' }}</td>
                        <td class="mono">{{ $item->rssi ?? '—' }}</td>
                        <td class="mono">{{ $item->snr ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <h3>{{ __('ui.telemetry.empty_title') }}</h3>
                                <p class="muted mb-0">{{ __('ui.telemetry.empty_body') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer{{ $telemetry->total() > 0 ? '' : ' d-none' }}" data-bind="footer-wrap">
        <div class="table-footer-meta" data-bind="footer">
            @if ($telemetry->total() > 0)
                {{ __('ui.pagination.showing', [
                    'from' => $telemetry->firstItem(),
                    'to' => $telemetry->lastItem(),
                    'total' => number_format($telemetry->total()),
                ]) }}
            @endif
        </div>
        @if ($telemetry->hasPages())
            <div class="table-footer-links">
                {{ $telemetry->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</section>
</div>
@endsection
