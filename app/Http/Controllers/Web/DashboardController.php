<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\GatewayLog;
use App\Models\Node;
use App\Models\Telemetry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $threshold = now()->subMinutes(15);

        return view('dashboard.index', [
            'gatewayCount' => Gateway::query()->count(),
            'nodeCount' => Node::query()->count(),
            'onlineNodeCount' => Node::query()->where('last_seen', '>=', $threshold)->count(),
            'offlineNodeCount' => Node::query()->where(fn ($q) => $q->whereNull('last_seen')->orWhere('last_seen', '<', $threshold))->count(),
            'todayPacketCount' => Telemetry::query()->whereDate('timestamp', today())->count(),
            'packetCount' => Telemetry::query()->count(),
        ]);
    }

    public function gateways(): View
    {
        return view('gateways.index', ['gateways' => Gateway::query()->latest()->paginate(20)]);
    }

    public function storeGateway(Request $request): RedirectResponse
    {
        $data = $request->validate(['gateway_id' => ['required', 'string', 'max:64', 'unique:gateways'], 'name' => ['required', 'string', 'max:120'], 'location' => ['nullable', 'string'], 'description' => ['nullable', 'string']]);
        $token = bin2hex(random_bytes(32));
        Gateway::query()->create($data + ['api_token' => hash('sha256', $token), 'enabled' => true]);

        return back()->with('success', 'Gateway dibuat. Simpan token berikut sekarang.')->with('new_gateway_token', $token);
    }

    public function nodes(): View
    {
        return view('nodes.index', ['nodes' => Node::query()->latest()->paginate(20), 'gateways' => Gateway::query()->orderBy('gateway_id')->get()]);
    }

    public function storeNode(Request $request): RedirectResponse
    {
        $data = $request->validate(['gateway_id' => ['required', 'exists:gateways,gateway_id'], 'node_id' => ['required', 'string', 'max:64', 'unique:nodes'], 'name' => ['required', 'string', 'max:120'], 'location' => ['nullable', 'string'], 'description' => ['nullable', 'string']]);
        Node::query()->create($data + ['enabled' => true]);

        return back()->with('success', 'Node dibuat.');
    }

    public function telemetry(Request $request): View
    {
        $query = Telemetry::query()->latest('timestamp');
        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q->where('gateway_id', 'like', "%{$search}%")->orWhere('node_id', 'like', "%{$search}%"));
        }
        return view('telemetry.index', ['telemetry' => $query->paginate(30)->withQueryString()]);
    }

    public function logs(): View
    {
        return view('logs.index', ['logs' => GatewayLog::query()->latest('created_at')->paginate(50)]);
    }
}
