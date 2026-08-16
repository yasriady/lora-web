@extends('layouts.app')

@section('title', __('ui.logs.title'))
@section('kicker', __('ui.logs.kicker'))
@section('heading', __('ui.logs.title'))
@section('subtitle', __('ui.logs.subtitle'))

@section('content')
<form class="card mb-3" method="GET">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md">
                <input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.logs.search_placeholder') }}">
            </div>
            <div class="col-md-auto" style="min-width:180px">
                <select class="form-select" name="level">
                    <option value="">{{ __('ui.logs.all_levels') }}</option>
                    <option value="info" @selected(request('level') === 'info')>Info</option>
                    <option value="warning" @selected(request('level') === 'warning')>Warning</option>
                    <option value="error" @selected(request('level') === 'error')>Error</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-primary" type="submit">{{ __('ui.filter') }}</button>
                @if (request()->hasAny(['search', 'level']))
                    <a href="{{ route('logs.index') }}" class="btn btn-ghost-secondary">{{ __('ui.reset') }}</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('ui.logs.list') }}</h3>
        <div class="card-actions text-secondary">{{ $logs->total() }} {{ __('ui.total') }}</div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
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
                            'warning' => 'bg-warning-lt',
                            'error' => 'bg-danger-lt',
                            default => 'bg-azure-lt',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="mono">{{ $log->created_at }}</div>
                            <div class="text-secondary small">{{ $log->created_at?->diffForHumans() }}</div>
                        </td>
                        <td class="mono">{{ $log->gateway_id ?? '—' }}</td>
                        <td><span class="badge {{ $levelClass }}">{{ $log->level }}</span></td>
                        <td class="mono">{{ $log->event }}</td>
                        <td>{{ $log->message }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <h3>{{ __('ui.logs.empty_title') }}</h3>
                                <p class="text-secondary mb-0">{{ __('ui.logs.empty_body') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.table-footer', ['paginator' => $logs])
</div>
@endsection
