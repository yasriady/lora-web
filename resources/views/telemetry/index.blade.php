@extends('layouts.app')

@section('title', __('ui.telemetry.title'))
@section('kicker', __('ui.telemetry.kicker'))
@section('heading', __('ui.telemetry.title'))
@section('subtitle', __('ui.telemetry.subtitle'))

@section('actions')
<a class="btn btn-outline-secondary" href="{{ route('telemetry.export', request()->query()) }}">{{ __('ui.telemetry.export') }}</a>
@endsection

@section('content')
<form class="card mb-3" method="GET">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md">
                <input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.telemetry.search_placeholder') }}">
            </div>
            <div class="col-md-auto" style="min-width:180px">
                <select class="form-select" name="gateway_id">
                    <option value="">{{ __('ui.telemetry.all_gateways') }}</option>
                    @foreach ($gateways as $gateway)
                        <option value="{{ $gateway->gateway_id }}" @selected(request('gateway_id') === $gateway->gateway_id)>{{ $gateway->gateway_id }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto" style="min-width:180px">
                <select class="form-select" name="node_id">
                    <option value="">{{ __('ui.telemetry.all_nodes') }}</option>
                    @foreach ($nodes as $node)
                        <option value="{{ $node->node_id }}" @selected(request('node_id') === $node->node_id)>{{ $node->node_id }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto" style="min-width:180px">
                <select class="form-select" name="metric_key">
                    <option value="">{{ __('ui.telemetry.all_metrics') }}</option>
                    @foreach ($metricKeys as $metricKey)
                        <option value="{{ $metricKey }}" @selected(request('metric_key') === $metricKey)>{{ $metricKey }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-primary" type="submit">{{ __('ui.filter') }}</button>
                @if (request()->hasAny(['search', 'gateway_id', 'node_id', 'metric_key']))
                    <a href="{{ route('telemetry.index') }}" class="btn btn-ghost-secondary">{{ __('ui.reset') }}</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('ui.telemetry.history') }}</h3>
        <div class="card-actions text-secondary">{{ $telemetry->total() }} {{ __('ui.rows') }}</div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>{{ __('ui.telemetry.time') }}</th>
                    <th>{{ __('ui.nav.gateway') }}</th>
                    <th>{{ __('ui.nav.node') }}</th>
                    <th>{{ __('ui.telemetry.metrics') }}</th>
                    <th>{{ __('ui.telemetry.battery') }}</th>
                    <th>RSSI</th>
                    <th>SNR</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($telemetry as $item)
                    <tr>
                        <td>
                            <div class="mono">{{ $item->timestamp }}</div>
                            <div class="text-secondary small">{{ $item->timestamp?->diffForHumans() }}</div>
                        </td>
                        <td class="mono">{{ $item->gateway_id }}</td>
                        <td class="mono">{{ $item->node_id }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @forelse ($item->displayMetrics() as $key => $value)
                                    <span class="badge bg-azure-lt mono">{{ $key }}: {{ $value }}</span>
                                @empty
                                    <span class="text-secondary">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="mono">{{ $item->battery ?? '—' }}</td>
                        <td class="mono">{{ $item->rssi ?? '—' }}</td>
                        <td class="mono">{{ $item->snr ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <h3>{{ __('ui.telemetry.empty_title') }}</h3>
                                <p class="text-secondary mb-0">{{ __('ui.telemetry.empty_body') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.table-footer', ['paginator' => $telemetry])
</div>
@endsection
