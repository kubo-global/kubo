@extends('install._layout')

@section('content')
  <h2 class="text-lg font-bold text-gray-900">Your school year</h2>
  <p class="mt-1 text-sm text-gray-500">The school year you're starting KUBO with. We'll split it into three terms, all editable later.</p>

  <form method="POST" action="{{ route('install.structure.store') }}" class="mt-6 space-y-5">
    @csrf

    <div>
      <label for="year_name" class="block text-sm font-medium text-gray-700">School year name</label>
      <input id="year_name" name="year_name" type="text" required value="{{ old('year_name', $data['name'] ?? '') }}"
        class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label for="start" class="block text-sm font-medium text-gray-700">Starts</label>
        <input id="start" name="start" type="date" required value="{{ old('start', $data['start'] ?? '') }}"
          class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
      </div>
      <div>
        <label for="end" class="block text-sm font-medium text-gray-700">Ends</label>
        <input id="end" name="end" type="date" required value="{{ old('end', $data['end'] ?? '') }}"
          class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
      </div>
    </div>

    {{-- Terms: prefilled with an even split, editable here, kept in sync when the year dates change. --}}
    <fieldset id="terms-block">
      <legend class="text-sm font-medium text-gray-700">Terms</legend>
      <p class="mt-1 text-xs text-gray-500">Usually three equal terms. Adjust the dates if your school's differ.</p>
      <div class="mt-3 space-y-2">
        @foreach ($terms as $i => $term)
          <div class="grid grid-cols-12 gap-2">
            <input type="text" name="terms[{{ $i }}][name]" value="{{ old('terms.'.$i.'.name', $term['name']) }}" required aria-label="Term {{ $i + 1 }} name"
              class="col-span-4 px-2 py-1.5 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            <input type="date" name="terms[{{ $i }}][start]" value="{{ old('terms.'.$i.'.start', $term['start']) }}" required aria-label="Term {{ $i + 1 }} start"
              class="col-span-4 px-2 py-1.5 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            <input type="date" name="terms[{{ $i }}][end]" value="{{ old('terms.'.$i.'.end', $term['end']) }}" required aria-label="Term {{ $i + 1 }} end"
              class="col-span-4 px-2 py-1.5 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
          </div>
        @endforeach
      </div>
    </fieldset>

    <script>
    (function () {
      var start = document.getElementById('start'), end = document.getElementById('end');
      if (!start || !end) return;
      var fmt = function (d) { return d.toISOString().slice(0, 10); };
      function recompute() {
        if (!start.value || !end.value) return;
        var s = new Date(start.value), e = new Date(end.value);
        if (e <= s) return;
        var days = Math.max(1, Math.round((e - s) / 86400000));
        var t1 = new Date(s.getTime() + Math.floor(days / 3) * 86400000);
        var t2 = new Date(s.getTime() + Math.floor(days * 2 / 3) * 86400000);
        var d = document.querySelectorAll('#terms-block input[type=date]');
        if (d.length < 6) return;
        d[0].value = start.value; d[1].value = fmt(t1);
        d[2].value = fmt(t1);     d[3].value = fmt(t2);
        d[4].value = fmt(t2);     d[5].value = end.value;
      }
      start.addEventListener('change', recompute);
      end.addEventListener('change', recompute);
    })();
    </script>

    <div class="flex items-center justify-between pt-2">
      <a href="{{ route('install.school') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">&larr; Back</a>
      <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg bg-indigo-600 hover:bg-indigo-700">Continue &rarr;</button>
    </div>
  </form>

  <x-install.help>
    This is the academic year you're setting up, with its start and end dates (for example 1 September 2026
    to 31 August 2027). KUBO divides it into three terms, shown above so you can adjust the dates if your
    school's differ. You can change any of this later in Settings.
  </x-install.help>
@endsection
