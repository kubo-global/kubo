@extends('install._layout')

@section('content')
  <h2 class="text-lg font-bold text-gray-900">Almost there</h2>
  <p class="mt-1 text-sm text-gray-500">Have a quick look over everything, then we'll get it all set up for you.</p>

  @php
    $gradeList = $gradeClasses !== null
      ? collect($gradeClasses)->map(fn ($c, $g) => $c > 1 ? $g.' ('.$c.' classes)' : $g)->values()->all()
      : ($preset ? $preset['grades'] : $manualGrades);
    $subjectList = $selectedSubjects ?? ($preset ? $preset['subjects'] : []);
  @endphp

  <dl class="mt-6 space-y-4 text-sm divide-y divide-gray-100">
    <div class="flex justify-between gap-4 pt-0">
      <dt class="font-medium text-gray-500">School</dt>
      <dd class="font-semibold text-right text-gray-900">{{ $school['name'] }}<br><span class="text-xs font-normal text-gray-500">{{ $countryName }}</span></dd>
    </div>
    <div class="flex justify-between gap-4 pt-4">
      <dt class="font-medium text-gray-500">School year</dt>
      <dd class="text-right text-gray-900">{{ $structure['name'] }}<br><span class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($structure['start'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($structure['end'])->format('d M Y') }}</span></dd>
    </div>
    <div class="flex justify-between gap-4 pt-4">
      <dt class="font-medium text-gray-500">Terms</dt>
      <dd class="text-right text-gray-900 text-xs">
        @foreach ($terms as $t)
          <div>{{ $t['name'] }} <span class="text-gray-500">{{ \Illuminate\Support\Carbon::parse($t['start'])->format('d M') }} – {{ \Illuminate\Support\Carbon::parse($t['end'])->format('d M') }}</span></div>
        @endforeach
      </dd>
    </div>
    <div class="flex justify-between gap-4 pt-4">
      <dt class="font-medium text-gray-500">Classes</dt>
      <dd class="text-right text-gray-900">{{ count($gradeList) ? implode(', ', $gradeList) : 'None yet — add later' }}</dd>
    </div>
    @if (count($subjectList))
      <div class="flex justify-between gap-4 pt-4">
        <dt class="font-medium text-gray-500">Subjects</dt>
        <dd class="text-right text-gray-900">{{ count($subjectList) }} subjects<br><span class="text-xs font-normal text-gray-500">{{ implode(', ', $subjectList) }}{{ $preset && !empty($preset['nat']) ? ' · NAT ('.implode(', ', array_keys($preset['nat'])).')' : '' }}</span></dd>
      </div>
    @endif
    <div class="flex justify-between gap-4 pt-4">
      <dt class="font-medium text-gray-500">Administrator</dt>
      <dd class="text-right text-gray-900">{{ $admin['first_name'] }} {{ $admin['last_name'] }}@if (!empty($admin['email']))<br><span class="text-xs text-gray-500">{{ $admin['email'] }}</span>@endif</dd>
    </div>
  </dl>

  <form method="POST" action="{{ route('install.complete') }}" class="mt-7">
    @csrf
    <div class="flex items-center justify-between">
      <a href="{{ route('install.admin') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">&larr; Back</a>
      <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg bg-emerald-600 hover:bg-emerald-700">
        Create my school &amp; sign in
      </button>
    </div>
  </form>
@endsection
