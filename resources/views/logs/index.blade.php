@extends('layouts.app')

@section('title', __('ui.logs.title'))
@section('kicker', __('ui.logs.kicker'))
@section('heading', __('ui.logs.title'))
@section('subtitle', __('ui.logs.subtitle'))

@section('content')
<form class="toolbar panel panel-body" method="GET">
    <input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.logs.search_placeholder') }}">
    <select class="form-select" name="level" style="max-width:180px">
        <option value="">{{ __('ui.logs.all_levels') }}</option>
        <option value="info" @selected(request('level') === 'info')>Info</option>
        <option value="warning" @selected(request('level') === 'warning')>Warning</option>
        <option value="error" @selected(request('level') === 'error')>Error</option>
    </select>
    <button class="btn btn-soft" type="submit">{{ __('ui.filter') }}</button>
    @if (request()->hasAny(['search', 'level']))
        <a href="{{ route('logs.index') }}" class="btn btn-outline-secondary">{{ __('ui.reset') }}</a>
    @endif
</form>

<section class="panel">
    <div class="panel-header">
        <h2 class="panel-title">{{ __('ui.logs.list') }}</h2>
        <span class="muted">{{ $logs->total() }} {{ __('ui.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('ui.logs.time') }}</th>
                    <th>{{ __('ui.nav.gateway') }}</th>
                    <th>{{ __('ui.logs.level') }}</th>
                    <th>{{ __('ui.logs.event') }}</th>
                    <th>{{ __('ui.logs.message') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $levelClass = match ($log->level) {
                            'warning' => 'badge-offline',
                            'error' => 'badge-danger',
                            default => 'badge-info',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="mono">{{ $log->created_at }}</div>
                            <div class="muted" style="font-size:0.75rem;">{{ $log->created_at?->diffForHumans() }}</div>
                        </td>
                        <td class="mono">{{ $log->gateway_id ?? '—' }}</td>
                        <td><span class="badge-pill {{ $levelClass }}">{{ $log->level }}</span></td>
                        <td class="mono">{{ $log->event }}</td>
                        <td>{{ $log->message }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <h3>{{ __('ui.logs.empty_title') }}</h3>
                                <p class="muted mb-0">{{ __('ui.logs.empty_body') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.table-footer', ['paginator' => $logs])
</section>
@endsection
