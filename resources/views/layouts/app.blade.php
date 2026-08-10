<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>LoRa Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('dashboard') }}">LoRa Monitor</a>
        <div class="navbar-nav me-auto">
            <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="nav-link" href="{{ route('gateways.index') }}">Gateway</a>
            <a class="nav-link" href="{{ route('nodes.index') }}">Node</a>
            <a class="nav-link" href="{{ route('telemetry.index') }}">Telemetry</a>
            <a class="nav-link" href="{{ route('map') }}">Map</a>
            <a class="nav-link" href="{{ route('logs.index') }}">Log</a>
            <a class="nav-link" href="{{ route('settings') }}">Setting</a>
        </div>
        @auth
            <div class="d-flex align-items-center gap-3 text-white">
                @if (auth()->user()->avatar)
                    <img src="{{ auth()->user()->avatar }}" alt="" width="28" height="28" class="rounded-circle">
                @endif
                <span class="small">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-outline-light" type="submit">Logout</button>
                </form>
            </div>
        @endauth
    </div>
</nav>
<main class="container pb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('new_gateway_token'))
        <div class="alert alert-warning">
            <strong>Simpan token ini; tidak dapat ditampilkan lagi.</strong>
            <code class="d-block text-break mt-2">{{ session('new_gateway_token') }}</code>
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
