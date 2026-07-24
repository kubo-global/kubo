{{-- Analysis + histogram tab panels, shared by the by-test|month view (per period)
     and the by-term view (combined term marks). Expects: $analysis, $analysisTitle,
     $offering, and optional $pdfParams for the download links. Lives inside an
     Alpine x-data scope with a `tab` property. --}}
@php
  $cats = ['fail' => ['Fail', '#ef6b7d'], 'pass' => ['Pass', '#4caf50'], 'mastery' => ['Mastery', '#7aa0d0']];
  $overallColor = '#e8913c';
@endphp
    {{-- ============ Analysis ============ --}}
    <div x-show="tab === 'analysis'" class="mt-5" style="display:none">
      <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
        <p class="text-sm text-gray-700">Below is the result analysis of the <b>{{ $analysisTitle }}</b> for the <b>{{ $offering->displayName() }}</b> pupils in {{ $offering->schoolyear->name ?? '' }}.</p>
        @if (!empty($pdfParams))<a href="{{ route('term-grid.analysis', $pdfParams) }}" class="text-sm text-indigo-600 hover:underline whitespace-nowrap">Download analysis (PDF)</a>@endif
      </div>
      <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
        <table class="min-w-full text-sm border-collapse">
          <thead>
            <tr class="text-xs text-gray-500 uppercase bg-gray-50">
              <th class="px-3 py-2 text-left border-b border-gray-200">Subject</th>
              <th class="px-3 py-2 text-left border-b border-l border-gray-200">Sex/Roll</th>
              <th class="px-2 py-2 border-b border-l border-gray-200">Students</th>
              <th class="px-2 py-2 border-b border-gray-200">Sat</th>
              <th class="px-2 py-2 border-b border-l border-gray-200">Fail</th>
              <th class="px-2 py-2 border-b border-gray-200">% fail</th>
              <th class="px-2 py-2 border-b border-l border-gray-200">Pass</th>
              <th class="px-2 py-2 border-b border-gray-200">% pass</th>
              <th class="px-2 py-2 border-b border-l border-gray-200">Mastery</th>
              <th class="px-2 py-2 border-b border-gray-200">% mastery</th>
              <th class="px-2 py-2 border-b border-l border-gray-200">Average</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($analysis as $row)
              @foreach (['male' => 'Male', 'female' => 'Female', 'overall' => 'Overall'] as $key => $label)
                @php $g = $row[$key]; @endphp
                <tr class="{{ $key === 'overall' ? 'bg-indigo-50/60 font-semibold' : 'bg-white' }}">
                  @if ($key === 'male')
                    <td class="px-3 py-1.5 font-semibold text-gray-800 align-middle border-b border-gray-200" rowspan="3">{{ $row['subject']->name }}</td>
                  @endif
                  <td class="px-3 py-1.5 text-gray-700 border-l border-gray-100">{{ $label }}</td>
                  <td class="px-2 py-1.5 text-center border-l border-gray-100">{{ $g['students'] }}</td>
                  <td class="px-2 py-1.5 text-center">{{ $g['sat'] }}</td>
                  <td class="px-2 py-1.5 text-center border-l border-gray-100">{{ $g['fail'] }}</td>
                  <td class="px-2 py-1.5 text-center">{{ $g['failPct'] }}%</td>
                  <td class="px-2 py-1.5 text-center border-l border-gray-100">{{ $g['pass'] }}</td>
                  <td class="px-2 py-1.5 text-center">{{ $g['passPct'] }}%</td>
                  <td class="px-2 py-1.5 text-center border-l border-gray-100">{{ $g['mastery'] }}</td>
                  <td class="px-2 py-1.5 text-center">{{ $g['masteryPct'] }}%</td>
                  <td class="px-2 py-1.5 text-center border-l border-gray-100">{{ $g['average'] }}</td>
                </tr>
              @endforeach
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="mt-2 text-xs text-gray-500">Fail below 40 &middot; Pass 40&ndash;79 &middot; Mastery 80 and above &middot; percentages of those who sat.</p>
    </div>

    {{-- ============ Histogram ============ --}}
    <div x-show="tab === 'histogram'" class="mt-5" style="display:none">
      <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
        <p class="text-sm text-gray-700">The below graph shows the result performance of the <b>{{ $offering->displayName() }}</b> pupils in the <b>{{ $analysisTitle }}</b> {{ $offering->schoolyear->name ?? '' }} academic year.</p>
        @if (!empty($pdfParams))<div class="flex gap-3 whitespace-nowrap">
          <a href="{{ route('term-grid.histogram', $pdfParams) }}" class="text-sm text-indigo-600 hover:underline">Download histogram (PDF)</a>
          <a href="{{ route('term-grid.histogram', array_merge($pdfParams, ['outline' => 1])) }}" class="text-sm text-indigo-600 hover:underline">Blank to colour in (B&amp;W)</a>
        </div>@endif
      </div>
      <div class="p-5 overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="flex" style="min-width: 640px">
          {{-- y-axis --}}
          <div class="flex flex-col justify-between pr-2 text-right text-gray-400" style="height: 300px; font-size: 10px">
            @for ($v = 100; $v >= 0; $v -= 10)<div>{{ $v }}</div>@endfor
          </div>
          {{-- bar groups --}}
          <div class="flex items-end flex-1 gap-6 pl-2 border-b border-l border-gray-300">
            @foreach ($analysis as $row)
              <div class="flex flex-col items-center">
                {{-- bars: each bar sits in an 18px slot so the M/F/O labels line up under it --}}
                <div class="flex items-end justify-center gap-2" style="height: 300px">
                  @foreach ($cats as $catKey => $cat)
                    <div class="flex items-end h-full">
                      @foreach (['male' => 'M', 'female' => 'F', 'overall' => 'O'] as $g => $glabel)
                        @php $pct = $row[$g][$catKey.'Pct']; $c = $g === 'overall' ? $overallColor : $cat[1]; @endphp
                        <div class="flex items-end justify-center h-full" style="width: 18px">
                          <div style="width: 12px; height: {{ max(1, $pct) }}%; background: {{ $c }}"
                            title="{{ $glabel === 'M' ? 'Male' : ($glabel === 'F' ? 'Female' : 'Overall') }} {{ $cat[0] }}: {{ $pct }}%"></div>
                        </div>
                      @endforeach
                    </div>
                  @endforeach
                </div>
                {{-- M / F / O under each bar, with the category name boxed below (like the paper) --}}
                <div class="flex gap-2 mt-1">
                  @foreach ($cats as $cat)
                    <div>
                      <div class="flex">
                        <div class="text-[9px] text-gray-500 text-center border border-gray-200" style="width: 18px">M</div>
                        <div class="text-[9px] text-gray-500 text-center border border-l-0 border-gray-200" style="width: 18px">F</div>
                        <div class="text-[9px] text-gray-500 text-center border border-l-0 border-gray-200" style="width: 18px">O</div>
                      </div>
                      <div class="text-[10px] font-medium text-gray-700 text-center border border-t-0 border-gray-200 py-0.5">{{ $cat[0] }}</div>
                    </div>
                  @endforeach
                </div>
                <div class="mt-1.5 text-xs font-semibold text-gray-800">{{ $row['subject']->name }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-gray-600">
        <span class="font-semibold">Key</span>
        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded" style="background:#4caf50"></span> Pass</span>
        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded" style="background:#ef6b7d"></span> Fail</span>
        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded" style="background:#7aa0d0"></span> Mastery</span>
        <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded" style="background:#e8913c"></span> Overall</span>
        <span class="text-gray-400">each group: Male &middot; Female &middot; Overall &middot; hover a bar for its value</span>
      </div>
    </div>
