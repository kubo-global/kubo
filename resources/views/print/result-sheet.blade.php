<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; }
    @page { margin: 20px 22px; }
    body { color: #1f2937; font-size: 10px; }
    .school { text-align: center; font-size: 15px; font-weight: bold; }
    .title { text-align: center; font-size: 12px; font-weight: bold; letter-spacing: 0.5px; }
    .meta { margin: 4px 0 8px; font-size: 10px; }
    .meta b { font-weight: bold; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #6b7280; padding: 2px 3px; text-align: center; }
    thead th { background: #eef1f8; color: #34486f; font-size: 8px; font-weight: bold; vertical-align: bottom; }
    td.name, th.name { text-align: left; }
    td.no { width: 22px; color: #6b7280; }
    th.name, td.name { width: 150px; }
    td.tot { font-weight: bold; }
    .subj { font-size: 8px; }
    .divide { border-left: 2px solid #34486f; }
    .grp { font-size: 8px; background: #dde4f2; color: #34486f; }
    tr:nth-child(even) td { background: #f8f9fc; }
    .foot { margin-top: 8px; color: #9ca3af; font-size: 9px; text-align: right; }
  </style>
</head>
<body>
  <div class="school">{{ $school->name ?? 'School' }}</div>
  <div class="title">INTERNAL ASSESSMENT RESULT SHEET</div>
  <div class="meta">
    <b>Class:</b> {{ $offering->displayName() }}
    &nbsp;&nbsp; <b>Term:</b> {{ $term?->name ?? '—' }}
    &nbsp;&nbsp; <b>Teacher:</b> {{ $teacher ? $teacher->first_name.' '.$teacher->last_name : '—' }}
    &nbsp;&nbsp; <b>Year:</b> {{ $offering->schoolyear->name ?? '' }}
  </div>

  @if ($rows->isEmpty() || $subjects->isEmpty())
    <p style="margin-top:16px; color:#6b7280;">No scored subjects for this class and term yet.</p>
  @else
    @php
      $calcCount = $subjects->where('counts_toward_total', true)->count();
      // Only draw the group split when the class actually has both kinds of subject.
      $hasGraded = $calcCount > 0 && $calcCount < $subjects->count();
    @endphp
    <table>
      <thead>
        @if ($hasGraded)
        <tr>
          <th class="no"></th>
          <th class="name"></th>
          <th class="grp" colspan="{{ $calcCount }}">Calculated &mdash; counts toward total</th>
          <th class="grp divide" colspan="{{ $subjects->count() - $calcCount }}">Graded</th>
          <th></th><th></th><th></th>
        </tr>
        @endif
        <tr>
          <th class="no">#</th>
          <th class="name">NAME</th>
          @foreach ($subjects as $s)
            <th class="subj {{ $hasGraded && $loop->index === $calcCount ? 'divide' : '' }}">{{ $s->name }}</th>
          @endforeach
          <th>TOTAL</th>
          <th>AVG %</th>
          <th>POS</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $i => $row)
          @php $results = $row['report']['results']; @endphp
          <tr>
            <td class="no">{{ $i + 1 }}</td>
            <td class="name">{{ ucfirst($row['student']->first_name).' '.ucfirst($row['student']->last_name) }}</td>
            @foreach ($subjects as $s)
              @php $sr = $results['subjectResults'][$s->name] ?? null; @endphp
              <td class="{{ $hasGraded && $loop->index === $calcCount ? 'divide' : '' }}">{{ $sr && $sr['subjectTotal'] !== null ? round($sr['subjectTotal']) : '' }}</td>
            @endforeach
            <td class="tot">{{ $row['total'] !== null ? round($row['total']) : '' }}</td>
            <td>{{ $row['average'] !== null ? round($row['average']).'%' : '' }}</td>
            <td>{{ $row['position'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="foot">Generated with KUBO</div>
</body>
</html>
