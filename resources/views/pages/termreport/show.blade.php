<x-page title="Term Report">
<div class="px-4 py-8 mx-auto max-w-4xl sm:px-6 lg:px-8">

  <div class="mb-4">
    <a href="{{ route('students.show', $report['student']->id) }}"
       class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to {{ $report['student']->first_name }} {{ $report['student']->last_name }}</a>
  </div>

  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-lg font-semibold text-gray-900">{{ $report['student']->first_name }} {{ $report['student']->last_name }}</h2>
      <p class="text-sm text-gray-500">
        {{ $report['grade']->name }} &middot; {{ $report['term']->name }} &middot;
        {{ $report['schoolyear']->start->format('Y') }}–{{ $report['schoolyear']->end->format('Y') }}
      </p>
    </div>
    @php
      $primaryCls = 'inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-gray-900 rounded-md shadow-sm hover:bg-gray-800';
      $secondaryCls = 'inline-flex items-center px-4 py-2 text-sm font-semibold text-indigo-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50';
      $bookPrimary = ($reportType ?? 'term') === 'book';
    @endphp
    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('term-report.print', ['enrollmentId' => $enrollmentId, 'termId' => $termId]) }}"
         class="{{ $bookPrimary ? $secondaryCls : $primaryCls }}"
         @style(['order:2' => $bookPrimary])>
        Download PDF
      </a>
      <a href="{{ route('report-book.screen', ['enrollmentId' => $enrollmentId]) }}"
         class="{{ $bookPrimary ? $primaryCls : $secondaryCls }}"
         @style(['order:1' => $bookPrimary])>
        Report Book
      </a>
      <a href="{{ route('report-book.print', ['enrollmentId' => $enrollmentId, 'empty' => 1]) }}" target="_blank" rel="noopener"
         class="{{ $secondaryCls }}" @style(['order:3' => $bookPrimary])>
        Blank booklet
      </a>
    </div>
  </div>

  {{-- Student info --}}
  <div class="overflow-x-auto border border-gray-200 shadow sm:rounded-lg mb-6">
    <table class="min-w-full">
      <tbody class="divide-y divide-gray-200">
        <tr>
          <td class="px-6 py-3 text-sm font-medium text-gray-500 bg-gray-50" style="width:160px">Student</td>
          <td class="px-6 py-3 text-sm text-gray-900">{{ $report['student']->first_name }} {{ $report['student']->last_name }}</td>
          <td class="px-6 py-3 text-sm font-medium text-gray-500 bg-gray-50" style="width:120px">Age</td>
          <td class="px-6 py-3 text-sm text-gray-900">
            @php $age = $report['student']->getAge(); @endphp
            @if($age > 5 && $age < 20) {{ $age }} @else — @endif
          </td>
        </tr>
        <tr>
          <td class="px-6 py-3 text-sm font-medium text-gray-500 bg-gray-50">Term</td>
          <td class="px-6 py-3 text-sm text-gray-900">{{ $report['term']->name }}</td>
          <td class="px-6 py-3 text-sm font-medium text-gray-500 bg-gray-50">Year</td>
          <td class="px-6 py-3 text-sm text-gray-900">{{ $report['schoolyear']->start->format('Y') }}–{{ $report['schoolyear']->end->format('Y') }}</td>
        </tr>
        <tr>
          <td class="px-6 py-3 text-sm font-medium text-gray-500 bg-gray-50">Class</td>
          <td class="px-6 py-3 text-sm text-gray-900">{{ $report['grade']->name }}</td>
          <td class="px-6 py-3 text-sm font-medium text-gray-500 bg-gray-50">Teacher</td>
          <td class="px-6 py-3 text-sm text-gray-900">
            @if($report['teacher'])
              {{ $report['teacher']->first_name }} {{ $report['teacher']->last_name }}
            @else — @endif
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  {{-- Grades table --}}
  @php
    $school = \App\Models\School::first();
    // Only weighted types (Test/Exam) make up a term report — the NAT (weight 0)
    // is a separate deliverable and doesn't belong on the term report.
    $assessmentTypes = $school ? $school->assessmentTypes->where('weight', '>', 0)->values() : collect();
  @endphp
  <div class="overflow-x-auto border border-gray-200 shadow sm:rounded-lg mb-6">
    <table class="min-w-full divide-y divide-gray-200">
      <thead>
        <tr>
          <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 uppercase bg-gray-50">Subject</th>
          @foreach($assessmentTypes as $type)
          <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase bg-gray-50">{{ $type->name }}s ({{ round($type->weight * 100) }})</th>
          @endforeach
          <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right text-gray-500 uppercase bg-gray-50">Total (%)</th>
        </tr>
      </thead>
      <tbody>
        @foreach($report['results']['subjectResults'] as $subject => $result)
        <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
          <td class="px-6 py-3 text-sm font-medium text-gray-900 whitespace-nowrap">{{ $subject }}</td>
          @foreach($assessmentTypes as $type)
          <td class="px-6 py-3 text-sm text-gray-600 text-right whitespace-nowrap">
            {{ $result['typeResults'][$type->id]['weightedScore'] ?? '—' }}
          </td>
          @endforeach
          <td class="px-6 py-3 text-sm font-medium text-right whitespace-nowrap {{ $result['subjectTotal'] !== null ? 'text-gray-900' : 'text-gray-500' }}">
            {{ $result['subjectTotal'] ?? '—' }}
          </td>
        </tr>
        @endforeach
        <tr class="bg-gray-50 border-t-2 border-gray-300">
          <td class="px-6 py-3 text-sm font-bold text-gray-900">Total</td>
          @foreach($assessmentTypes as $type)
          <td class="px-6 py-3"></td>
          @endforeach
          <td class="px-6 py-3 text-sm font-bold text-gray-900 text-right">{{ $report['results']['total'] }}</td>
        </tr>
        <tr class="bg-white">
          <td class="px-6 py-3 text-sm font-bold text-gray-900">Average</td>
          @foreach($assessmentTypes as $type)
          <td class="px-6 py-3"></td>
          @endforeach
          <td class="px-6 py-3 text-sm font-bold text-right
            {{ $report['results']['average'] >= 50 ? 'text-green-700' : 'text-red-600' }}">
            {{ $report['results']['average'] }}%
          </td>
        </tr>
      </tbody>
    </table>
  </div>

</div>
</x-page>
