@extends('install._layout')

@section('content')
  <h2 class="text-lg font-bold text-gray-900">Features</h2>
  <p class="mt-1 text-sm text-gray-500">Turn on the parts of KUBO your school will use. You can switch these on or off any time in Settings.</p>

  <form method="POST" action="{{ route('install.features.store') }}" class="mt-6 space-y-3">
    @csrf
    @php $prior = old('modules', $selected); @endphp
    @foreach ($modules as $m)
      @php $checked = $prior !== null ? in_array($m['slug'], (array) $prior) : $m['default']; @endphp
      <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
        <input type="checkbox" name="modules[]" value="{{ $m['slug'] }}" @checked($checked)
          class="w-4 h-4 mt-0.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
        <span class="flex-1">
          <span class="text-sm font-medium text-gray-900">{{ $m['label'] }}</span>@if ($m['beta'])<span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700">Beta</span>@endif
          <span class="block text-xs text-gray-500">{{ $m['description'] }}</span>
        </span>
      </label>
    @endforeach

    <div class="flex items-center justify-between pt-2">
      <a href="{{ route('install.classes') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">&larr; Back</a>
      <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg bg-indigo-600 hover:bg-indigo-700">Continue &rarr;</button>
    </div>
  </form>

  <x-install.help>
    These are optional parts of KUBO. Health tracks student health reports and milestones, leave it off if
    your school won't use it. Progress and Lesson Plans add analytics and lesson planning. Core things like
    Students and Grades are always on. You can turn any of these on or off later in Settings.
  </x-install.help>
@endsection
