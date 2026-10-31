@extends('layouts.app')

@section('title', 'Sidebar')

@section('content')
    <x-page-header eyebrow="Website content" title="Sidebar"
        subtitle="Manage the links displayed in the public website sidebar.">
        <x-slot:actions>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="arrow-left">Website dashboard</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Sidebar links</h3>
                <p class="card-sub">Add, enable or disable sidebar links.</p>
            </div>
        </div>
        <div class="card-body">
            @include('admin.website._menu-manager', ['menu' => $menu, 'location' => 'sidebar', 'defaultName' => 'Website sidebar'])
        </div>
    </div>
@endsection