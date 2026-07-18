@extends('install._layout')

@section('content')
  <h2 class="text-lg font-bold text-gray-900">Classes and subjects</h2>
  <p class="mt-1 text-sm text-gray-500">Which grades your school runs, how many classes each, and the subjects you teach. All editable later.</p>

  <form method="POST" action="{{ route('install.classes.store') }}" class="mt-6 space-y-6">
    @csrf

    @if ($preset)
      {{-- Grades: tick the ones the school runs, and set how many classes each has. --}}
      <fieldset>
        <legend class="text-sm font-medium text-gray-700">Grades</legend>
        <p class="mt-1 text-xs text-gray-500">Untick any grade you don't have. If a grade runs more than one class, set the number (they become A, B, C…).</p>
        @php $priorGrades = old('grades', $gradeClasses !== null ? array_keys($gradeClasses) : null); @endphp
        <div class="mt-3 space-y-2">
          @foreach ($preset['grades'] as $g)
            @php
              $checked = $priorGrades !== null ? in_array($g, (array) $priorGrades) : true;
              $count = old('classes.'.$g, $gradeClasses[$g] ?? 1);
            @endphp
            <div class="flex items-center justify-between gap-3">
              <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="grades[]" value="{{ $g }}" @checked($checked)
                  class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                {{ $g }}
              </label>
              <label class="flex items-center gap-1.5 text-xs text-gray-500">
                classes
                <input type="number" name="classes[{{ $g }}]" min="1" max="12" value="{{ $count }}"
                  class="w-16 px-2 py-1 text-sm text-gray-900 border border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
              </label>
            </div>
          @endforeach
        </div>
        @if (!empty($preset['nat']))
          <p class="mt-3 text-xs text-gray-500">The National Assessment Test is set up for {{ implode(' and ', array_keys($preset['nat'])) }}.</p>
        @endif
      </fieldset>

      {{-- Subjects --}}
      <fieldset>
        <input type="hidden" name="subjects_present" value="1">
        <legend class="text-sm font-medium text-gray-700">Subjects</legend>
        <p class="mt-1 text-xs text-gray-500">Tick the subjects your school teaches. Most public schools leave French and Health off, tick them if you offer them.</p>
        @php $priorSubjects = old('subjects', $selectedSubjects); @endphp
        <div class="grid grid-cols-1 mt-3 gap-x-4 gap-y-2 sm:grid-cols-2">
          @foreach ($preset['subjects'] as $subj)
            @php $checked = $priorSubjects !== null ? in_array($subj, (array) $priorSubjects) : ! in_array($subj, $preset['optional_subjects'] ?? []); @endphp
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" name="subjects[]" value="{{ $subj }}" @checked($checked)
                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
              {{ $subj }}
            </label>
          @endforeach
        </div>
        <div class="mt-4">
          <label for="custom_subjects" class="block text-sm font-medium text-gray-700">Add your own <span class="font-normal text-gray-500">(optional)</span></label>
          <textarea id="custom_subjects" name="custom_subjects" rows="2" placeholder="One subject per line"
            class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old('custom_subjects') }}</textarea>
        </div>
      </fieldset>
    @else
      <div>
        <label for="manual_grades" class="block text-sm font-medium text-gray-700">Classes <span class="font-normal text-gray-500">(optional)</span></label>
        <textarea id="manual_grades" name="manual_grades" rows="5" placeholder="One class per line, e.g.&#10;Grade 1&#10;Grade 2&#10;Grade 3"
          class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old('manual_grades', $manualGrades) }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Add your classes now, or leave blank and add them later. Subjects are set up in Settings.</p>
      </div>
    @endif

    <div class="flex items-center justify-between pt-2">
      <a href="{{ route('install.structure') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">&larr; Back</a>
      <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg bg-indigo-600 hover:bg-indigo-700">Continue &rarr;</button>
    </div>
  </form>

  <x-install.help>
    Tick the grades your school runs and set how many classes each has (two classes of Grade 1 become
    Grade 1 A and Grade 1 B). Then tick the subjects you teach. You can add, rename or remove any grade,
    class or subject later in Settings, and set which subjects count toward the total there too.
  </x-install.help>
@endsection
