<x-student-login heading="Which class?" :subheading="$grade->name"
    :back="route('student-login.select-grade')" back-label="Back to grades">
    <div class="space-y-3">
        @foreach ($offerings as $offering)
            <a href="{{ route('student-login.select-student', $offering) }}"
               class="flex items-center w-full gap-3 px-4 py-3.5 text-base font-bold transition border shadow-sm text-indigo-900 rounded-xl hover:scale-[1.02] hover:shadow-md animate-[fadeSlideUp_0.4s_ease-out_both] bg-white border-white/60"
               style="animation-delay: {{ $loop->index * 50 }}ms">
                <x-kid-avatar :label="$offering->name ?: '•'" :bg="$offering->grade->colorTriplet()[2]" class="w-9 h-9 text-sm" />
                {{ $offering->displayName() }}
            </a>
        @endforeach
    </div>
</x-student-login>
