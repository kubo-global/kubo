<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; }
    @page { margin: 28px 32px; }
    body { color: #1f2937; font-size: 11px; }
    .school { text-align: center; font-size: 15px; font-weight: bold; }
    .title { text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 2px; letter-spacing: 0.5px; }
    .sub { text-align: center; font-size: 11px; color: #6b7280; margin-bottom: 18px; }
    .plot { width: 100%; border-collapse: collapse; }
    .plot > tbody > tr > td { vertical-align: bottom; text-align: center; }
    .bar { margin: 0 3px; }
    .val { font-size: 9px; color: #4b5563; padding-bottom: 2px; }
    .axis { border-top: 1px solid #9ca3af; }
    .wk { text-align: center; font-size: 10px; color: #374151; padding-top: 4px; }
    .legend { margin-top: 20px; font-size: 11px; }
    .swatch { display: inline-block; width: 14px; height: 14px; vertical-align: middle; margin-right: 5px; }
    .foot { margin-top: 12px; color: #9ca3af; font-size: 9px; text-align: right; }
  </style>
</head>
<body>
  <div class="school">{{ $school->name ?? 'School' }}</div>
  <div class="title">ANALYSED CHART ON INSTRUCTIONAL HOURS DATA</div>
  <div class="sub">{{ $offering->displayName() }} · {{ $month->format('F Y') }}</div>

  @php $H = 220; @endphp
  <table class="plot">
    <tr>
      {{-- y-axis: 0-100% scale --}}
      <td style="width: 26px; vertical-align: bottom; padding: 0;">
        <div style="position: relative; height: {{ $H }}px; font-size: 8px; color: #6b7280;">
          @foreach ([100, 75, 50, 25, 0] as $t)
            <div style="height: {{ $H / 4 }}px; text-align: right; padding-right: 3px;">{{ $t }}%</div>
          @endforeach
        </div>
      </td>
      @foreach ($series as $s)
        @php
          $ha = min($H, round($s['achievedPct'] / 100 * $H));
          $hl = min($H, round($s['lostPct'] / 100 * $H));
        @endphp
        <td>
          <table style="margin: 0 auto; border-collapse: collapse;">
            <tr>
              <td style="vertical-align: bottom; text-align: center;">
                <div class="val">{{ $s['achievedPct'] }}%</div>
                <div class="bar" style="width: 40px; height: {{ $ha }}px; background: #4caf50;"></div>
              </td>
              <td style="vertical-align: bottom; text-align: center;">
                <div class="val">{{ $s['lostPct'] }}%</div>
                <div class="bar" style="width: 40px; height: {{ $hl }}px; background: #e8913c;"></div>
              </td>
            </tr>
          </table>
        </td>
      @endforeach
    </tr>
    <tr>
      <td class="axis"></td>
      @foreach ($series as $s)
        <td class="axis"></td>
      @endforeach
    </tr>
    <tr>
      <td></td>
      @foreach ($series as $s)
        <td class="wk">Week {{ $s['week'] }}</td>
      @endforeach
    </tr>
  </table>

  <div class="legend">
    <span class="swatch" style="background: #4caf50;"></span> Achieved (% of expected)
    &nbsp;&nbsp;&nbsp;
    <span class="swatch" style="background: #e8913c;"></span> Lost (% of expected)
  </div>

  <div class="foot">Generated with KUBO · {{ $month->format('F Y') }}</div>
</body>
</html>
