@extends('layouts.app')

@section('title', __('ui.gateway.title'))
@section('kicker', __('ui.gateway.kicker'))
@section('heading', __('ui.gateway.title'))
@section('subtitle', __('ui.gateway.subtitle'))

@section('actions')
<button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#createGatewayModal">{{ __('ui.gateway.add') }}</button>
@endsection

@section('content')
<form class="toolbar panel panel-body" method="GET">
    <input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.gateway.search_placeholder') }}">
    <select class="form-select" name="status" style="max-width:180px">
        <option value="">{{ __('ui.all_status') }}</option>
        <option value="active" @selected(request('status') === 'active')>{{ __('ui.active') }}</option>
        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('ui.inactive') }}</option>
    </select>
    <button class="btn btn-soft" type="submit">{{ __('ui.filter') }}</button>
    @if (request()->hasAny(['search', 'status']))
        <a href="{{ route('gateways.index') }}" class="btn btn-outline-secondary">{{ __('ui.reset') }}</a>
    @endif
</form>

<section class="panel">
    <div class="panel-header">
        <h2 class="panel-title">{{ __('ui.gateway.list') }}</h2>
        <span class="muted">{{ $gateways->total() }} {{ __('ui.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('ui.nav.gateway') }}</th>
                    <th>{{ __('ui.location') }}</th>
                    <th>{{ __('ui.status') }}</th>
                    <th>{{ __('ui.last_seen') }}</th>
                    <th>{{ __('ui.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($gateways as $gateway)
                    @php
                        $online = $gateway->enabled && $gateway->last_seen && $gateway->last_seen->gte($threshold);
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $gateway->name }}</div>
                            <div class="mono muted">{{ $gateway->gateway_id }}</div>
                            @if ($gateway->description)
                                <div class="muted" style="font-size:0.78rem;">{{ \Illuminate\Support\Str::limit($gateway->description, 60) }}</div>
                            @endif
                        </td>
                        <td>{{ $gateway->location ?: '—' }}</td>
                        <td>
                            @if (! $gateway->enabled)
                                <span class="badge-pill badge-disabled">{{ __('ui.disabled') }}</span>
                            @elseif ($online)
                                <span class="badge-pill badge-online">{{ __('ui.online') }}</span>
                            @else
                                <span class="badge-pill badge-offline">{{ __('ui.offline') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($gateway->last_seen)
                                <div>{{ $gateway->last_seen->diffForHumans() }}</div>
                                <div class="mono muted" style="font-size:0.75rem;">{{ $gateway->last_seen }}</div>
                            @else
                                <span class="muted">{{ __('ui.never') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-group">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editGatewayModal"
                                    data-action="{{ route('gateways.update', $gateway) }}"
                                    data-fill-edit="{{ base64_encode(json_encode([
                                        'gateway_id' => $gateway->gateway_id,
                                        'name' => $gateway->name,
                                        'location' => $gateway->location,
                                        'description' => $gateway->description,
                                    ], JSON_UNESCAPED_UNICODE)) }}"
                                >{{ __('ui.edit') }}</button>
                                <form method="POST" action="{{ route('gateways.toggle', $gateway) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-soft" type="submit">{{ $gateway->enabled ? __('ui.disable') : __('ui.enable') }}</button>
                                </form>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmDeleteModal"
                                    data-delete-action="{{ route('gateways.destroy', $gateway) }}"
                                    data-delete-label="{{ __('ui.gateway.delete_label', ['id' => $gateway->gateway_id, 'name' => $gateway->name]) }}"
                                >{{ __('ui.delete') }}</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <h3>{{ __('ui.gateway.empty_title') }}</h3>
                                <p class="muted mb-3">{{ __('ui.gateway.empty_body') }}</p>
                                <button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#createGatewayModal">{{ __('ui.gateway.add') }}</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.table-footer', ['paginator' => $gateways])
</section>

<div class="modal fade" id="createGatewayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('gateways.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('ui.gateway.create_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('ui.gateway.id') }}</label>
                    <input class="form-control mono" name="gateway_id" value="{{ old('gateway_id') }}" required maxlength="64" placeholder="GW001">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('ui.name') }}</label>
                    <input class="form-control" name="name" value="{{ old('name') }}" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('ui.location') }}</label>
                    <input class="form-control" name="location" value="{{ old('location') }}">
                </div>
                <div class="mb-0">
                    <label class="form-label">{{ __('ui.description') }}</label>
                    <textarea class="form-control" name="description" rows="3">{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                <button class="btn btn-brand" type="submit">{{ __('ui.save') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editGatewayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" id="editGatewayForm">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">{{ __('ui.gateway.edit_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('ui.gateway.id') }}</label>
                    <input class="form-control mono" name="gateway_id" required maxlength="64">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('ui.name') }}</label>
                    <input class="form-control" name="name" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('ui.location') }}</label>
                    <input class="form-control" name="location">
                </div>
                <div class="mb-0">
                    <label class="form-label">{{ __('ui.description') }}</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                <button class="btn btn-brand" type="submit">{{ __('ui.update') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
