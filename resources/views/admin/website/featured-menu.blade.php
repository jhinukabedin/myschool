@extends('layouts.app')

@section('title', 'Featured Menu')

@section('content')
    <x-page-header eyebrow="Website content" title="Featured Menu"
        subtitle="Buttons shown in the homepage hero featured area. Only enabled items appear on the public site.">
        <x-slot:actions>
            <x-button :href="route('website-admin.hero')" variant="secondary" icon="arrow-left">Hero Section</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>


    @php $featuredCount = $menu ? $menu->items()->count() : 0; @endphp

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Featured items</h3>
                <p class="card-sub">Add an icon, menu title, sub title and link. Up to 6 buttons are allowed and each fills one tile of the homepage grid.</p>
            </div>
            <x-badge color="violet">{{ $featuredCount }} / 6</x-badge>
        </div>
        <div class="card-body">
            @include('admin.website._featured-menu', ['menu' => $menu])
        </div>
    </div>
@endsection