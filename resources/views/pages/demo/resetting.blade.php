<x-page title="Demo is resetting">
  {{-- Shown while the demo database is being wiped and reseeded: the school does not
       exist for those few seconds, and this is what a visitor sees instead of the
       setup wizard. Reloads itself, so it clears as soon as the seed lands. --}}
  <meta http-equiv="refresh" content="8">

  <div class="max-w-lg px-4 py-24 mx-auto text-center">
    <div class="flex items-center justify-center w-12 h-12 mx-auto rounded-full bg-indigo-50">
      <svg class="w-6 h-6 text-indigo-700 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
      </svg>
    </div>

    <h1 class="mt-6 text-xl font-bold text-gray-900">The demo is resetting</h1>
    <p class="mt-2 text-gray-600">
      Fresh pupils, scores and health records are being seeded. It takes about half a minute,
      and this page reloads itself when the school is back.
    </p>
  </div>
</x-page>
