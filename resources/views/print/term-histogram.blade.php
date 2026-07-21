<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 22px 26px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1f2937; font-size: 10px; }
    .intro { font-size: 11px; margin-bottom: 8px; line-height: 1.4; }
    table.box { width: 100%; border: 1.5px solid #111; border-collapse: collapse; margin-bottom: 12px; }
    table.box td { padding: 4px 8px; font-size: 10px; border: 1px solid #111; }
    table.box b { font-weight: bold; }

    td.yaxis { vertical-align: top; padding: 0 3px 0 0; }
    td.yaxis .tick { height: 37.1px; font-size: 9px; line-height: 1; text-align: right; color: #374151; }
    table.chart { border-collapse: collapse; }
    td.bc { vertical-align: bottom; height: 780px; padding: 0; }
    td.bc .bar { margin: 0 auto; }
    tr.base td { border-top: 1.2px solid #111; height: 0; line-height: 0; font-size: 0; padding: 0; }
    td.mfo { font-size: 8px; text-align: center; color: #374151; padding: 1px 0; }
    td.cat { font-size: 7.5px; font-weight: bold; text-align: center; border: 1px solid #333; padding: 2px 0; }
    td.subj { font-size: 9.5px; font-weight: bold; text-align: center; border: 1px solid #333; padding: 3px 0; }

    .legend { margin-top: 16px; font-size: 13px; }
    .legend .lt { font-weight: bold; font-size: 14px; margin-bottom: 6px; }
    .legend i { display: inline-block; width: 16px; height: 16px; margin-right: 6px; vertical-align: middle; }
    .legend span { margin-right: 26px; }
  </style>
</head>
<body>

  @php
    $cats = ['fail' => ['Fail', '#ef6b7d'], 'pass' => ['Pass', '#4caf50'], 'mastery' => ['Mastery', '#7aa0d0']];
    $overallColor = '#e8913c';
    $blank = $outline ?? false;

    // Fit up to 4 subjects per page; beyond that, spread across pages (balanced),
    // and size the bars to fill the width for that page's subject count.
    $n = max(1, count($analysis));
    $pages = (int) ceil($n / 4);
    $perPage = (int) ceil($n / max(1, $pages));
    $bc = max(10, min(20, (int) round(700 / (10.97 * $perPage - 1.1))));
    $barW = max(6, $bc - 2);
    $gapW = max(3, (int) round($bc / 2.3));
    $sgapW = max(6, (int) round($bc * 1.1));
    $chunks = collect($analysis)->values()->chunk($perPage);
  @endphp

  <style>
    td.bc { width: {{ $bc }}px; }
    td.bc .bar { width: {{ $barW }}px; }
    td.gap { width: {{ $gapW }}px; }
    td.sgap { width: {{ $sgapW }}px; }
  </style>

  @foreach ($chunks as $ci => $chunk)
    @php $cols = count($chunk) * 11 + max(0, count($chunk) - 1); @endphp
    <div style="{{ ! $loop->last ? 'page-break-after: always;' : '' }}">
      <div class="intro">
        The below graph shows the result performance of the <b>{{ $offering->displayName() }}</b> pupils
        in the <b>{{ $term->name }} {{ $periodTitle }}</b> {{ $offering->schoolyear->name ?? '' }} academic year.@if ($pages > 1) <b>(part {{ $ci + 1 }} of {{ $pages }})</b>@endif
      </div>

      <table class="box">
        <tr>
          <td><b>GRADE:</b> {{ $offering->grade->name ?? '' }} {{ $offering->name }}</td>
          <td><b>ANALYSIS YEAR:</b> {{ $offering->schoolyear->name ?? '' }}</td>
        </tr>
        <tr>
          <td><b>NUMBER OF STUDENTS IN THE CLASS:</b> {{ $studentCount }}</td>
          <td><b>NUMBER OF STUDENTS SAT:</b> {{ $satCount }}</td>
        </tr>
      </table>

      <table style="margin: 0 auto; border-collapse: collapse;">
        <tr>
          <td class="yaxis">
            @for ($v = 100; $v >= 0; $v -= 5)<div class="tick">{{ $v }}</div>@endfor
          </td>
          <td style="vertical-align: top;">
            <table class="chart">
              {{-- bars --}}
              <tr>
                @foreach ($chunk as $row)
                  @foreach ($cats as $catKey => $cat)
                    @foreach (['male', 'female', 'overall'] as $g)
                      @php $pct = $row[$g][$catKey.'Pct']; $c = $g === 'overall' ? $overallColor : $cat[1]; @endphp
                      <td class="bc"><div class="bar" style="height: {{ max(1, round($pct * 7.75)) }}px; {{ $blank ? 'border: 1.2px solid #333; background: #fff;' : 'background: '.$c.';' }}"></div></td>
                    @endforeach
                    @if (! $loop->last)<td class="gap"></td>@endif
                  @endforeach
                  @if (! $loop->last)<td class="sgap"></td>@endif
                @endforeach
              </tr>
              <tr class="base"><td colspan="{{ $cols }}"></td></tr>
              {{-- M / F / O --}}
              <tr>
                @foreach ($chunk as $row)
                  @foreach ($cats as $catKey => $cat)
                    <td class="mfo">M</td><td class="mfo">F</td><td class="mfo">O</td>
                    @if (! $loop->last)<td class="gap"></td>@endif
                  @endforeach
                  @if (! $loop->last)<td class="sgap"></td>@endif
                @endforeach
              </tr>
              {{-- Fail / Pass / Mastery --}}
              <tr>
                @foreach ($chunk as $row)
                  @foreach ($cats as $catKey => $cat)
                    <td class="cat" colspan="3">{{ $cat[0] }}</td>
                    @if (! $loop->last)<td class="gap"></td>@endif
                  @endforeach
                  @if (! $loop->last)<td class="sgap"></td>@endif
                @endforeach
              </tr>
              {{-- Subject --}}
              <tr>
                @foreach ($chunk as $row)
                  <td class="subj" colspan="11">{{ strtoupper($row['subject']->name) }}</td>
                  @if (! $loop->last)<td class="sgap"></td>@endif
                @endforeach
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <div class="legend">
        <div class="lt">Key features @if ($blank)<span style="font-weight: normal; color: #6b7280;">(colour each bar to match its group)</span>@endif</div>
        <span><i style="{{ $blank ? 'border: 1px solid #333; background: #fff;' : 'background: #4caf50;' }}"></i>Pass</span>
        <span><i style="{{ $blank ? 'border: 1px solid #333; background: #fff;' : 'background: #ef6b7d;' }}"></i>Fail</span>
        <span><i style="{{ $blank ? 'border: 1px solid #333; background: #fff;' : 'background: #7aa0d0;' }}"></i>Mastery</span>
        <span><i style="{{ $blank ? 'border: 1px solid #333; background: #fff;' : 'background: #e8913c;' }}"></i>Overall</span>
        <div style="margin-top: 5px; font-size: 11px; color: #6b7280;">bars are % of pupils &middot; M = male, F = female, O = overall</div>
      </div>
    </div>
  @endforeach

</body>
</html>
