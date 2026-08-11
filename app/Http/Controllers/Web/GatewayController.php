<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GatewayController extends Controller
{
    public function index(Request $request): View
    {
        $query = Gateway::query()->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('gateway_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('enabled', $request->string('status')->toString() === 'active');
        }

        return view('gateways.index', [
            'gateways' => $query->paginate(10)->withQueryString(),
            'threshold' => now()->subMinutes(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $token = bin2hex(random_bytes(32));

        Gateway::query()->create($data + [
            'api_token' => hash('sha256', $token),
            'enabled' => true,
        ]);

        return back()
            ->with('success', __('ui.gateway.created'))
            ->with('new_gateway_token', $token);
    }

    public function update(Request $request, Gateway $gateway): RedirectResponse
    {
        $gateway->update($this->validated($request, $gateway));

        return back()->with('success', __('ui.gateway.updated'));
    }

    public function toggle(Gateway $gateway): RedirectResponse
    {
        $gateway->update(['enabled' => ! $gateway->enabled]);

        return back()->with('success', $gateway->enabled ? __('ui.gateway.enabled') : __('ui.gateway.disabled'));
    }

    public function destroy(Gateway $gateway): RedirectResponse
    {
        $gateway->delete();

        return back()->with('success', __('ui.gateway.deleted'));
    }

    /**
     * @return array{gateway_id: string, name: string, location: ?string, description: ?string}
     */
    private function validated(Request $request, ?Gateway $gateway = null): array
    {
        return $request->validate([
            'gateway_id' => [
                'required',
                'string',
                'max:64',
                Rule::unique('gateways', 'gateway_id')->ignore($gateway?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
