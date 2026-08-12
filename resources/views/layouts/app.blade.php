<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.nav.dashboard')) · {{ __('ui.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:ital,wght@0,300;0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/dashboard.css') }}?v=6" rel="stylesheet">
</head>
<body class="app-body skin-blue sidebar-mini">
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="wrapper">
    <header class="main-header">
        <a class="logo" href="{{ route('dashboard') }}">
            <span class="logo-mini">L</span>
            <span class="logo-lg"><b>{{ __('ui.app_name') }}</b></span>
        </a>
        <nav class="navbar navbar-static-top">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="{{ __('ui.menu') }}">
                <span class="sr-only">{{ __('ui.menu') }}</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <div class="navbar-custom-menu">
                <div class="navbar-actions">
                    @yield('actions')
                </div>
                <div class="lang-switch" role="group" aria-label="Language">
                    <a href="{{ route('locale.update', 'id') }}" class="{{ app()->getLocale() === 'id' ? 'active' : '' }}">ID</a>
                    <a href="{{ route('locale.update', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}">EN</a>
                </div>
                @auth
                    <div class="navbar-user">
                        @if (auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar }}" alt="" class="user-image">
                        @else
                            <span class="user-image user-initial">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        @endif
                        <span class="hidden-xs">{{ auth()->user()->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="navbar-logout">
                        @csrf
                        <button class="btn btn-navbar-logout" type="submit">{{ __('ui.logout') }}</button>
                    </form>
                @endauth
            </div>
        </nav>
    </header>

    <aside class="main-sidebar" id="appSidebar">
        <section class="sidebar">
            @auth
                <div class="user-panel">
                    <div class="pull-left image">
                        @if (auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar }}" alt="" class="img-circle">
                        @else
                            <span class="img-circle user-initial">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="pull-left info">
                        <p>{{ auth()->user()->name }}</p>
                        <span class="status-online"><i class="status-dot-inline"></i> Online</span>
                    </div>
                </div>
            @endauth

            <ul class="sidebar-menu">
                <li class="header">{{ __('ui.app_tagline') }}</li>
                @php
                    $items = [
                        ['route' => 'dashboard', 'label' => __('ui.nav.dashboard'), 'match' => 'dashboard', 'path' => 'M4 4h7v7H4V4zm9 0h7v4h-7V4zM4 13h4v7H4v-7zm6 3h10v4H10v-4z'],
                        ['route' => 'gateways.index', 'label' => __('ui.nav.gateway'), 'match' => 'gateways.*', 'path' => 'M12 3c-4.5 3.5-7 7.1-7 10.2A7 7 0 0 0 12 20a7 7 0 0 0 7-6.8C19 10.1 16.5 6.5 12 3zm0 7.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z'],
                        ['route' => 'nodes.index', 'label' => __('ui.nav.node'), 'match' => 'nodes.*', 'path' => 'M12 2l3 5h6l-4.5 4 1.8 6.2L12 14.8 5.7 17.2 7.5 11 3 7h6l3-5z'],
                        ['route' => 'telemetry.index', 'label' => __('ui.nav.telemetry'), 'match' => 'telemetry.*', 'path' => 'M3 17l5-6 4 3 5-7 4 3v2l-4-3-5 7-4-3-5 6H3z'],
                        ['route' => 'map', 'label' => __('ui.nav.map'), 'match' => 'map', 'path' => 'M9 3l6 2 6-2v16l-6 2-6-2-6 2V5l6-2zm0 2.2V17l6 2V7.2L9 5.2z'],
                        ['route' => 'logs.index', 'label' => __('ui.nav.log'), 'match' => 'logs.*', 'path' => 'M5 4h14v2H5V4zm0 5h14v2H5V9zm0 5h10v2H5v-2zm0 5h8v2H5v-2z'],
                        ['route' => 'settings', 'label' => __('ui.nav.settings'), 'match' => 'settings', 'path' => 'M10 2h4l.6 2.3a7 7 0 0 1 1.7.9L18.9 4l2.1 2.1-1.2 2.6c.3.5.6 1.1.8 1.7L23 11v4l-2.4.4a7 7 0 0 1-.8 1.7l1.2 2.6-2.1 2.1-2.6-1.2a7 7 0 0 1-1.7.8L14 23h-4l-.4-2.4a7 7 0 0 1-1.7-.8L5.3 21 3.2 18.9l1.2-2.6a7 7 0 0 1-.8-1.7L1 15v-4l2.4-.4c.2-.6.5-1.2.8-1.7L3.2 6.1 5.3 4l2.6 1.2c.5-.4 1.1-.7 1.7-.9L10 2zm2 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6z'],
                    ];
                @endphp
                @foreach ($items as $item)
                    <li class="{{ request()->routeIs($item['match']) ? 'active' : '' }}">
                        <a href="{{ route($item['route']) }}" class="nav-link-item">
                            <span class="nav-ico">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $item['path'] }}"/></svg>
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <h1>
                @yield('heading')
                @hasSection('subtitle')
                    <small>@yield('subtitle')</small>
                @endif
            </h1>
            <ol class="breadcrumb">
                <li><a href="{{ route('dashboard') }}">{{ __('ui.app_name') }}</a></li>
                <li class="active">@yield('kicker', __('ui.nav.dashboard'))</li>
            </ol>
        </section>

        <section class="content">
            @if (session('success'))
                <div class="flash flash-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="flash flash-error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="flash flash-error">
                    <strong>{{ __('ui.check_input') }}</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (session('new_gateway_token'))
                <div class="flash flash-warning">
                    <strong>{{ __('ui.gateway.token_notice') }}</strong>
                    <div class="token-box">
                        <code id="newGatewayToken">{{ session('new_gateway_token') }}</code>
                        <button type="button" class="btn btn-sm btn-default" data-copy="{{ session('new_gateway_token') }}" data-copied-label="{{ __('ui.copied') }}">{{ __('ui.copy') }}</button>
                    </div>
                </div>
            @endif

            @yield('content')
        </section>
    </div>

    <footer class="main-footer">
        <div class="pull-right hidden-xs"><b>Version</b> 1.0</div>
        <strong>{{ __('ui.app_name') }}</strong> · {{ __('ui.app_tagline') }}
    </footer>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" id="confirmDeleteForm">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title text-danger">{{ __('ui.delete_confirm.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">{!! __('ui.delete_confirm.body', ['item' => '<strong id="confirmDeleteLabel">'.e(__('ui.delete_confirm.item_fallback')).'</strong>']) !!}</p>
                <p class="muted mb-3">{!! __('ui.delete_confirm.hint', ['hapus' => '<code>hapus</code>', 'delete' => '<code>delete</code>']) !!}</p>
                <label class="form-label" for="confirmDeleteInput">{{ __('ui.delete_confirm.label') }}</label>
                <input
                    type="text"
                    class="form-control"
                    id="confirmDeleteInput"
                    autocomplete="off"
                    placeholder="{{ __('ui.delete_confirm.placeholder') }}"
                >
                <div class="form-text text-danger d-none" id="confirmDeleteHint">{{ __('ui.delete_confirm.mismatch') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                <button type="submit" class="btn btn-danger" id="confirmDeleteSubmit" disabled>{{ __('ui.delete_confirm.submit') }}</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/dashboard.js') }}?v=6"></script>
@stack('scripts')
</body>
</html>
