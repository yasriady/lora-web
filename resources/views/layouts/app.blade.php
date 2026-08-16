<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.nav.dashboard')) · {{ __('ui.app_name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css">
    <link rel="stylesheet" href="{{ asset('css/app-tabler.css') }}?v={{ filemtime(public_path('css/app-tabler.css')) }}">
</head>
<body>
@php
    $navItems = [
        ['route' => 'dashboard', 'label' => __('ui.nav.dashboard'), 'match' => 'dashboard', 'icon' => 'M4 4h7v7H4V4zm9 0h7v4h-7V4zM4 13h4v7H4v-7zm6 3h10v4H10v-4z'],
        ['route' => 'gateways.index', 'label' => __('ui.nav.gateway'), 'match' => 'gateways.*', 'icon' => 'M12 3c-4.5 3.5-7 7.1-7 10.2A7 7 0 0 0 12 20a7 7 0 0 0 7-6.8C19 10.1 16.5 6.5 12 3zm0 7.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z'],
        ['route' => 'nodes.index', 'label' => __('ui.nav.node'), 'match' => 'nodes.*', 'icon' => 'M12 2l3 5h6l-4.5 4 1.8 6.2L12 14.8 5.7 17.2 7.5 11 3 7h6l3-5z'],
        ['route' => 'telemetry.index', 'label' => __('ui.nav.telemetry'), 'match' => 'telemetry.*', 'icon' => 'M3 17l5-6 4 3 5-7 4 3v2l-4-3-5 7-4-3-5 6H3z'],
        ['route' => 'map', 'label' => __('ui.nav.map'), 'match' => 'map', 'icon' => 'M9 3l6 2 6-2v16l-6 2-6-2-6 2V5l6-2zm0 2.2V17l6 2V7.2L9 5.2z'],
        ['route' => 'logs.index', 'label' => __('ui.nav.log'), 'match' => 'logs.*', 'icon' => 'M5 4h14v2H5V4zm0 5h14v2H5V9zm0 5h10v2H5v-2zm0 5h8v2H5v-2z'],
        ['route' => 'settings', 'label' => __('ui.nav.settings'), 'match' => 'settings', 'icon' => 'M10 2h4l.6 2.3a7 7 0 0 1 1.7.9L18.9 4l2.1 2.1-1.2 2.6c.3.5.6 1.1.8 1.7L23 11v4l-2.4.4a7 7 0 0 1-.8 1.7l1.2 2.6-2.1 2.1-2.6-1.2a7 7 0 0 1-1.7.8L14 23h-4l-.4-2.4a7 7 0 0 1-1.7-.8L5.3 21 3.2 18.9l1.2-2.6a7 7 0 0 1-.8-1.7L1 15v-4l2.4-.4c.2-.6.5-1.2.8-1.7L3.2 6.1 5.3 4l2.6 1.2c.5-.4 1.1-.7 1.7-.9L10 2zm2 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6z'],
    ];
