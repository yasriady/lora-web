@extends('layouts.app')

@section('title', request()->routeIs('map') ? __('ui.placeholder.map_title') : __('ui.placeholder.settings_title'))
@section('kicker', __('ui.placeholder.kicker'))
@section('heading', request()->routeIs('map') ? __('ui.placeholder.map_title') : __('ui.placeholder.settings_title'))
@section('subtitle', __('ui.placeholder.subtitle'))

@section('content')
<div class="card">
    <div class="card-body empty-state">
        <span class="avatar avatar-lg bg-primary text-white mb-3">L</span>
        <h3>{{ __('ui.placeholder.title') }}</h3>
        <p class="text-secondary mb-0">{{ __('ui.placeholder.body') }}</p>
    </div>
</div>
@endsection
