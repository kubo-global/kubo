{{-- Expects $incomplete: a collection of ['subject','has','missing']. --}}
@if (!empty($incomplete) && count($incomplete))
  <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    <p class="font-semibold">Some subjects will not appear on the report yet</p>
    <p class="mt-1">They have marks in one column but not the other, so KUBO cannot total them. Add the missing marks (a common cause is an exam saved under the Test type):</p>
    <ul class="mt-1.5 space-y-0.5">
      @foreach ($incomplete as $item)
        <li>
          <span class="font-medium">{{ $item['subject'] }}</span>
          (has {{ implode(' + ', $item['has']) }}, missing {{ implode(' + ', $item['missing']) }})
        </li>
      @endforeach
    </ul>
  </div>
@endif
