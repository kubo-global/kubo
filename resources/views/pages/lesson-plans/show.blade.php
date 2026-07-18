<x-page :title="'Lesson plan — ' . $plan->topic" :wrap="true">
    @php
        $user = auth()->user();
        $isOwner = $plan->user_id === $user->id;
        $canEdit = $isOwner || $user->hasAnyRole(['headmaster', 'admin']);
        // The headmaster IS the coordinator — they fill the Coordinator Remarks.
        $canSignAsCoordinator = $user->hasAnyRole(['headmaster', 'admin']);
        $canSignAsAssistant = $user->hasRole('assistant_coordinator') && !$isOwner;
    @endphp

    <div class="space-y-6">
        @if (session()->has('message'))
            <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded p-3">{{ session('message') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500">
                {{ $plan->lesson_date?->format('d M Y') }} — {{ $plan->offering->grade->name ?? '—' }} — {{ $plan->subject->name ?? '—' }}
            </div>
            <div class="space-x-3 text-sm">
                @if ($canEdit)
                    <a href="{{ route('lesson-plans.edit', $plan) }}" class="text-indigo-600 hover:underline">Edit</a>
                @endif
                <a href="{{ route('lesson-plans.index') }}" class="text-gray-600 hover:underline">Back</a>
            </div>
        </div>

        <div class="text-sm text-gray-500">
            Teacher: {{ $plan->teacher->first_name }} {{ $plan->teacher->last_name }}
        </div>

        <h2 class="text-lg font-semibold text-gray-900">{{ $plan->topic }}</h2>

        @if ($plan->curriculum_topic_id)
            <div class="text-sm text-gray-500">
                Curriculum topic:
                <span class="text-gray-900">{{ $plan->curriculumTopic->name ?? '—' }}</span>
            </div>
        @endif

        @foreach (['content' => 'Content', 'objectives' => 'Objectives', 'resources' => 'Resources', 'activities' => 'Activities', 'assessment' => 'Assessment', 'conclusion' => 'Conclusion'] as $field => $label)
            <section>
                <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wider">{{ $label }}</h2>
                <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $plan->{$field} ?: '—' }}</p>
            </section>
        @endforeach

        @include('pages.lesson-plans._remarks-section', [
            'anchor'   => 'assistant-remarks',
            'title'    => 'Assistant coordinator remarks',
            'field'    => 'assistant_coordinator_remarks',
            'value'    => $plan->assistant_coordinator_remarks,
            'signedAt' => $plan->assistant_coordinator_signed_at,
            'level'    => 'assistant',
            'canSign'  => $canSignAsAssistant,
            'border'   => true,
        ])

        @include('pages.lesson-plans._remarks-section', [
            'anchor'   => 'coordinator-remarks',
            'title'    => 'Coordinator remarks',
            'field'    => 'coordinator_remarks',
            'value'    => $plan->coordinator_remarks,
            'signedAt' => $plan->coordinator_signed_at,
            'level'    => 'coordinator',
            'canSign'  => $canSignAsCoordinator,
            'border'   => false,
        ])

        @if ($canEdit)
            <form method="POST" action="{{ route('lesson-plans.destroy', $plan) }}" onsubmit="return confirm('Delete this lesson plan?');" class="border-t pt-6">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm text-red-600 hover:underline">Delete lesson plan</button>
            </form>
        @endif
    </div>
</x-page>
