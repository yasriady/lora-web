@extends('layouts.app')
@section('content')
<h1 class="h3 mb-4">Dashboard</h1><div class="row g-3">@foreach(['Gateway'=>$gatewayCount,'Node'=>$nodeCount,'Node Online'=>$onlineNodeCount,'Node Offline'=>$offlineNodeCount,'Packet Hari Ini'=>$todayPacketCount,'Packet Total'=>$packetCount] as $label=>$value)<div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">{{ $label }}</div><div class="display-6">{{ number_format($value) }}</div></div></div></div>@endforeach</div>
@endsection
