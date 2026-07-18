<x-page title="Lesson Plans" :wrap="true">
    @php
        $user = auth()->user();
        $isCoordinator = $user->hasAnyRole(['headmaster', 'admin']);
        $isAssistant = $user->hasRole('assistant_coordinator');
        $hasFilters = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    @if (session()->has('message'))
        <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded p-3">{{ session('message') }}</div>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        {{-- Filters --}}
        <form method="GET" action="{{ route('lesson-plans.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="space-y-1">
                <label for="offering_id" class="block text-xs font-medium text-gray-500">Class</label>
                <select id="offering_id" name="offering_id" class="py-1.5 pl-2 pr-8 text-sm border border-gray-300 rounded-md">
                    <option value="">All classes</option>
                    @foreach ($offerings as $offering)
                        <option value="{{ $offering->id }}" @selected($filters['offering_id'] == $offering->id)>{{ $offering->displayName() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label for="subject_id" class="block text-xs font-medium text-gray-500">Subject</label>
                <select id="subject_id" name="subject_id" class="py-1.5 pl-2 pr-8 text-sm border border-gray-300 rounded-md">
                    <option value="">All subjects</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected($filters['subject_id'] == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($canFilterTeacher)
                <div class="space-y-1">
                    <label for="teacher_id" class="block text-xs font-medium text-gray-500">Teacher</label>
                    <select id="teacher_id" name="teacher_id" class="py-1.5 pl-2 pr-8 text-sm border border-gray-300 rounded-md">
                        <option value="">All teachers</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] == $teacher->id)>{{ $teacher->first_name }} {{ $teacher->last_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="space-y-1">
                <label for="status" class="block text-xs font-medium text-gray-500">Sign-off</label>
                <select id="status" name="status" class="py-1.5 pl-2 pr-8 text-sm border border-gray-300 rounded-md">
                    @foreach (['' => 'Any status', 'unsigned' => 'Not signed', 'awaiting_coordinator' => 'Awaiting coordinator', 'assistant_signed' => 'Assistant signed', 'coordinator_signed' => 'Coordinator signed'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] == $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Filter</button>
            @if ($hasFilters)
                <a href="{{ route('lesson-plans.index') }}" class="px-2 py-1.5 text-sm text-gray-600 underline">Clear</a>
            @endif
        </form>

        @hasanyrole('headmaster|admin|teacher')
            <a href="{{ route('lesson-plans.create') }}"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
                New lesson plan
            </a>
        @endhasanyrole
    </div>

    @if ($plans->isEmpty())
        <p class="text-sm text-gray-500">{{ $hasFilters ? 'No lesson plans match these filters.' : 'No lesson plans yet.' }}</p>
    @else
        <div class="overflow-x-auto bg-white shadow ring-1 ring-black ring-opacity-5 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Topic</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assistant coordinator</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coordinator</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($plans as $plan)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">
                                <a href="{{ route('lesson-plans.show', $plan) }}" class="text-indigo-600 hover:underline">
                                    {{ $plan->lesson_date?->format('d M Y') }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $plan->offering?->displayName() ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $plan->subject->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $plan->topic }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{{ $plan->teacher->first_name }} {{ $plan->teacher->last_name }}</td>

                            {{-- Assistant coordinator: sign-off status (check when signed, else a Sign off button) + the remark below --}}
                            <td class="px-4 py-3 text-sm text-gray-600 align-top">
                                @include('pages.lesson-plans._signoff-cell', [
                                    'signedAt' => $plan->assistant_coordinator_signed_at,
                                    'remark'   => $plan->assistant_coordinator_remarks,
                                    'canSign'  => $isAssistant && $plan->user_id !== $user->id,
                                    'level'    => 'assistant',
                                ])
                            </td>

                            {{-- Coordinator: sign-off status (check when signed, else a Sign off button) + the remark below --}}
                            <td class="px-4 py-3 text-sm text-gray-600 align-top">
                                @include('pages.lesson-plans._signoff-cell', [
                                    'signedAt' => $plan->coordinator_signed_at,
                                    'remark'   => $plan->coordinator_remarks,
                                    'canSign'  => $isCoordinator,
                                    'level'    => 'coordinator',
                                ])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $plans->links() }}</div>
    @endif
</x-page>
