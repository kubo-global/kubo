<div>
  <div class="flow-root">
    <ul class="-mb-8">
      @foreach($user->enrollments->sortByDesc("offering_id") as $enrollment)
      <div class="mb-8">
        <h2 class="font-medium leading-5 text-indigo-700 text-md">
          {{ $enrollment->offering->displayName() }}
          <span class="text-sm text-gray-500">{{ $enrollment->offering->schoolyear->name }}</span>
        </h2>
        @foreach($enrollment->offering->schoolyear->terms as $term)
        <li>
          <div class="relative pt-4">
            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-indigo-200" aria-hidden="true"></span>
            <div class="relative flex space-x-3">
              <div>
                <span class="flex items-center justify-center w-8 h-8 bg-indigo-400 rounded-full ring-8 ring-white">
                  <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd"
                      d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                      clip-rule="evenodd"></path>
                  </svg>
                </span>
              </div>
              <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                <div>
                  <p class="text-sm text-gray-500">View term report for <a
                      href="{{ route('term-report.show', ['enrollmentId' => $enrollment->id, 'termId' => $term->id]) }}"
                      class="font-medium text-gray-900">{{ $term->name}}</a></p>
                </div>
              </div>
            </div>
          </div>
        </li>
        @endforeach
      </div>
      @endforeach
    </ul>
  </div>
</div>