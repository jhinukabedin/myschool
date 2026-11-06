@php
    $developer = $printDeveloper ?? [
        'name' => school_setting('report_developer_name', 'EduSphere Development'),
        'website' => school_setting('report_developer_website'),
        'email' => school_setting('report_developer_email'),
        'phone' => school_setting('report_developer_phone'),
    ];
@endphp

<footer class="report-print-footer" aria-label="Report developer information">
    <span>Developed by <strong>{{ $developer['name'] ?: 'EduSphere Development' }}</strong></span>
    <span class="report-print-footer-contact">
        @if (filled($developer['website']))
            <a href="{{ $developer['website'] }}">{{ preg_replace('#^https?://#', '', rtrim($developer['website'], '/')) }}</a>
        @endif
        @if (filled($developer['email']))
            <a href="mailto:{{ $developer['email'] }}">{{ $developer['email'] }}</a>
        @endif
        @if (filled($developer['phone']))
            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $developer['phone']) }}">{{ $developer['phone'] }}</a>
        @endif
    </span>
    <span class="report-print-footer-pages"></span>
</footer>
