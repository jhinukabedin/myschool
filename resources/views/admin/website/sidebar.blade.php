@extends('layouts.app')

@section('title', 'Sidebar')

@section('content')
    <x-page-header eyebrow="Website content" title="Sidebar"
        subtitle="Manage the quick links shown in the public inner-page sidebar and the mobile drawer.">
        <x-slot:actions>
            <x-button :href="route('website-menus.index')" variant="secondary" icon="menu">Menu builder</x-button>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="arrow-left">Website dashboard</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ $menu?->name ?? 'Sidebar links' }}</h3>
                <p class="card-sub">
                    @if ($menu)
                        {{ $menu->rootItems->count() }} link(s). These links also feed the mobile drawer.
                    @else
                        No sidebar menu yet. Create one to control the public sidebar instead of the built-in defaults.
                    @endif
                </p>
            </div>
        </div>
        <div class="card-body">
            @include('admin.website._menu-manager', ['menu' => $menu, 'location' => 'sidebar', 'defaultName' => 'Website sidebar'])
        </div>
    </div>
@endsection