@endphp
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="{{ __('ui.menu') }}">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="{{ route('dashboard') }}">
                    <span class="avatar avatar-sm bg-primary text-white me-2">L</span>
                    <span>{{ __('ui.app_name') }}</span>
                </a>
            </h1>
            <div class="navbar-nav flex-row d-lg-none">
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
                        @auth
                            @if (auth()->user()->avatar)
                                <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->avatar }})"></span>
                            @else
                                <span class="avatar avatar-sm">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            @endif
                        @endauth
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <div class="dropdown-header">
                            @auth
                                <div class="fw-bold">{{ auth()->user()->name }}</div>
                                <div class="text-secondary small">{{ auth()->user()->email }}</div>
                            @endauth
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('locale.update', 'id') }}">Bahasa ID @if(app()->getLocale() === 'id')✓@endif</a>
                        <a class="dropdown-item" href="{{ route('locale.update', 'en') }}">English EN @if(app()->getLocale() === 'en')✓@endif</a>
                        @auth
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger" type="submit">{{ __('ui.logout') }}</button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-3">
                    <li class="nav-item">
                        <span class="nav-link disabled text-secondary small px-3">{{ __('ui.app_tagline') }}</span>
                    </li>
                    @foreach ($navItems as $item)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $item['icon'] }}"/></svg>
                                </span>
                                <span class="nav-link-title">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-auto mb-3 d-none d-lg-block px-3">
                    @auth
                        <div class="d-flex align-items-center gap-2 mb-2">
                            @if (auth()->user()->avatar)
                                <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->avatar }})"></span>
                            @else
                                <span class="avatar avatar-sm">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            @endif
                            <div class="lh-sm overflow-hidden">
                                <div class="text-truncate fw-medium">{{ auth()->user()->name }}</div>
                                <div class="text-secondary small text-truncate">{{ auth()->user()->email }}</div>
                            </div>
                        </div>
                        <div class="btn-group w-100 mb-2" role="group" aria-label="Language">
                            <a href="{{ route('locale.update', 'id') }}" class="btn btn-sm {{ app()->getLocale() === 'id' ? 'btn-primary' : 'btn-outline-secondary' }}">ID</a>
                            <a href="{{ route('locale.update', 'en') }}" class="btn btn-sm {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">EN</a>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-outline-secondary w-100 btn-sm" type="submit">{{ __('ui.logout') }}</button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">@yield('kicker', __('ui.app_name'))</div>
                        <h2 class="page-title">@yield('heading')</h2>
                        @hasSection('subtitle')
                            <div class="text-secondary mt-1">@yield('subtitle')</div>
                        @endif
                    </div>
                    <div class="col-auto ms-auto d-print-none">
                        <div class="btn-list">
                            @yield('actions')
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <div>{{ session('success') }}</div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div>{{ session('error') }}</div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div>
                            <strong>{{ __('ui.check_input') }}</strong>
                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif
                @if (session('new_gateway_token'))
                    <div class="alert alert-warning alert-dismissible" role="alert">
                        <div>
                            <strong>{{ __('ui.gateway.token_notice') }}</strong>
                            <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                                <code id="newGatewayToken" class="user-select-all">{{ session('new_gateway_token') }}</code>
                                <button type="button" class="btn btn-sm btn-outline-warning" data-copy="{{ session('new_gateway_token') }}" data-copied-label="{{ __('ui.copied') }}">{{ __('ui.copy') }}</button>
                            </div>
                        </div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>

        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-secondary align-items-center">
                    <div class="col-auto">
                        <strong>{{ __('ui.app_name') }}</strong> · {{ __('ui.app_tagline') }}
                    </div>
                    <div class="col-auto ms-auto">
                        <b>{{ __('ui.version') }}</b> {{ \App\Support\AppRevision::version() }}
                        @if ($revision = \App\Support\AppRevision::describe())
                            · {{ $revision }}
                        @endif
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>

<div class="modal modal-blur fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <form class="modal-content" method="POST" id="confirmDeleteForm">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title text-danger">{{ __('ui.delete_confirm.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">{!! __('ui.delete_confirm.body', ['item' => '<strong id="confirmDeleteLabel">'.e(__('ui.delete_confirm.item_fallback')).'</strong>']) !!}</p>
                <p class="text-secondary mb-3">{!! __('ui.delete_confirm.hint', ['hapus' => '<code>hapus</code>', 'delete' => '<code>delete</code>']) !!}</p>
                <label class="form-label" for="confirmDeleteInput">{{ __('ui.delete_confirm.label') }}</label>
                <input
                    type="text"
                    class="form-control"
                    id="confirmDeleteInput"
                    autocomplete="off"
                    placeholder="{{ __('ui.delete_confirm.placeholder') }}"
                >
                <div class="form-hint text-danger d-none" id="confirmDeleteHint">{{ __('ui.delete_confirm.mismatch') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                <button type="submit" class="btn btn-danger ms-auto" id="confirmDeleteSubmit" disabled>{{ __('ui.delete_confirm.submit') }}</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
@stack('pre-scripts')
<script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
@stack('scripts')
</body>
</html>
