<x-page :title="'Promote students — ' . $schoolyear->name">
  <div class="mx-6 py-8">
    <h2 class="text-lg font-medium text-gray-900">Promote students to {{ $schoolyear->name }}</h2>
    <p class="mt-1 text-sm text-gray-500 mb-6">
      Review each student from {{ $previousYear->name }}. Default is promote to next grade.
    </p>

    <form method="POST" action="{{ route('rollover.process-promotions', $schoolyear) }}">
      @csrf

      @foreach($previousOfferings as $offering)
      @if($offering->students->isEmpty()) @continue @endif

      @php
        $currentGradeId = $offering->grade_id;
        $nextGradeId = $nextGradeMap[$currentGradeId] ?? null;
        $isHighestGrade = ($currentGradeId === $highestGradeId);

        // Sections for next grade (promote) and current grade (repeat).
        $promoteSections = $nextGradeId ? ($newOfferingsByGrade[$nextGradeId] ?? collect()) : collect();
        $repeatSections = $newOfferingsByGrade[$currentGradeId] ?? collect();

        // Default the chosen offering: if the student's current section
        // name exists in the destination grade, prefer that (continuity
        // for A → A, B → B). Otherwise first section in the destination.
        $defaultPromoteId = $promoteSections
            ->firstWhere('name', $offering->name)?->id ?? $promoteSections->first()?->id;
        $defaultRepeatId = $repeatSections
            ->firstWhere('name', $offering->name)?->id ?? $repeatSections->first()?->id;
      @endphp

      <div class="mb-8">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">
          {{ $offering->displayName() }}
          <span class="font-normal text-gray-500">— {{ $offering->students->count() }} students</span>
          @if($nextGradeId)
            <span class="font-normal text-gray-500">→ {{ $grades->firstWhere('id', $nextGradeId)?->name }}</span>
          @else
            <span class="font-normal text-gray-500">→ Graduating</span>
          @endif
        </h3>

        <div class="overflow-x-auto border border-gray-200 rounded-lg shadow">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Promote</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Repeat</th>
                @if($isHighestGrade)
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Graduate</th>
                @endif
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Left school</th>
                @if($promoteSections->count() > 1 || $repeatSections->count() > 1)
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Section</th>
                @endif
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              @foreach($offering->students as $student)
              @php $done = in_array($student->id, $alreadyEnrolled); @endphp
              <tr class="{{ $done ? 'bg-green-50 opacity-60' : ($loop->even ? 'bg-white' : 'bg-gray-50') }}">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                  {{ $student->first_name }} {{ $student->last_name }}
                  @if($done)
                  <span class="text-xs text-green-600 ml-2">already enrolled</span>
                  @endif
                </td>

                @if($done)
                <td colspan="{{ $isHighestGrade ? 4 : 3 }}" class="px-4 py-3 text-center text-xs text-green-600">Done</td>
                @else
                  {{-- Promote/repeat each target a different offering. Emit both;
                       controller picks based on the chosen action. When the
                       destination grade has one section the input is hidden;
                       otherwise it becomes a small dropdown that admin can override.
                       Defaults try to preserve section continuity (A → A, B → B). --}}

                  @if($isHighestGrade)
                  {{-- Highest grade: default to graduate --}}
                  @php $studentName = $student->first_name.' '.$student->last_name; @endphp
                  <td class="px-4 py-3 text-center">
                    <input type="radio" name="action[{{ $student->id }}]" value="promote" aria-label="Promote {{ $studentName }}" class="h-4 w-4 text-gray-900 border-gray-300" disabled>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <input type="radio" name="action[{{ $student->id }}]" value="repeat" aria-label="Repeat {{ $studentName }}" class="h-4 w-4 text-gray-900 border-gray-300">
                  </td>
                  <td class="px-4 py-3 text-center">
                    <input type="radio" name="action[{{ $student->id }}]" value="graduated" checked aria-label="Graduate {{ $studentName }}" class="h-4 w-4 text-gray-900 border-gray-300">
                  </td>
                  <td class="px-4 py-3 text-center">
                    <input type="radio" name="action[{{ $student->id }}]" value="left" aria-label="Mark {{ $studentName }} as left" class="h-4 w-4 text-gray-900 border-gray-300">
                  </td>
                  @else
                  {{-- Normal grade: default to promote --}}
                  @php $studentName = $student->first_name.' '.$student->last_name; @endphp
                  <td class="px-4 py-3 text-center">
                    <input type="radio" name="action[{{ $student->id }}]" value="promote" checked aria-label="Promote {{ $studentName }}" class="h-4 w-4 text-gray-900 border-gray-300">
                  </td>
                  <td class="px-4 py-3 text-center">
                    <input type="radio" name="action[{{ $student->id }}]" value="repeat" aria-label="Repeat {{ $studentName }}" class="h-4 w-4 text-gray-900 border-gray-300">
                  </td>
                  <td class="px-4 py-3 text-center">
                    <input type="radio" name="action[{{ $student->id }}]" value="left" aria-label="Mark {{ $studentName }} as left" class="h-4 w-4 text-gray-900 border-gray-300">
                  </td>
                  @endif

                  @if($promoteSections->count() > 1 || $repeatSections->count() > 1)
                  <td class="px-4 py-3 text-xs">
                    @if($promoteSections->count() > 1)
                      <select name="promote_offering[{{ $student->id }}]" aria-label="Promotion section for {{ $student->first_name }} {{ $student->last_name }}" class="px-2 py-1 text-xs border border-gray-300 rounded-md">
                        @foreach ($promoteSections as $s)
                          <option value="{{ $s->id }}" @selected($s->id === $defaultPromoteId)>{{ $s->displayName() }}</option>
                        @endforeach
                      </select>
                    @elseif($defaultPromoteId)
                      <input type="hidden" name="promote_offering[{{ $student->id }}]" value="{{ $defaultPromoteId }}">
                    @endif
                    @if($repeatSections->count() > 1)
                      <select name="repeat_offering[{{ $student->id }}]" aria-label="Repeat section for {{ $student->first_name }} {{ $student->last_name }}" class="px-2 py-1 text-xs border border-gray-300 rounded-md mt-1" title="Section if repeating">
                        @foreach ($repeatSections as $s)
                          <option value="{{ $s->id }}" @selected($s->id === $defaultRepeatId)>{{ $s->displayName() }} (repeat)</option>
                        @endforeach
                      </select>
                    @elseif($defaultRepeatId)
                      <input type="hidden" name="repeat_offering[{{ $student->id }}]" value="{{ $defaultRepeatId }}">
                    @endif
                  </td>
                  @else
                    @if($defaultPromoteId)
                      <input type="hidden" name="promote_offering[{{ $student->id }}]" value="{{ $defaultPromoteId }}">
                    @endif
                    @if($defaultRepeatId)
                      <input type="hidden" name="repeat_offering[{{ $student->id }}]" value="{{ $defaultRepeatId }}">
                    @endif
                  @endif
                @endif
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endforeach

      <div class="flex items-center justify-between pt-4 border-t border-gray-200">
        <a href="{{ route('rollover.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
        <button type="submit"
          class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
          Process promotions
        </button>
      </div>
    </form>
  </div>
</x-page>
