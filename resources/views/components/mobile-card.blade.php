@props([
    'title' => '',
    'subtitle' => '',
    'action' => null,
    'actionText' => '',
    'actionUrl' => '',
    'padding' => 'default', // 'none', 'sm', 'default', 'lg'
    'shadow' => 'default', // 'none', 'sm', 'default', 'lg'
    'clickable' => false,
    'href' => null
])

@php
    $paddingClasses = [
        'none' => '',
        'sm' => 'p-3 md:p-4',
        'default' => 'p-4 md:p-6',
        'lg' => 'p-6 md:p-8'
    ];
    
    $shadowClasses = [
        'none' => 'border border-gray-200',
        'sm' => 'shadow-sm border border-gray-200',
        'default' => 'shadow-sm border border-gray-200',
        'lg' => 'shadow-md border border-gray-200'
    ];
    
    $baseClasses = 'card bg-white rounded-lg';
    $containerClasses = $baseClasses . ' ' . ($shadowClasses[$shadow] ?? $shadowClasses['default']);
    
    if ($clickable || $href) {
        $containerClasses .= ' transform transition-all duration-200 active:scale-98 touch-manipulation';
    }
@endphp

@if($href)
<a href="{{ $href }}" class="{{ $containerClasses }} block hover:shadow-md">
@else
<div class="{{ $containerClasses }} {{ $clickable ? 'cursor-pointer hover:shadow-md' : '' }}">
@endif

    <!-- Card Header -->
    @if($title || $subtitle || $action)
    <div class="flex items-start justify-between {{ $paddingClasses[$padding] ?? $paddingClasses['default'] }} {{ $slot->isNotEmpty() ? 'border-b border-gray-100 pb-4 mb-4' : '' }}">
        <div class="flex-1">
            @if($title)
            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ $title }}</h3>
            @endif
            
            @if($subtitle)
            <p class="text-sm text-gray-600">{{ $subtitle }}</p>
            @endif
        </div>
        
        @if($action && $actionText && $actionUrl)
        <div class="ml-4">
            <a href="{{ $actionUrl }}" class="text-sm text-primary-600 hover:text-primary-700 font-medium touch-feedback">
                {{ $actionText }}
            </a>
        </div>
        @elseif($action)
        <div class="ml-4">
            {!! $action !!}
        </div>
        @endif
    </div>
    @endif

    <!-- Card Content -->
    @if($slot->isNotEmpty())
    <div class="{{ ($title || $subtitle || $action) ? '' : ($paddingClasses[$padding] ?? $paddingClasses['default']) }}">
        {{ $slot }}
    </div>
    @endif

@if($href)
</a>
@else
</div>
@endif