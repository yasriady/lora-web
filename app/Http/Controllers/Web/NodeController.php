<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\Node;
use App\Support\NodeMetricCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NodeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Node::query()->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('node_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('gateway_id', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('node_type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('gateway_id')) {
            $query->where('gateway_id', $request->string('gateway_id')->toString());
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            $threshold = now()->subMinutes(15);

            if ($status === 'active') {
                $query->where('enabled', true);
            } elseif ($status === 'disabled') {
                $query->where('enabled', false);
            } elseif ($status === 'online') {
                $query->where('enabled', true)->where('last_seen', '>=', $threshold);
            } elseif ($status === 'offline') {
                $query->where(function ($builder) use ($threshold): void {
                    $builder->where('enabled', false)
                        ->orWhereNull('last_seen')
                        ->orWhere('last_seen', '<', $threshold);
                });
            }
        }

        return view('nodes.index', [
            'nodes' => $query->paginate(10)->withQueryString(),
            'gateways' => Gateway::query()->orderBy('gateway_id')->get(),
            'threshold' => now()->subMinutes(15),
            'presets' => NodeMetricCatalog::presets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Node::query()->create($this->validated($request) + ['enabled' => true]);

        return back()->with('success', __('ui.node.created'));
    }

    public function update(Request $request, Node $node): RedirectResponse
    {
        $node->update($this->validated($request, $node));

        return back()->with('success', __('ui.node.updated'));
    }

    public function toggle(Node $node): RedirectResponse
    {
        $node->update(['enabled' => ! $node->enabled]);

        return back()->with('success', $node->enabled ? __('ui.node.enabled') : __('ui.node.disabled'));
    }

    public function destroy(Node $node): RedirectResponse
    {
        $node->delete();

        return back()->with('success', __('ui.node.deleted'));
    }

    /**
     * @return array{
     *     gateway_id: string,
     *     node_id: string,
     *     name: string,
     *     node_type: string,
     *     metrics_schema: ?array,
     *     location: ?string,
     *     description: ?string
     * }
     */
    private function validated(Request $request, ?Node $node = null): array
    {
        $presets = NodeMetricCatalog::presets();
        $data = $request->validate([
            'gateway_id' => ['required', 'exists:gateways,gateway_id'],
            'node_id' => [
                'required',
                'string',
                'max:64',
                Rule::unique('nodes', 'node_id')->ignore($node?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'node_type' => ['required', 'string', Rule::in(array_keys($presets))],
            'metrics_schema_json' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $schema = $presets[$data['node_type']]['schema'] ?? [];

        if ($data['node_type'] === 'custom' && filled($data['metrics_schema_json'] ?? null)) {
            $decoded = json_decode((string) $data['metrics_schema_json'], true);
            if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'metrics_schema_json' => __('ui.node.schema_invalid'),
                ]);
            }
            $schema = $decoded;
        } elseif ($data['node_type'] === 'custom') {
            $schema = [];
        }

        unset($data['metrics_schema_json']);
        $data['metrics_schema'] = $schema;

        return $data;
    }
}
