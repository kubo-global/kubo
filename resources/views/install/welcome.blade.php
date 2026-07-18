@extends('install._layout')

@section('content')
  @php
    $steps = [
      ['Your school', 'Your school\'s name and country, and a logo if you\'d like one printed on your reports.'],
      ['Year, classes and subjects', 'Your school year and terms, the grades and classes you run, and the subjects you teach. Prefilled for The Gambia, so you just adjust what differs.'],
      ['Your account', 'The administrator login you\'ll use to set up and run KUBO, then add your headmaster, teachers and students.'],
      ['Review and finish', 'A last look at everything before it\'s saved. Nothing is final until you confirm.'],
    ];
  @endphp

  <p class="text-base leading-relaxed text-gray-600">
    Setting up KUBO takes a little configuration, but none of it is hard, and anything you get wrong
    now can be changed later in Settings. Still, it's worth getting right from the start. Here's what we'll do:
  </p>

  <ol class="mt-7 space-y-6">
    @foreach ($steps as $i => [$title, $desc])
      <li class="flex gap-4">
        <span class="flex items-center justify-center flex-shrink-0 text-base font-bold rounded-full w-9 h-9 bg-indigo-100 text-indigo-700">{{ $i + 1 }}</span>
        <div class="pt-0.5">
          <div class="text-lg font-semibold text-gray-900">{{ $title }}</div>
          <div class="text-base leading-relaxed text-gray-600">{{ $desc }}</div>
        </div>
      </li>
    @endforeach
  </ol>

  <div class="flex justify-end mt-8">
    <a href="{{ route('install.school') }}" class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg bg-indigo-600 hover:bg-indigo-700">
      Start setup &rarr;
    </a>
  </div>
@endsection
