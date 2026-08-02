@props(['name', 'value' => null])
@php($inputId = $attributes->get('id') ?: 'password-'.\Illuminate\Support\Str::uuid())
<div data-password-field class="relative">
    <input
        id="{{ $inputId }}"
        type="password"
        name="{{ $name }}"
        @if(! is_null($value)) value="{{ $value }}" @endif
        {{ $attributes->except('id')->merge(['class' => 'input !pr-12']) }}
    >
    <button
        type="button"
        data-password-toggle
        aria-controls="{{ $inputId }}"
        aria-label="Tampilkan password"
        aria-pressed="false"
        title="Tampilkan password"
        class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-2xl text-slate-400 transition hover:text-violet-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-violet-500/30 dark:hover:text-violet-300"
    >
        <x-icon data-password-icon="show" name="eye" size="size-5" />
        <x-icon data-password-icon="hide" name="eye-off" size="size-5" hidden />
    </button>
</div>
