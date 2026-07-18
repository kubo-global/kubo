<x-page title="Health report detail">
  <div class="px-4 py-12 mx-auto max-w-7xl sm:px-6 lg:px-8">
    <!-- We've used 3xl here, but feel free to try other max-widths based on your needs -->
    <div class="max-w-3xl mx-auto">
      <div class="overflow-hidden bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 sm:flex sm:items-start sm:justify-between">
          <div>
            <h3 class="text-lg font-medium leading-6 text-gray-900">{{$healthReport->user->getFullNameAttribute()}}</h3>
            <p class="max-w-2xl mt-1 text-sm text-gray-500">Health report created on
              {{$healthReport->created_at->format('d-m-Y')}}</p>
          </div>
          <div class="flex gap-3 mt-3 sm:mt-0">
            <a href="{{ route('health.pupil', $healthReport->user_id) }}"
               class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
              Full health record
            </a>
            <a href="{{ route('health.edit', $healthReport) }}"
               class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
              Edit
            </a>
          </div>
        </div>
        <div class="border-t border-gray-200">
          <dl>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Full name</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                {{$healthReport->user->getFullNameAttribute()}}</dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Date of birth</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$healthReport->user->profile->birth_date}}
                <span class="text-gray-500">({{$healthReport->user->getAge()}} years old)</span></dd>
            </div>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">General condition</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$healthReport->general_condition}}</dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Wounds and bruises</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                {{$healthReport->wound_and_bruise_observations}}</dd>
            </div>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Height</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$healthReport->height_in_cm}} centimeters
              </dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Weight</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{ $healthReport->weight_kg !== null ? number_format($healthReport->weight_kg, 1).' kg' : '—' }}</dd>
            </div>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Teeth condition</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$healthReport->teeth_condition}}</dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Eye condition</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$healthReport->eyes_condition}}</dd>
            </div>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Ear condition</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$healthReport->ears_condition}}</dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Hair condition</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$healthReport->hair_condition}}</dd>
            </div>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Nail condition</dt>
              <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{$healthReport->nails_condition}}</dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt class="text-sm font-medium text-gray-500">Worm treatment received?</dt>
              @if ($healthReport->worm_treatment_received === 0)
              <dd class="px-1 py-1 mt-1 text-sm text-center text-red-900 bg-red-200 rounded-full sm:col-span-2 sm:mt-0">
                Treatment not received</dd>
              @elseif($healthReport->worm_treatment_received === null)
              <dd class="px-1 py-1 mt-1 text-sm text-center text-indigo-800 bg-indigo-100 rounded-full sm:col-span-2 sm:mt-0">
                Status unknown</dd>
              @elseif($healthReport->worm_treatment_received === 1)
              <dd
                class="px-1 py-1 mt-1 text-sm text-center text-green-800 bg-green-100 rounded-full sm:col-span-2 sm:mt-0">
                Treatment received</dd>
              @endif
            </div>
            {{-- Once-true milestones (vaccines, first menstruation) live on
                 the student now, not on each report. They're shown on the
                 student's Health tab; the report only captures point-in-time
                 observations. --}}
          </dl>
        </div>
      </div>
    </div>
  </div>
</x-page>