<x-student-login heading="What grade are you in?">
    <div class="space-y-3">
        @forelse ($grades as $grade)
            @php($short = collect(explode(' ', $grade->name))->map(fn ($w) => ctype_digit($w) ? $w : mb_substr($w, 0, 1))->implode(''))
            <a href="{{ route('student-login.select-class', $grade) }}"
               class="flex items-center w-full gap-3 px-4 py-3.5 text-base font-bold transition border shadow-sm text-indigo-900 rounded-xl hover:scale-[1.02] hover:shadow-md animate-[fadeSlideUp_0.4s_ease-out_both] bg-white border-white/60"
               style="animation-delay: {{ $loop->index * 50 }}ms">
                <x-kid-avatar :label="$short" :bg="$grade->colorTriplet()[2]" class="w-9 h-9 text-sm" />
                {{ $grade->name }}
            </a>
        @empty
            <p class="text-center text-white/70">No classes are set up yet.</p>
        @endforelse
    </div>
</x-student-login>
