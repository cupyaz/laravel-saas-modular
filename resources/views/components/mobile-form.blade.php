@props([
    'action' => '',
    'method' => 'POST',
    'title' => '',
    'subtitle' => '',
    'submitText' => 'Submit',
    'cancelUrl' => null,
    'loading' => false,
    'autocomplete' => 'on'
])

<div class="min-h-screen bg-gray-50">
    <!-- Form Header -->
    @if($title || $subtitle)
    <div class="bg-white border-b border-gray-200 px-4 py-6 ios-safe-area-top">
        <div class="max-w-md mx-auto">
            @if($title)
            <h1 class="text-xl font-semibold text-gray-900 mb-1">{{ $title }}</h1>
            @endif
            
            @if($subtitle)
            <p class="text-sm text-gray-600">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @endif

    <!-- Form Container -->
    <div class="p-4 ios-safe-area-bottom">
        <div class="max-w-md mx-auto">
            <form 
                action="{{ $action }}" 
                method="{{ $method === 'GET' ? 'GET' : 'POST' }}" 
                class="space-y-6"
                autocomplete="{{ $autocomplete }}"
                novalidate
            >
                @if($method !== 'GET' && $method !== 'POST')
                    @method($method)
                @endif
                
                @if($method !== 'GET')
                    @csrf
                @endif

                <!-- Form Fields -->
                <div class="space-y-4">
                    {{ $slot }}
                </div>

                <!-- Form Actions -->
                <div class="flex flex-col space-y-3 pt-6">
                    <button 
                        type="submit" 
                        class="btn btn-primary btn-lg w-full"
                        @if($loading) disabled @endif
                    >
                        @if($loading)
                            <div class="spinner mr-2"></div>
                            Processing...
                        @else
                            {{ $submitText }}
                        @endif
                    </button>

                    @if($cancelUrl)
                    <a 
                        href="{{ $cancelUrl }}" 
                        class="btn btn-ghost btn-lg w-full"
                    >
                        Cancel
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>