@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-medium text-[#7A6E5E] tracking-wide uppercase mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
