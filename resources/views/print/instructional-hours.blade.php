<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; }
    @page { margin: 24px 28px; }
    body { color: #1f2937; font-size: 11px; }
    .school { text-align: center; font-size: 15px; font-weight: bold; }
    .title { text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 8px; letter-spacing: 0.5px; }
    .meta { margin: 4px 0 10px; font-size: 11px; }
    .meta b { font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; }
    th, td { border: 1px solid #6b7280; padding: 3px 5px; text-align: center; }
    thead th { background: #eef1f8; color: #34486f; font-size: 9.5px; font-weight: bold; }
    td.day { text-align: left; font-weight: bold; color: #374151; }
    td.rem { text-align: left; }
    tr.total td { font-weight: bold; background: #f3f4f6; }
    .datecol { width: 12%; } .daycol { width: 16%; } .hrs { width: 13%; }
    .foot { margin-top: 8px; color: #9ca3af; font-size: 9px; text-align: right; }
  </style>
</head>
<body>
  <div class="school">{{ $school->name ?? 'School' }}</div>
  <div class="title">DATA COLLECTION ON INSTRUCTIONAL HOURS</div>
  <div class="meta">
    <b>Name of teacher:</b> {{ $teacher ? $teacher->first_name.' '.$teacher->last_name : '..............................' }}
    &nbsp;&nbsp; <b>Grade:</b> {{ $offering->displayName() }}
    &nbsp;&nbsp; <b>Month:</b> {{ $month->format('F Y') }}
  </div>

  @php $fmt = fn ($n) => $n ? rtrim(rtrim(number_format($n, 2), '0'), '.') : ''; @endphp
  @forelse ($weeks as $weekNum => $rows)
    @php $sumE = collect($rows)->sum('expected'); $sumA = collect($rows)->sum('actual'); $sumL = collect($rows)->sum('lost'); @endphp
    <table>
      <thead>
        <tr>
          <th class="datecol">DATE</th>
          <th class="daycol">WEEK {{ $weekNum }}</th>
          <th class="hrs">EXPECTED HRS</th>
          <th class="hrs">ACTUAL HRS</th>
          <th class="hrs">LOST HRS</th>
          <th>REMARKS</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $r)
          <tr>
            <td>{{ $r['date_short'] }}</td>
            <td class="day">{{ $r['day'] }}</td>
            <td>{{ $fmt($r['expected']) }}</td>
            <td>{{ $fmt($r['actual']) }}</td>
            <td>{{ $fmt($r['lost']) }}</td>
            <td class="rem">{{ $r['remarks'] }}</td>
          </tr>
        @endforeach
        <tr class="total">
          <td></td><td class="day">TOTAL</td>
          <td>{{ $fmt($sumE) }}</td>
          <td>{{ $fmt($sumA) }}</td>
          <td>{{ $fmt($sumL) }}</td>
          <td></td>
        </tr>
      </tbody>
    </table>
  @empty
    <p style="margin-top:20px; color:#6b7280;">No weekdays in {{ $month->format('F Y') }}.</p>
  @endforelse

  <div class="foot">Generated with KUBO · {{ $month->format('F Y') }}</div>
</body>
</html>
