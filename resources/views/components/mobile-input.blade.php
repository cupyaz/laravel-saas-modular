@props([
    'type' => 'text',
    'name' => '',
    'label' => '',
    'placeholder' => '',
    'value' => '',
    'required' => false,
    'disabled' => false,
    'autocomplete' => '',
    'inputmode' => '',
    'pattern' => '',
    'minlength' => '',
    'maxlength' => '',
    'min' => '',
    'max' => '',
    'step' => '',
    'hint' => '',
    'error' => '',
    'icon' => null,
    'suffix' => null
])

@php
    $inputId = $name . '_' . Str::random(8);
    $hasError = !empty($error) || $errors->has($name);
    $errorMessage = !empty($error) ? $error : $errors->first($name);
    
    // Set appropriate inputmode for mobile keyboards
    $inputmodeMap = [
        'email' => 'email',
        'tel' => 'tel',
        'number' => 'numeric',
        'url' => 'url',
        'search' => 'search'
    ];
    
    if (empty($inputmode) && isset($inputmodeMap[$type])) {
        $inputmode = $inputmodeMap[$type];
    }
@endphp

<div class="space-y-2">
    <!-- Label -->
    @if($label)
    <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if($required)
        <span class="text-red-500 ml-1">*</span>
        @endif
    </label>
    @endif

    <!-- Input Container -->
    <div class="relative">
        <!-- Icon -->
        @if($icon)
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $icon !!}
            </svg>
        </div>
        @endif

        <!-- Input Field -->
        @if($type === 'textarea')
        <textarea
            id="{{ $inputId }}"
            name="{{ $name }}"
            class="textarea {{ $hasError ? 'input-error' : '' }} {{ $icon ? 'pl-10' : '' }} {{ $suffix ? 'pr-12' : '' }}"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if($inputmode) inputmode="{{ $inputmode }}" @endif
            @if($minlength) minlength="{{ $minlength }}" @endif
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            rows="4"
        >{{ old($name, $value) }}</textarea>
        @elseif($type === 'select')
        <select
            id="{{ $inputId }}"
            name="{{ $name }}"
            class="select {{ $hasError ? 'input-error' : '' }} {{ $icon ? 'pl-10' : '' }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        >
            {{ $slot }}
        </select>
        @else
        <input
            type="{{ $type }}"
            id="{{ $inputId }}"
            name="{{ $name }}"
            class="input {{ $hasError ? 'input-error' : '' }} {{ $icon ? 'pl-10' : '' }} {{ $suffix ? 'pr-12' : '' }}"
            placeholder="{{ $placeholder }}"
            value="{{ old($name, $value) }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if($inputmode) inputmode="{{ $inputmode }}" @endif
            @if($pattern) pattern="{{ $pattern }}" @endif
            @if($minlength) minlength="{{ $minlength }}" @endif
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            @if($min) min="{{ $min }}" @endif
            @if($max) max="{{ $max }}" @endif
            @if($step) step="{{ $step }}" @endif
        >
        @endif

        <!-- Suffix -->
        @if($suffix)
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
            <span class="text-gray-500 text-sm">{{ $suffix }}</span>
        </div>
        @endif
    </div>

    <!-- Hint Text -->
    @if($hint && !$hasError)
    <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif

    <!-- Error Message -->
    @if($hasError)
    <p class="text-xs text-red-600 flex items-center">
        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        {{ $errorMessage }}
    </p>
    @endif
</div>