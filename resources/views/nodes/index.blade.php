@extends('layouts.app')

@section('title', __('ui.node.title'))
@section('kicker', __('ui.node.kicker'))
@section('heading', __('ui.node.title'))
@section('subtitle', __('ui.node.subtitle'))

@section('actions')
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNodeModal" @disabled($gateways->isEmpty())>{{ __('ui.node.add') }}</button>
@endsection

@section('content')
@if ($gateways->isEmpty())
    <div class="alert alert-warning" role="alert">{{ __('ui.node.need_gateway') }}</div>
@endif

<form class="card mb-3" method="GET">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md">
                <input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.node.search_placeholder') }}">
            </div>
            <div class="col-md-auto" style="min-width:200px">
                <select class="form-select" name="gateway_id">
                    <option value="">{{ __('ui.node.all_gateways') }}</option>
                    @foreach ($gateways as $gateway)
                        <option value="{{ $gateway->gateway_id }}" @selected(request('gateway_id') === $gateway->gateway_id)>{{ $gateway->gateway_id }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto" style="min-width:180px">
                <select class="form-select" name="status">
                    <option value="">{{ __('ui.all_status') }}</option>
                    <option value="online" @selected(request('status') === 'online')>{{ __('ui.online') }}</option>
                    <option value="offline" @selected(request('status') === 'offline')>{{ __('ui.offline') }}</option>
                    <option value="active" @selected(request('status') === 'active')>{{ __('ui.active') }}</option>
                    <option value="disabled" @selected(request('status') === 'disabled')>{{ __('ui.disabled') }}</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-primary" type="submit">{{ __('ui.filter') }}</button>
                @if (request()->hasAny(['search', 'gateway_id', 'status']))
                    <a href="{{ route('nodes.index') }}" class="btn btn-ghost-secondary">{{ __('ui.reset') }}</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('ui.node.list') }}</h3>
        <div class="card-actions text-secondary">{{ $nodes->total() }} {{ __('ui.total') }}</div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>{{ __('ui.nav.node') }}</th>
                    <th>{{ __('ui.nav.gateway') }}</th>
                    <th>{{ __('ui.node.type') }}</th>
                    <th>{{ __('ui.location') }}</th>
                    <th>{{ __('ui.status') }}</th>
                    <th>{{ __('ui.last_seen') }}</th>
                    <th>{{ __('ui.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($nodes as $node)
                    @php
                        $online = $node->enabled && $node->last_seen && $node->last_seen->gte($threshold);
                        $typeLabel = $presets[$node->node_type]['label'] ?? $node->node_type;
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $node->name }}</div>
                            <div class="mono text-secondary">{{ $node->node_id }}</div>
                        </td>
                        <td class="mono">{{ $node->gateway_id }}</td>
                        <td>
                            <div>{{ $typeLabel }}</div>
                            <div class="mono text-secondary small">{{ implode(', ', array_keys($node->resolvedMetricsSchema())) ?: 'any' }}</div>
                        </td>
                        <td>{{ $node->location ?: '—' }}</td>
                        <td>
                            @if (! $node->enabled)
                                <span class="badge bg-secondary-lt">{{ __('ui.disabled') }}</span>
                            @elseif ($online)
                                <span class="badge bg-success-lt">{{ __('ui.online') }}</span>
                            @else
                                <span class="badge bg-warning-lt">{{ __('ui.offline') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($node->last_seen)
                                <div>{{ $node->last_seen->diffForHumans() }}</div>
                                <div class="mono text-secondary small">{{ $node->last_seen }}</div>
                            @else
                                <span class="text-secondary">{{ __('ui.never') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-group">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editNodeModal"
                                    data-action="{{ route('nodes.update', $node) }}"
                                    data-fill-edit="{{ base64_encode(json_encode([
                                        'gateway_id' => $node->gateway_id,
                                        'node_id' => $node->node_id,
                                        'name' => $node->name,
                                        'node_type' => $node->node_type,
                                        'metrics_schema_json' => json_encode($node->metrics_schema ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                                        'location' => $node->location,
                                        'description' => $node->description,
                                    ], JSON_UNESCAPED_UNICODE)) }}"
                                >{{ __('ui.edit') }}</button>
                                <form method="POST" action="{{ route('nodes.toggle', $node) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-ghost-secondary" type="submit">{{ $node->enabled ? __('ui.disable') : __('ui.enable') }}</button>
                                </form>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmDeleteModal"
                                    data-delete-action="{{ route('nodes.destroy', $node) }}"
                                    data-delete-label="{{ __('ui.node.delete_label', ['id' => $node->node_id, 'name' => $node->name]) }}"
                                >{{ __('ui.delete') }}</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <h3>{{ __('ui.node.empty_title') }}</h3>
                                <p class="text-secondary mb-3">{{ __('ui.node.empty_body') }}</p>
                                @unless ($gateways->isEmpty())
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNodeModal">{{ __('ui.node.add') }}</button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.table-footer', ['paginator' => $nodes])
</div>

@include('nodes._form-modal', [
    'id' => 'createNodeModal',
    'title' => __('ui.node.create_title'),
    'action' => route('nodes.store'),
    'method' => 'POST',
    'gateways' => $gateways,
    'presets' => $presets,
])

@include('nodes._form-modal', [
    'id' => 'editNodeModal',
    'title' => __('ui.node.edit_title'),
    'action' => '#',
    'method' => 'PUT',
    'gateways' => $gateways,
    'presets' => $presets,
    'formId' => 'editNodeForm',
])
@endsection
