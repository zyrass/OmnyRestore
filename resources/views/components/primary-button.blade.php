<button {{ $attributes->merge(['type' => 'submit', 'class' => 'w-full inline-flex items-center justify-center gap-2 px-6 py-3.5
    bg-[#C9A84C] hover:bg-[#E8C97A] text-[#0D0B08] font-semibold text-sm tracking-wide rounded-sm
    transition-all duration-300 hover:shadow-[0_0_25px_rgba(201,168,76,0.35)] hover:-translate-y-px
    focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/50 focus:ring-offset-2 focus:ring-offset-[#0D0B08]
    disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0']) }}>
    {{ $slot }}
</button>
