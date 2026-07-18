@props(['heading', 'subheading' => null, 'back' => null, 'backLabel' => 'Back'])

{{-- Shared chrome for the student-login flow (grade → class → student): the brand
     gradient, logo, heading, frosted card, and the "Teacher login" escape hatch. --}}
<x-page bg-color='bg-[#0f1030]'>
    <a href="{{ route('login') }}"
       style="top: max(1rem, env(safe-area-inset-top, 1rem))"
       class="fixed z-20 inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition border rounded-lg shadow-lg right-4 bg-white/20 hover:bg-white/35 border-white/30 backdrop-blur-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Teacher login
    </a>
    {{-- Hand-drawn nudge so it's obvious the corner button switches to the staff login. --}}
    <div class="fixed z-20 items-end hidden gap-1 select-none sm:flex top-[4.25rem] right-12 text-brand-gold pointer-events-none">
        <span class="mb-3 text-sm italic -rotate-3">Not a student? Click here</span>
        <svg class="w-12 h-16 text-brand-gold/80" viewBox="0 0 50 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 44 C 20 54, 40 48, 41 13"/>
            <path d="M34 20 L 41 13 L 48 20"/>
        </svg>
    </div>

    <div class="relative flex flex-col justify-center min-h-screen px-6 py-12 overflow-hidden bg-[#0f1030]">
        <x-aurora-bg />

        <div class="relative z-10 w-full max-w-md mx-auto text-center animate-[fadeIn_0.5s_ease-out]">
            <svg class="w-auto h-20 mx-auto text-white fill-current drop-shadow" viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg">
                <path d="M228.39 76.13l11.24-6.48L125.94 4 1 76.13 38.9 98v87.53l87 50.24 87-50.24V85l-87 50.26-75.77-43.73 64.57-37.28-11.24-6.48L38.9 85l-15.43-8.87L125.94 17l102.45 59.13zM50.14 104.49l75.8 43.84 75.79-43.84V179l-75.79 43.83L50.14 179v-74.51z"/>
            </svg>
            <h2 class="mt-6 text-3xl font-extrabold tracking-tight text-white">{{ $heading }}</h2>
            @if ($subheading)<p class="mt-2 text-sm text-indigo-100">{{ $subheading }}</p>@endif
        </div>

        <div class="relative z-10 w-full max-w-md mx-auto mt-8">
            <div class="px-4 py-8 border sm:rounded-2xl sm:px-10 bg-white/10 border-white/25 backdrop-blur-sm">
                {{ $slot }}
            </div>
            @if ($back)
                <div class="mt-6 text-center">
                    <a href="{{ $back }}" class="text-sm text-white/70 hover:text-white">&larr; {{ $backLabel }}</a>
                </div>
            @endif
        </div>
    </div>

    <style>
        [x-cloak] { display: none; }
        @keyframes fadeSlideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</x-page>
