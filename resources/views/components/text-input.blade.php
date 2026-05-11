@props(['disabled' => false])

<input
    {{ $disabled ? 'disabled' : '' }}
    {!! $attributes->merge([
        'class' => 'w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-4 py-3
                   placeholder-[#7A6E5E]/50
                   focus:outline-none focus:border-[#C9A84C]/60 focus:ring-1 focus:ring-[#C9A84C]/30
                   hover:border-[#C9A84C]/35
                   transition-all duration-200
                   disabled:opacity-40 disabled:cursor-not-allowed'
    ]) !!}
>
