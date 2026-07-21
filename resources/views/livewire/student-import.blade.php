<div class="px-6 py-6 lg:px-8 max-w-4xl">

  <nav class="text-sm text-gray-500 mb-4">
    <a href="{{ route('students.index') }}" class="text-indigo-600 hover:underline">Students</a>
    <span class="mx-1">&rsaquo;</span>
    <span class="text-gray-700">Import a class list</span>
  </nav>

  <h1 class="text-lg font-semibold text-gray-900">Import a class list</h1>
  <p class="text-sm text-gray-500 mb-5 max-w-2xl">
    Upload a CSV with one pupil per line: <code class="text-xs bg-gray-100 px-1 rounded">first name, last name, gender (m/f, optional), birth date (optional)</code>.
    Nothing is saved until you confirm the preview below.
  </p>

  @if ($imported)
    <div class="mb-5 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
      Imported {{ $importedCount }} pupil{{ $importedCount === 1 ? '' : 's' }} into the class. They appear on the Students page and the class's scorebook right away.
    </div>
  @endif

  <div class="flex flex-wrap items-end gap-4 mb-6">
    <label class="flex flex-col gap-1">
      <span class="text-xs font-medium text-gray-500">Class</span>
      <select wire:model.live="offeringId"
        class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        <option value="">Choose a class…</option>
        @foreach ($offerings as $o)
          <option value="{{ $o->id }}">{{ $o->grade->name }}{{ $o->name ? ' '.$o->name : '' }}</option>
        @endforeach
      </select>
    </label>

    <label class="flex flex-col gap-1">
      <span class="text-xs font-medium text-gray-500">Class list (CSV)</span>
      <input type="file" wire:model="file" accept=".csv,.txt"
        class="text-sm text-gray-700 file:mr-3 file:px-3 file:py-2 file:text-sm file:font-medium file:border-0 file:rounded-md file:bg-gray-900 file:text-white hover:file:bg-gray-800">
    </label>
    <div wire:loading wire:target="file" class="text-sm text-gray-500">Reading…</div>
  </div>
  @error('file')<p class="mb-4 text-sm text-red-600">{{ $message }}</p>@enderror

  @if ($rows)
    <div class="flex flex-wrap items-center gap-3 mb-3 text-sm">
      <span class="px-2 py-1 rounded bg-green-50 text-green-800 font-medium">{{ $okCount }} to add</span>
      @if ($skipCount)<span class="px-2 py-1 rounded bg-gray-100 text-gray-600">{{ $skipCount }} already enrolled</span>@endif
      @if ($errorCount)<span class="px-2 py-1 rounded bg-red-50 text-red-700 font-medium">{{ $errorCount }} with problems</span>@endif
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded-lg mb-4">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
            <th class="px-3 py-2 w-12">Line</th>
            <th class="px-3 py-2">First name</th>
            <th class="px-3 py-2">Last name</th>
            <th class="px-3 py-2 w-16">Gender</th>
            <th class="px-3 py-2 w-28">Birth date</th>
            <th class="px-3 py-2">Result</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @foreach ($rows as $row)
            <tr wire:key="row-{{ $row['line'] }}" class="{{ $row['status'] === 'error' ? 'bg-red-50/60' : ($row['status'] === 'skip' ? 'bg-gray-50 text-gray-500' : '') }}">
              <td class="px-3 py-1.5 text-gray-400">{{ $row['line'] }}</td>
              <td class="px-3 py-1.5 font-medium">{{ $row['first'] }}</td>
              <td class="px-3 py-1.5 font-medium">{{ $row['last'] }}</td>
              <td class="px-3 py-1.5">{{ $row['gender'] ? strtoupper($row['gender']) : '' }}</td>
              <td class="px-3 py-1.5">{{ $row['birth'] }}</td>
              <td class="px-3 py-1.5 {{ $row['status'] === 'error' ? 'text-red-700' : 'text-gray-500' }}">{{ $row['note'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if (! $offeringId)
      <p class="text-sm text-amber-700">Choose a class above to enable the import.</p>
    @elseif ($errorCount)
      <p class="text-sm text-red-700">Fix the {{ $errorCount }} problem line{{ $errorCount === 1 ? '' : 's' }} in the file and upload again — importing is disabled while any line has a problem, so a typo can't slip in.</p>
    @elseif ($okCount)
      <button type="button" wire:click="confirm" wire:loading.attr="disabled"
        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md shadow-sm hover:bg-indigo-700">
        Import {{ $okCount }} pupil{{ $okCount === 1 ? '' : 's' }} into this class
      </button>
    @endif
  @endif
</div>
