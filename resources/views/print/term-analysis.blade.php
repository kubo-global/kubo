<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 26px 26px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1f2937; font-size: 11px; }
    .header { text-align: center; border-bottom: 2px solid #3d5494; padding-bottom: 6px; margin-bottom: 10px; }
    .school { font-size: 16px; font-weight: bold; }
    .sub { font-size: 11px; color: #4b5563; }
    .sub b { color: #1f2937; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: center; }
    table.grid thead th { background: #eef1f8; color: #34486f; font-size: 9.5px; vertical-align: bottom; }
    td.subject { font-weight: bold; text-align: left; vertical-align: middle; background: #f7f8fb; }
    td.sex { text-align: left; }
    tr.overall td { font-weight: bold; background: #f3f6fc; }
    .foot { margin-top: 12px; font-size: 9.5px; color: #6b7280; }
    .intro { font-size: 11px; margin: 8px 0 12px; line-height: 1.4; }
  </style>
</head>
<body>
  <div class="header">
    <div class="school">{{ $school->name ?? 'School' }}</div>
    <div class="sub">Internal Assessment &middot; Result Analysis</div>
  </div>

  <div class="intro">
    Below is the result analysis of the <b>{{ $term->name }} {{ $periodTitle }}</b> for the
    <b>{{ $offering->displayName() }}</b> pupils in {{ $offering->schoolyear->name ?? '' }}.
  </div>

  <table class="grid">
    <thead>
      <tr>
        <th style="text-align:left;">Subject</th>
        <th style="text-align:left;">Sex/Roll</th>
        <th>Number of<br>students</th>
        <th>Number<br>sat</th>
        <th>Number<br>fail</th>
        <th>%<br>fail</th>
        <th>Number<br>pass</th>
        <th>%<br>pass</th>
        <th>Number<br>mastery</th>
        <th>%<br>mastery</th>
        <th>Average</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($analysis as $row)
        @foreach (['male' => 'Male', 'female' => 'Female', 'overall' => 'Overall'] as $key => $label)
          @php $g = $row[$key]; @endphp
          <tr class="{{ $key === 'overall' ? 'overall' : '' }}">
            @if ($key === 'male')
              <td class="subject" rowspan="3">{{ $row['subject']->name }}</td>
            @endif
            <td class="sex">{{ $label }}</td>
            <td>{{ $g['students'] }}</td>
            <td>{{ $g['sat'] }}</td>
            <td>{{ $g['fail'] }}</td>
            <td>{{ $g['failPct'] }}%</td>
            <td>{{ $g['pass'] }}</td>
            <td>{{ $g['passPct'] }}%</td>
            <td>{{ $g['mastery'] }}</td>
            <td>{{ $g['masteryPct'] }}%</td>
            <td>{{ $g['average'] }}</td>
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>

  <div class="foot">
    Fail below 40 &middot; Pass 40&ndash;79 &middot; Mastery 80 and above. Percentages are of the number who sat.
  </div>
</body>
</html>
