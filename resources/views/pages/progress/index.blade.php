<x-page title="Student Progress">
  <div class="mx-6">
    <div class="min-w-full mt-8 align-middle">

      <p class="text-sm text-gray-500 mb-6">See how your students are doing with their exercises.</p>

      @if($offerings->isEmpty())
        <div class="my-8 p-8 text-center text-gray-500 border border-gray-200 rounded-md">
          No classes found for this school year.
        </div>
      @else
        <div class="space-y-3">
          @foreach($offerings as $offering)
          <a href="{{ route('progress.show', $offering) }}"
             class="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-lg hover:shadow-sm transition-shadow">
            <div>
              <p class="font-semibold text-gray-900">{{ $offering->grade->name ?? '' }} {{ $offering->name }}</p>
              <p class="text-sm text-gray-500">{{ $offering->students()->count() }} students</p>
            </div>
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
          </a>
          @endforeach
        </div>
      @endif

    </div>
  </div>
</x-page>
