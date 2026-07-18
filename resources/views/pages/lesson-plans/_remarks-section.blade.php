{{-- Lesson-plan remarks + sign-off (coordinator / assistant coordinator).
     Remarks and sign-off are SEPARATE: you can leave remarks without signing off.
     Expects: $anchor, $title, $field, $value, $signedAt, $level, $canSign, $border,
     and $plan in scope. --}}
<section id="{{ $anchor }}" @class(['pt-6', 'border-t' => $border ?? false])
         x-data="{ editing: {{ $value && !$errors->has($field) ? 'false' : 'true' }} }">
    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wider">{{ $title }}</h2>

    {{-- Remarks (optional, independent of sign-off) --}}
    @if ($canSign)
        @if ($value)
            <div x-show="!editing" x-cloak class="mt-2">
                <div class="p-3 text-sm text-gray-900 rounded-md bg-gray-50 ring-1 ring-gray-200 whitespace-pre-line">{{ $value }}</div>
                <button type="button" @click="editing = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 mt-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                    <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.379-8.379-2.828-2.828z" /></svg>
                    Edit remarks
                </button>
            </div>
        @endif
        <form x-show="editing" x-cloak method="POST" action="{{ route('lesson-plans.update', $plan) }}" class="mt-2">
            @csrf @method('PUT')
            <textarea name="{{ $field }}" rows="3" placeholder="Optional remarks…"
                class="w-full py-2 pl-2 text-sm border-2 border-gray-200 rounded-md form-input">{{ old($field, $value) }}</textarea>
            <div class="flex items-center gap-3 mt-2">
                <button type="submit" class="px-3 py-1 text-sm text-white bg-gray-800 rounded-md hover:bg-gray-900">Save remarks</button>
                @if ($value)
                    <button type="button" @click="editing = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                @endif
            </div>
        </form>
    @else
        @if ($value)
            <div class="p-3 mt-2 text-sm text-gray-900 rounded-md bg-gray-50 ring-1 ring-gray-200 whitespace-pre-line">{{ $value }}</div>
        @else
            <p class="mt-2 text-sm italic text-gray-500">No remarks.</p>
        @endif
    @endif

    {{-- Sign-off: explicit, separate from remarks, shown with a check symbol --}}
    <div class="flex items-center gap-3 mt-3">
        @if ($signedAt)
            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-900">
                <svg class="w-5 h-5 text-gray-900" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                Signed off
                <span class="font-normal text-gray-500">· {{ $signedAt->format('d M Y') }}</span>
            </span>
            @if ($canSign)
                <form method="POST" action="{{ route('lesson-plans.sign-off', $plan) }}">
                    @csrf
                    <input type="hidden" name="level" value="{{ $level }}">
                    <input type="hidden" name="signed" value="0">
                    <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700">Undo</button>
                </form>
            @endif
        @elseif ($canSign)
            <form method="POST" action="{{ route('lesson-plans.sign-off', $plan) }}">
                @csrf
                <input type="hidden" name="level" value="{{ $level }}">
                <input type="hidden" name="signed" value="1">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    Sign off
                </button>
            </form>
        @else
            <span class="inline-flex items-center gap-1.5 text-sm text-gray-400">
                <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5" /></svg>
                Not signed off
            </span>
        @endif
    </div>
</section>
