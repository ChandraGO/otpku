@props(['name', 'value' => null])
<div x-data="{ visible: false }" class="relative">
    <input
        :type="visible ? 'text' : 'password'"
        name="{{ $name }}"
        @if(! is_null($value)) value="{{ $value }}" @endif
        {{ $attributes->merge(['class' => 'input !pr-12']) }}
    >
    <button
        type="button"
        @click="visible = ! visible"
        :aria-label="visible ? 'Sembunyikan password' : 'Tampilkan password'"
        :title="visible ? 'Sembunyikan password' : 'Tampilkan password'"
        class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-2xl text-slate-400 transition hover:text-violet-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-violet-500/30 dark:hover:text-violet-300"
    >
        <x-icon x-show="! visible" name="eye" size="size-5" />
        <x-icon x-show="visible" x-cloak name="eye-off" size="size-5" />
    </button>
</div>
