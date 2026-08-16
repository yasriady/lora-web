@extends('layouts.app')

@section('title', request()->routeIs('map') ? __('ui.placeholder.map_title') : __('ui.placeholder.settings_title'))
@section('kicker', __('ui.placeholder.kicker'))
@section('heading', request()->routeIs('map') ? __('ui.placeholder.map_title') : __('ui.placeholder.settings_title'))
@section('subtitle', __('ui.placeholder.subtitle'))

@section('content')
<section class="panel">
    <div class="panel-header">
        <h2 class="panel-title">@yield('heading')</h2>
    </div>
    <div class="empty-state">
        <div class="brand-mark" style="margin:0 auto;">L</div>
        <h3>{{ __('ui.placeholder.title') }}</h3>
        <p class="muted mb-0">{{ __('ui.placeholder.body') }}</p>
    </div>
</section>
@endsection
