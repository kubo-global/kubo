<x-page title="Enter new grades">
  <div class="max-w-2xl px-6 py-10 mx-auto lg:px-8">
    <h2 class="text-xl font-bold text-gray-900">What type of assessment?</h2>
    <p class="mt-1 text-sm text-gray-500">Choose what you're entering scores for.</p>

    <div class="mt-6 space-y-3">
      @foreach($assessmentTypes ?? [] as $type)
        @php
          $isExam = str_contains(strtolower($type->name), 'exam');
          $accent = $isExam ? ['bg-amber-50', 'text-amber-600'] : ['bg-indigo-50', 'text-indigo-600'];
          $pct = $type->weight !== null ? round($type->weight * 100) : null;
        @endphp
        <a href="{{ route('reporting.assessment.create', ['type' => $type->id, 'offering' => $offering, 'term' => $term, 'subject' => $subject]) }}"
          class="flex items-center gap-4 p-4 transition bg-white border border-gray-200 group rounded-xl hover:border-indigo-300 hover:shadow-sm">
          <span class="inline-flex items-center justify-center rounded-lg w-11 h-11 shrink-0 {{ $accent[0] }} {{ $accent[1] }}">
            @if ($isExam)
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            @else
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            @endif
          </span>
          <div class="min-w-0">
            <div class="font-semibold text-gray-900">{{ $type->name }}</div>
            @if ($pct !== null)
              <div class="text-sm text-gray-500">Counts for {{ $pct }}% of the term grade</div>
            @endif
          </div>
          <svg class="w-5 h-5 ml-auto text-gray-500 transition group-hover:text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
      @endforeach
    </div>
  </div>
</x-page>
