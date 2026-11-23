@props(['tone' => 'neutral'])

@php
    $allowedTones = ['neutral', 'success', 'info', 'warning', 'danger'];
    $tone = in_array($tone, $allowedTones, true) ? $tone : 'neutral';
@endphp

<span {{ $attributes->merge(['class' => 'admin-badge admin-badge--' . $tone]) }}>
    {{ $slot }}
</span>
