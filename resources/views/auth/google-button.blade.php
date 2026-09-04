<x-filament::button :href="route('auth.google')" tag="a" color="gray" outlined icon-position="before">
    <x-slot name="icon">{!! file_get_contents(resource_path('icons/google.svg')) !!}</x-slot>
    {{ __('Sign in with Google') }}
</x-filament::button>
