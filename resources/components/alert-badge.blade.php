{{-- resources/views/components/alert-badge.blade.php --}}
@props(['severity' => 'info', 'count' => 0, 'pulse' => false])

@php
    $colors = match($severity) {
        'critical' => 'bg-red-100 text-red-800 border-red-300',
        'warning' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'info' => 'bg-blue-100 text-blue-800 border-blue-300',
        'debug' => 'bg-gray-100 text-gray-800 border-gray-300',
        default => 'bg-gray-100 text-gray-800 border-gray-300',
    };
    
    $icons = match($severity) {
        'critical' => '🔴',
        'warning' => '🟡',
        'info' => '🔵',
        'debug' => '⚪',
        default => '📊',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border {$colors} " . ($pulse ? 'animate-pulse' : '')]) }}>
    <span>{{ $icons[$severity] ?? '📊' }}</span>
    <span class="uppercase tracking-wider">{{ $severity }}</span>
    @if($count > 0)
        <span class="ml-1 font-bold">{{ number_format($count) }}</span>
    @endif
    {{ $slot }}
</span>