{{-- The tab keeps the pupil's name (you may have several open); the page header
     says what this page is. --}}
<x-page :title="'Health | '.$student->first_name.' '.$student->last_name" heading="Health record">
  <div class="max-w-7xl px-4 mx-auto sm:px-6 lg:px-8">

    <a href="{{ route('health.index') }}"
      class="inline-flex items-center gap-1 mt-6 text-sm text-gray-600 hover:text-gray-900">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
      Back to the health desk
    </a>

    <div class="pt-4 pb-6">
      <x-pupil-identity :user="$student" />
    </div>

    @livewire('pupil-health', ['user' => $student])
  </div>
</x-page>
