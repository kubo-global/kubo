<x-page :title="($offering->grade->name ?? '') . ' ' . $offering->name . ' — Progress'">
  <div class="mx-6">
    <div class="min-w-full mt-8 align-middle">

      <div class="flex items-center justify-between mb-6">
        <div>
          <a href="{{ route('progress.index') }}" class="text-sm text-gray-500 hover:text-gray-600">&larr; All classes</a>
          <h2 class="text-lg font-medium text-gray-900 mt-1">{{ $offering->grade->name ?? '' }} {{ $offering->name }}</h2>
        </div>
        @if($assignment)
        <div class="text-right">
          <p class="text-xs text-gray-500 uppercase tracking-wider">Week {{ $currentWeek }} assignment</p>
          <p class="text-sm font-medium text-gray-700">{{ $assignment->skill->name }}</p>
        </div>
        @endif
      </div>

      @if($students->isEmpty())
        <div class="my-8 p-8 text-center text-gray-500 border border-gray-200 rounded-md">
          No students enrolled in this class.
        </div>
      @else

      {{-- Summary cards --}}
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-4 bg-white border border-gray-200 rounded-lg text-center">
          <p class="text-2xl font-bold text-gray-900">{{ $students->count() }}</p>
          <p class="text-xs text-gray-500">Students</p>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-lg text-center">
          <p class="text-2xl font-bold text-gray-900">{{ $students->sum('runs_this_week') }}</p>
          <p class="text-xs text-gray-500">Exercises this week</p>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-lg text-center">
          <p class="text-2xl font-bold text-gray-900">{{ $students->filter(fn ($s) => $s['runs_this_week'] > 0)->count() }}</p>
          <p class="text-xs text-gray-500">Active students</p>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-lg text-center">
          @php $avgScores = $students->filter(fn ($s) => $s['avg_score'] !== null); @endphp
          <p class="text-2xl font-bold text-gray-900">{{ $avgScores->count() > 0 ? round($avgScores->avg('avg_score')) . '%' : '--' }}</p>
          <p class="text-xs text-gray-500">Avg score this week</p>
        </div>
      </div>

      {{-- Student table --}}
      <div class="border border-gray-200 rounded-lg shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This week</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Avg score</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skills</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            @foreach($students as $row)
            <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }} hover:bg-indigo-50">
              <td class="px-4 py-3 whitespace-nowrap">
                <p class="text-sm font-medium text-gray-900">{{ $row['student']->first_name }} {{ $row['student']->last_name }}</p>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-center">
                @if($row['runs_this_week'] > 0)
                <span class="text-sm text-gray-900">{{ $row['runs_this_week'] }}</span>
                @else
                <span class="text-sm text-gray-500">--</span>
                @endif
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-center">
                @if($row['avg_score'] !== null)
                <span class="text-sm font-medium {{ $row['avg_score'] >= 80 ? 'text-green-600' : ($row['avg_score'] >= 50 ? 'text-gray-900' : 'text-orange-600') }}">{{ $row['avg_score'] }}%</span>
                @else
                <span class="text-sm text-gray-500">--</span>
                @endif
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                  @foreach($skills->take(20) as $skill)
                    @php $ss = $row['skills']->get($skill->id); @endphp
                    @if($ss && $ss->status === 'mastered')
                    <div class="w-3 h-3 rounded-full" style="background: #22c55e" title="{{ $skill->name }}: Mastered ({{ round($ss->mastery) }}%)" role="img" aria-label="{{ $skill->name }}: Mastered"></div>
                    @elseif($ss && $ss->status === 'struggling')
                    <div class="w-3 h-3 rounded-full" style="background: #f97316" title="{{ $skill->name }}: Needs more practice ({{ round($ss->mastery) }}%)" role="img" aria-label="{{ $skill->name }}: Needs more practice"></div>
                    @elseif($ss && $ss->status === 'in_progress')
                    <div class="w-3 h-3 rounded-full" style="background: #f59e0b" title="{{ $skill->name }}: In progress ({{ round($ss->mastery) }}%)" role="img" aria-label="{{ $skill->name }}: In progress"></div>
                    @else
                    <div class="w-3 h-3 rounded-full border border-gray-200" title="{{ $skill->name }}: Not started" role="img" aria-label="{{ $skill->name }}: Not started"></div>
                    @endif
                  @endforeach
                  @if($row['total_skills'] > 0)
                  <span class="text-xs text-gray-500 ml-1">{{ $row['mastered'] }}/{{ $row['total_skills'] }}</span>
                  @endif
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- Skill legend --}}
      <div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
        <div class="flex items-center gap-1"><div class="w-3 h-3 rounded-full" style="background: #22c55e"></div> Mastered</div>
        <div class="flex items-center gap-1"><div class="w-3 h-3 rounded-full" style="background: #f97316"></div> Needs more practice</div>
        <div class="flex items-center gap-1"><div class="w-3 h-3 rounded-full" style="background: #f59e0b"></div> In progress</div>
        <div class="flex items-center gap-1"><div class="w-3 h-3 rounded-full border border-gray-200"></div> Not started</div>
      </div>

      @endif
    </div>
  </div>
</x-page>
