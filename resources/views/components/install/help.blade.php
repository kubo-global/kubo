{{-- An expandable "Need help?" note for a wizard step. Native <details>, no JS. --}}
<details class="pt-4 mt-6 border-t border-gray-100">
  <summary class="inline-flex items-center gap-1.5 text-sm font-medium cursor-pointer select-none text-indigo-600 hover:text-indigo-700 marker:content-['']">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.55-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.28 2.575-3.006 2.907-.542.104-.994.54-.994 1.093V14m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    Need help with this step?
  </summary>
  <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $slot }}</p>
</details>
