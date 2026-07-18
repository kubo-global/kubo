<x-page title="Instructional hours">
  <div class="px-6 py-6 lg:px-8">

    <div class="mb-4">
      <nav class="text-sm text-gray-500">
        <a href="{{ route('instructional-hours.index') }}" class="text-indigo-600 hover:underline">Instructional hours</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <span class="text-gray-700">{{ $offering->displayName() }}</span>
      </nav>
    </div>

    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <p class="text-sm text-gray-600">Expected hours come from the timetable. Enter <b>Actual</b> and <b>Lost</b> hours per day.</p>
        <form method="GET" action="{{ route('instructional-hours.show', $offering) }}" class="flex items-end gap-2 mt-2">
          <div>
            <label for="month" class="block mb-1 text-xs text-gray-500">Month</label>
            <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}"
              class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
          </div>
          <button type="submit" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Go</button>
        </form>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('instructional-hours.pdf', ['offering' => $offering, 'month' => $month->format('Y-m')]) }}" target="_blank" rel="noopener"
          class="inline-flex items-center px-3 py-1.5 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-indigo-700 hover:bg-gray-50">Data sheet PDF</a>
        <a href="{{ route('instructional-hours.chart', ['offering' => $offering, 'month' => $month->format('Y-m')]) }}" target="_blank" rel="noopener"
          class="inline-flex items-center px-3 py-1.5 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-indigo-700 hover:bg-gray-50">Chart PDF</a>
      </div>
    </div>

    {{-- Each day saves on its own (a background request), so you fill in and save
         day by day instead of one big submit for the whole month. --}}
    <div>
      @forelse ($weeks as $weekNum => $rows)
        @php $sumE = collect($rows)->sum('expected'); $sumA = collect($rows)->sum('actual'); $sumL = collect($rows)->sum('lost'); @endphp
        <div class="mb-5 overflow-x-auto border border-gray-200 rounded-lg">
          <table class="min-w-[760px] w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
              <tr class="text-left">
                <th class="px-3 py-2 font-semibold border-b border-gray-200 w-40">Week {{ $weekNum }}</th>
                <th class="px-3 py-2 font-semibold text-right border-b border-gray-200 w-24">Expected</th>
                <th class="px-3 py-2 font-semibold border-b border-gray-200 w-28">Actual</th>
                <th class="px-3 py-2 font-semibold border-b border-gray-200 w-28">Lost</th>
                <th class="px-3 py-2 font-semibold border-b border-gray-200">Remarks</th>
                @if ($canEdit)<th class="px-3 py-2 border-b border-gray-200 w-28"></th>@endif
              </tr>
            </thead>
            <tbody>
              @foreach ($rows as $r)
                <tr>
                  <td class="px-3 py-2 border-b border-gray-100">
                    <span class="font-medium text-gray-900">{{ ucfirst(strtolower($r['day'])) }}</span>
                    <span class="ml-1 text-xs text-gray-500">{{ $r['date_short'] }}</span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-700 border-b border-gray-100">{{ $r['expected'] ? rtrim(rtrim(number_format($r['expected'], 2), '0'), '.') : '—' }}</td>
                  <td class="px-2 py-1.5 border-b border-gray-100">
                    <input type="number" step="0.25" min="0" data-field="actual" value="{{ $r['actual'] }}" @disabled(! $canEdit)
                      class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                  </td>
                  <td class="px-2 py-1.5 border-b border-gray-100">
                    <input type="number" step="0.25" min="0" data-field="lost" value="{{ $r['lost'] }}" @disabled(! $canEdit)
                      class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                  </td>
                  <td class="px-2 py-1.5 border-b border-gray-100">
                    <input type="text" data-field="remarks" value="{{ $r['remarks'] }}" @disabled(! $canEdit)
                      class="w-full px-2 py-1 text-sm border border-gray-200 rounded focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                  </td>
                  @if ($canEdit)
                    <td class="px-2 py-1.5 border-b border-gray-100 whitespace-nowrap">
                      <button type="button" data-date="{{ $r['date'] }}" onclick="saveDay(this)"
                        class="px-2.5 py-1 text-xs font-medium text-white bg-gray-800 rounded hover:bg-gray-900">Save</button>
                      <span class="ml-1 text-xs text-gray-400" data-status></span>
                    </td>
                  @endif
                </tr>
              @endforeach
              <tr class="bg-gray-50 font-semibold text-gray-800">
                <td class="px-3 py-2">Total</td>
                <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($sumE, 2), '0'), '.') ?: 0 }}</td>
                <td class="px-3 py-2">{{ $sumA ? rtrim(rtrim(number_format($sumA, 2), '0'), '.') : '' }}</td>
                <td class="px-3 py-2">{{ $sumL ? rtrim(rtrim(number_format($sumL, 2), '0'), '.') : '' }}</td>
                <td></td>
                @if ($canEdit)<td></td>@endif
              </tr>
            </tbody>
          </table>
        </div>
      @empty
        <p class="text-sm text-gray-500 italic">No weekdays in this month.</p>
      @endforelse
    </div>

    @if ($canEdit)
      <script>
        const IH = {
          url: @json(route('instructional-hours.save', $offering)),
          token: @json(csrf_token()),
          month: @json($month->format('Y-m')),
        };
        async function saveDay(btn) {
          const date = btn.dataset.date;
          const row = btn.closest('tr');
          const status = row.querySelector('[data-status]');
          const body = new FormData();
          body.append('_token', IH.token);
          body.append('month', IH.month);
          body.append('ajax', '1');
          row.querySelectorAll('input[data-field]').forEach(i => body.append(`rows[${date}][${i.dataset.field}]`, i.value));
          btn.disabled = true;
          if (status) { status.textContent = 'Saving…'; status.className = 'ml-1 text-xs text-gray-400'; }
          try {
            const res = await fetch(IH.url, { method: 'POST', body, headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error();
            if (status) { status.textContent = 'Saved'; status.className = 'ml-1 text-xs text-gray-600'; }
          } catch (e) {
            if (status) { status.textContent = 'Not saved'; status.className = 'ml-1 text-xs text-red-600'; }
          } finally {
            btn.disabled = false;
          }
        }
      </script>
    @endif
  </div>
</x-page>
