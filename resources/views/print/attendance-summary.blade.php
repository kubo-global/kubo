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
    th, td { border: 1px solid #6b7280; padding: 3px 4px; text-align: center; }
    thead th { background: #eef1f8; color: #34486f; font-size: 9.5px; font-weight: bold; }
    td.date, td.day { text-align: left; }
    td.day { font-weight: bold; color: #374151; }
    tr.total td { font-weight: bold; background: #f3f4f6; }
    .num { width: 11%; }
    .datecol { width: 14%; }
    .daycol { width: 16%; }
    .foot { margin-top: 8px; color: #9ca3af; font-size: 9px; text-align: right; }
  </style>
</head>
<body>
  <div class="school">{{ $school->name ?? 'School' }}</div>
  <div class="title">STUDENTS DAILY ATTENDANCE</div>
  <div class="meta">
    <b>Name of teacher:</b> {{ $teacher ? $teacher->first_name.' '.$teacher->last_name : '..............................' }}
    &nbsp;&nbsp; <b>Grade:</b> {{ $offering->displayName() }}
    &nbsp;&nbsp; <b>Month:</b> {{ $month->format('F Y') }}
  </div>

  @forelse ($weeks as $weekNum => $rows)
    @php
      $sum = ['bp' => 0, 'gp' => 0, 'tp' => 0, 'ba' => 0, 'ga' => 0, 'ta' => 0];
      foreach ($rows as $r) { foreach ($sum as $k => $v) { $sum[$k] += $r[$k]; } }
    @endphp
    <table>
      <thead>
        <tr>
          <th class="datecol">DATE</th>
          <th class="daycol">WEEK {{ $weekNum }}</th>
          <th class="num">BOYS PRES.</th>
          <th class="num">GIRLS PRES.</th>
          <th class="num">TOTAL PRES.</th>
          <th class="num">BOYS ABS.</th>
          <th class="num">GIRLS ABS.</th>
          <th class="num">TOTAL ABS.</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $r)
          <tr>
            <td class="date">{{ $r['date'] }}</td>
            <td class="day">{{ $r['day'] }}</td>
            <td>{{ $r['bp'] ?: '' }}</td>
            <td>{{ $r['gp'] ?: '' }}</td>
            <td>{{ $r['tp'] ?: '' }}</td>
            <td>{{ $r['ba'] ?: '' }}</td>
            <td>{{ $r['ga'] ?: '' }}</td>
            <td>{{ $r['ta'] ?: '' }}</td>
          </tr>
        @endforeach
        <tr class="total">
          <td></td>
          <td class="day">TOTAL</td>
          <td>{{ $sum['bp'] }}</td>
          <td>{{ $sum['gp'] }}</td>
          <td>{{ $sum['tp'] }}</td>
          <td>{{ $sum['ba'] }}</td>
          <td>{{ $sum['ga'] }}</td>
          <td>{{ $sum['ta'] }}</td>
        </tr>
      </tbody>
    </table>
  @empty
    <p style="margin-top:20px; color:#6b7280;">No attendance has been recorded for this class in {{ $month->format('F Y') }}.</p>
  @endforelse

  <div class="foot">Generated with KUBO · {{ $month->format('F Y') }}</div>
</body>
</html>
