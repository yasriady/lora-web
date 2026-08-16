@php
    $formId = $formId ?? null;
@endphp
<div class="modal modal-blur fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <form class="modal-content" method="POST" action="{{ $action }}" @if($formId) id="{{ $formId }}" @endif>
            @csrf
            @if (($method ?? 'POST') !== 'POST')
                @method($method)
            @endif
            <div class="modal-header">
                <h5 class="modal-title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('ui.node.gateway') }}</label>
                        <select class="form-select" name="gateway_id" required>
                            <option value="">{{ __('ui.node.select_gateway') }}</option>
                            @foreach ($gateways as $gateway)
                                <option value="{{ $gateway->gateway_id }}">{{ $gateway->gateway_id }} — {{ $gateway->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('ui.node.id') }}</label>
                        <input class="form-control mono" name="node_id" required maxlength="64" placeholder="NODE01">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('ui.name') }}</label>
                        <input class="form-control" name="name" required maxlength="120">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('ui.node.type') }}</label>
                        <select class="form-select js-node-type" name="node_type" required>
                            @foreach ($presets as $key => $preset)
                                <option value="{{ $key }}">{{ $preset['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('ui.location') }}</label>
                        <input class="form-control" name="location">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('ui.description') }}</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="col-12 js-schema-wrap">
                        <label class="form-label">{{ __('ui.node.schema_json') }}</label>
                        <textarea class="form-control mono js-schema-json" name="metrics_schema_json" rows="6" placeholder='{"temperature":{"label":"Temperature","unit":"°C","type":"number"}}'></textarea>
                        <div class="form-hint">{{ __('ui.node.schema_help') }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                <button class="btn btn-primary ms-auto" type="submit">{{ ($method ?? 'POST') === 'PUT' ? __('ui.update') : __('ui.save') }}</button>
            </div>
        </form>
    </div>
</div>
