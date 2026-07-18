<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 18px 22px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1f2937; font-size: 11px; }
    .header { border-bottom: 2px solid #3d5494; padding-bottom: 8px; margin-bottom: 12px; }
    .header table { width: 100%; }
    .header .logo { width: 56px; vertical-align: middle; }
    .header .title { font-size: 18px; font-weight: bold; color: #1f2937; }
    .header .sub { font-size: 12px; color: #4b5563; font-weight: bold; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: middle; }
    table.grid thead th { vertical-align: bottom; }
    table.grid thead th { background: #eef1f8; color: #34486f; font-size: 11px; text-align: left; }
    table.grid td.period { width: 130px; }
    .pname { font-weight: bold; }
    .ptime, .teacher { color: #6b7280; font-size: 9px; }
    .subject { font-weight: bold; color: #1f2937; }
    tr.stripe td { background: #f7f8fb; }
    tr.break td { background: #eceef2; }
    .break-label { color: #9ca3af; font-style: italic; }
    .foot { margin-top: 10px; color: #9ca3af; font-size: 9px; text-align: right; }
  </style>
</head>
<body>
  <div class="header">
    <table>
      <tr>
        @if ($logo)<td class="logo"><img src="{{ $logo }}" class="logo"></td>@endif
        <td>
          <div class="title">{{ $school->name ?? 'School' }}</div>
          <div class="sub">Timetable — {{ $offering->displayName() }} — {{ $offering->schoolyear->name ?? '' }}</div>
        </td>
      </tr>
    </table>
  </div>

  <table class="grid">
    <thead>
      <tr>
        <th>Period</th>
        @foreach ($days as $dayName)<th>{{ $dayName }}</th>@endforeach
      </tr>
    </thead>
    <tbody>
      @php
        $stripe = 0;
        // Fill the page: spread the usable A4-landscape height across the period rows,
        // so a short timetable gets taller rows instead of trailing whitespace. With
        // enough rows this drops below the natural cell height and content height wins.
        // The divisor is the usable body height (page minus header/thead/footer and the
        // per-row cell padding); kept conservative so the last row never spills over.
        $rowHeight = max(28, intdiv(500, max(1, $periods->count())));
      @endphp
      @foreach ($periods as $period)
        @php if (! $period->is_break) { $stripe++; } @endphp
        <tr class="{{ $period->is_break ? 'break' : ($stripe % 2 === 0 ? 'stripe' : '') }}">
          <td class="period" style="height: {{ $rowHeight }}px">
            <span class="pname">{{ $period->label }}</span>
            @if ($period->timeRange())<div class="ptime">{{ $period->timeRange() }}</div>@endif
          </td>
          @foreach ($days as $dayNum => $dayName)
            @php $cell = $grid[$period->id][$dayNum]; $lesson = $cell['lesson']; @endphp
            @continue($cell['kind'] === 'covered')
            <td @if($cell['rowspan'] > 1) rowspan="{{ $cell['rowspan'] }}" @endif>
              @if ($cell['kind'] === 'break')
                <span class="break-label">break</span>
              @elseif ($lesson)
                <span class="subject">{{ optional($lesson->subject)->name }}</span>
                @if ($lesson->teacher)<div class="teacher">{{ $lesson->teacher->first_name }} {{ $lesson->teacher->last_name }}</div>@endif
              @endif
            </td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="foot">Generated with KUBO</div>
</body>
</html>
