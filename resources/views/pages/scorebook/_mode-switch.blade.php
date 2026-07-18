{{-- class-level mode selector: regular term tests/exams vs the NAT vs timetable. expects $offering, $active ('regular'|'nat'|'timetable') --}}
{{-- Scroll horizontally on phones so every tab stays reachable; natural width on sm+. --}}
<div class="w-full overflow-x-auto sm:w-auto">
<div class="inline-flex overflow-hidden text-sm border border-gray-300 rounded-lg shadow-sm">
  <a href="{{ route('scorebook.class', $offering) }}"
    class="px-4 py-2 font-semibold {{ $active === 'regular' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
    Tests &amp; exams
  </a>
  <a href="{{ route('scorebook.nat', $offering) }}"
    class="inline-flex items-center gap-1.5 px-4 py-2 font-semibold border-l border-gray-300 {{ $active === 'nat' ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-700 hover:bg-gray-50' }}">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 3v3h6V3M8 12l2 2 4-4"/></svg>
    National Assessment Test
  </a>
  <a href="{{ route('scorebook.positions', $offering) }}"
    class="inline-flex items-center gap-1.5 px-4 py-2 font-semibold border-l border-gray-300 {{ $active === 'positions' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V10M10 19V5M16 19v-7M20 19H3"/></svg>
    Positions
  </a>
  <a href="{{ route('scorebook.attendance', $offering) }}"
    class="inline-flex items-center gap-1.5 px-4 py-2 font-semibold border-l border-gray-300 {{ $active === 'attendance' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    Attendance
  </a>
  <a href="{{ route('term-report.prepare', $offering) }}"
    class="inline-flex items-center gap-1.5 px-4 py-2 font-semibold border-l border-gray-300 {{ $active === 'prepare' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15.5 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V8.5L15.5 3z"/><path d="M8 13h8M8 17h5M14 3v5h5"/></svg>
    Prepare reports
  </a>
</div>
</div>
