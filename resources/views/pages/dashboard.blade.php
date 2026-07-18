<x-page :title="'Dashboard'" :wrap="true">

  {{-- ============================ STUDENT ============================ --}}
  @hasanyrole('student')
  @unless(auth()->user()->hasAnyRole(['teacher','headmaster','admin']))
    <div class="grid grid-cols-1 gap-4">
      @include('components.card',[
        'title' => 'Learn',
        'route' => 'learn.index',
        'illustration' => asset('illustrations/graduate.svg'),
      ])
    </div>
  @endunless
  @endhasanyrole

  {{-- ====================== TEACHER / ADMIN ========================= --}}
  @hasanyrole('headmaster|admin|teacher')

  {{-- Greeting + where-are-we --}}
  <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Welcome back, {{ auth()->user()->first_name }}</h1>
      @if($year)
        <p class="mt-1 text-sm text-gray-500">
          {{ $year->name }}
          @if($term)
            <span class="mx-1 text-gray-500">&middot;</span>
            {{ $term->name }}
            @if($term->isLocked())
              <span title="This term has ended and is locked — only the headmaster can still change scores."
                class="inline-flex items-center gap-1.5 ml-1 text-gray-500">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> scores locked
              </span>
            @else
              <span title="Teachers can still enter and edit test/exam scores for this term."
                class="inline-flex items-center gap-1.5 ml-1 text-gray-500">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> open for scores
              </span>
            @endif
          @endif
        </p>
      @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
    @can('view medical records')
      @if(($openFollowUps ?? 0) > 0)
        <a href="{{ route('health.index') }}"
          class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium bg-white border rounded-lg border-indigo-200 text-indigo-700 hover:bg-indigo-50">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
          {{ $openFollowUps }} health {{ \Illuminate\Support\Str::plural('follow-up', $openFollowUps) }}
        </a>
      @endif
    @endcan
    <a href="{{ route('backup.index') }}"
      class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium bg-white border rounded-lg
        {{ \App\Models\BackupLog::isOverdue(7) ? (\App\Models\BackupLog::lastSuccessful() ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-red-200 text-red-700 hover:bg-red-50') : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
      @if(\App\Models\BackupLog::isOverdue(7))
        {{ \App\Models\BackupLog::lastSuccessful() ? 'Backup overdue — '.\App\Models\BackupLog::lastSuccessful()->created_at->diffForHumans() : 'No backups yet — back up your data' }}
      @else
        Backed up {{ \App\Models\BackupLog::lastSuccessful()?->created_at->diffForHumans() }}
      @endif
    </a>
    </div>
  </div>

  {{-- Year-end nudge: the once-a-year rollover, surfaced only when due --}}
  @if($rolloverDue ?? false)
    <a href="{{ route('rollover.index') }}" class="flex flex-wrap items-center justify-between gap-3 p-4 mb-6 transition border border-indigo-200 rounded-xl bg-indigo-50 hover:bg-indigo-100">
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center text-indigo-600 bg-white rounded-lg w-9 h-9 shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </span>
        <div>
          <p class="text-sm font-semibold text-indigo-900">{{ $year->name }} is ending soon</p>
          <p class="text-xs text-indigo-700">Start the new school year, KUBO creates it, carries classes over and promotes students.</p>
        </div>
      </div>
      <span class="text-sm font-medium text-indigo-700">Start now &rarr;</span>
    </a>
  @endif

  {{-- Headline numbers --}}
  @if($overview)
  <div class="grid grid-cols-2 gap-4 mb-8 lg:grid-cols-4">
    @php
      $stats = [
        ['label' => $overview['isAdmin'] ? 'Students' : 'My students', 'value' => $overview['students'], 'route' => 'students.index'],
        ['label' => $overview['isAdmin'] ? 'Classes' : 'My classes', 'value' => $overview['classes'], 'route' => 'reporting.grades'],
      ];
      if ($overview['teachers'] !== null) $stats[] = ['label' => 'Teachers', 'value' => $overview['teachers'], 'route' => 'users.index'];
      if ($overview['subjects'] !== null) $stats[] = ['label' => 'Subjects', 'value' => $overview['subjects'], 'route' => 'settings.index'];
    @endphp
    @foreach($stats as $s)
      <a href="{{ route($s['route']) }}" class="p-5 transition bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50">
        <p class="text-3xl font-bold text-gray-900">{{ $s['value'] }}</p>
        <p class="mt-1 text-sm text-gray-500">{{ $s['label'] }}</p>
      </a>
    @endforeach
  </div>
  @endif

  {{-- Learning stats (only when the Kolibri/Learn module is actually in use) --}}
  @if($insights && ($insights['activeThisWeek'] || $insights['homeworkPending'] || $insights['struggling']))
  <div class="grid grid-cols-2 gap-4 mb-8 sm:grid-cols-3">
    <a href="{{ route('progress.index') }}" class="p-4 text-center bg-white border border-gray-200 rounded-lg hover:shadow-sm">
      <p class="text-2xl font-bold text-green-600">{{ $insights['activeThisWeek'] }}</p>
      <p class="text-xs text-gray-500">Practised this week</p>
    </a>
    <a href="{{ route('progress.index') }}" class="p-4 text-center rounded-lg hover:shadow-sm {{ $insights['homeworkPending'] > 0 ? 'bg-amber-50 border border-amber-200' : 'bg-white border border-gray-200' }}">
      <p class="text-2xl font-bold {{ $insights['homeworkPending'] > 0 ? 'text-amber-600' : 'text-gray-500' }}">{{ $insights['homeworkPending'] }}</p>
      <p class="text-xs text-gray-500">Homework pending</p>
    </a>
    <a href="{{ route('progress.index') }}" class="p-4 text-center rounded-lg hover:shadow-sm {{ $insights['struggling'] > 0 ? 'bg-orange-50 border border-orange-200' : 'bg-white border border-gray-200' }}">
      <p class="text-2xl font-bold {{ $insights['struggling'] > 0 ? 'text-orange-600' : 'text-gray-500' }}">{{ $insights['struggling'] }}</p>
      <p class="text-xs text-gray-500">Need help</p>
    </a>
  </div>
  @endif

  <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
    {{-- Classes at a glance --}}
    @if($overview && $overview['offerings']->isNotEmpty())
    <section class="lg:col-span-2">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase">{{ $overview['isAdmin'] ? 'Classes' : 'My classes' }}</h2>
        @if($term)
          <span class="text-xs text-gray-500">{{ $overview['termAssessments'] }} tests &amp; exams in {{ $term->name }}</span>
        @endif
      </div>
      <div class="overflow-hidden bg-white border border-gray-200 divide-y divide-gray-100 rounded-xl">
        @foreach($overview['offerings'] as $offering)
          <a href="{{ route('scorebook.class', $offering) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
            <span class="flex items-center gap-2.5 text-sm font-medium text-gray-900">
              <span class="w-2 h-2 rounded-full shrink-0" style="{{ $offering->grade?->dotStyle() }}"></span>
              {{ $offering->grade->name ?? 'Class' }}@if($offering->name) <span class="font-normal text-gray-500">{{ $offering->name }}</span>@endif
            </span>
            <span class="flex items-center gap-4 text-sm">
              @if($term)
                <span class="{{ $offering->term_assessments > 0 ? 'text-gray-600' : 'text-gray-500' }}">
                  {{ $offering->term_assessments }} {{ \Illuminate\Support\Str::plural('test/exam', $offering->term_assessments) }}
                </span>
              @endif
              <span class="text-gray-500 w-20 text-right">{{ $offering->students_count }} {{ \Illuminate\Support\Str::plural('student', $offering->students_count) }}</span>
            </span>
          </a>
        @endforeach
      </div>
    </section>
    @endif

    {{-- Quick actions --}}
    <section class="{{ ($overview && $overview['offerings']->isNotEmpty()) ? '' : 'lg:col-span-3' }}">
      <h2 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase">Quick actions</h2>
      <div class="grid grid-cols-1 gap-3 {{ ($overview && $overview['offerings']->isNotEmpty()) ? '' : 'sm:grid-cols-3' }}">
        @foreach([
          ['label' => 'Students', 'route' => 'students.index', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
          ['label' => 'Scorebook', 'route' => 'reporting.grades', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
          ['label' => 'Term reports', 'route' => 'termreports.overview', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ] as $a)
          <a href="{{ route($a['route']) }}" class="flex items-center gap-3 p-4 transition bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50">
            <span class="inline-flex items-center justify-center w-10 h-10 text-gray-500 rounded-lg bg-gray-100 shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $a['icon'] }}"/></svg>
            </span>
            <span class="text-sm font-semibold text-gray-900">{{ $a['label'] }}</span>
          </a>
        @endforeach
      </div>
    </section>
  </div>

  @endhasanyrole
</x-page>
