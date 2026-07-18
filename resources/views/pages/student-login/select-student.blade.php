<x-student-login :heading="$offering->displayName()" subheading="Find your name and type it to log in"
    :back="$back" :back-label="$backLabel">
    <div class="space-y-3" x-data="{ selected: null, typed: '' }">
        @forelse ($students as $student)
            <div class="animate-[fadeSlideUp_0.4s_ease-out_both]" style="animation-delay: {{ $loop->index * 50 }}ms">
                <button type="button"
                    x-on:click="selected = selected === {{ $student->id }} ? null : {{ $student->id }}; typed = ''"
                    class="flex items-center w-full gap-3 px-4 py-3.5 text-base font-semibold text-left transition border shadow-sm text-indigo-900 rounded-xl hover:scale-[1.02] hover:shadow-md bg-white border-white/60"
                    :class="selected === {{ $student->id }} ? 'ring-2 ring-white' : ''">
                    <x-kid-avatar :seed="$student->id" :label="mb_strtoupper(mb_substr($student->first_name, 0, 1))" class="w-10 h-10 text-base" />
                    {{ $student->first_name }} {{ $student->last_name }}
                </button>
                <div x-show="selected === {{ $student->id }}" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="mt-2">
                    <form action="{{ route('student-login.login', $student) }}" class="flex items-center gap-2">
                        <input type="text" x-model="typed" placeholder="Type your first name"
                            class="block w-full px-3 py-2 text-sm border-0 rounded-lg focus:outline-none focus:ring-2 focus:ring-white bg-white/90">
                        <button type="submit"
                            :disabled="typed.trim().toLowerCase() !== '{{ strtolower($student->first_name) }}'"
                            class="px-4 py-2 text-sm font-bold text-indigo-700 transition bg-white rounded-lg"
                            :class="typed.trim().toLowerCase() !== '{{ strtolower($student->first_name) }}' ? 'opacity-30' : 'opacity-100'">
                            Go
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-center text-white/70">No students in this class yet.</p>
        @endforelse
    </div>
</x-student-login>
