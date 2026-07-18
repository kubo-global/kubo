<x-page :title="$incident->exists ? 'Edit incident' : 'New incident — ' . $student->getFullNameAttribute()">
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h2 class="text-lg font-medium text-gray-900">
      {{ $incident->exists ? 'Edit incident' : 'New incident for ' . $student->getFullNameAttribute() }}
    </h2>

    @if ($errors->any())
      <div class="my-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
        <ul class="list-disc pl-5">
          @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
      </div>
    @endif

    <form method="POST"
          action="{{ $incident->exists ? route('health.incidents.update', $incident) : route('health.incidents.store') }}"
          class="space-y-4 mt-6">
      @csrf
      @if ($incident->exists) @method('PUT') @else
        <input type="hidden" name="student" value="{{ $student->id }}">
      @endif

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">When</label>
          <input required type="datetime-local" name="occurred_at"
            value="{{ old('occurred_at', $incident->occurred_at?->format('Y-m-d\TH:i')) }}"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Location <span class="text-gray-500">(optional)</span></label>
          <input type="text" name="location" placeholder="e.g. Manjai, classroom 1"
            value="{{ old('location', $incident->location) }}"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Complaint</label>
        <textarea required name="complaint" rows="3" placeholder="What was the symptom or injury?"
          class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">{{ old('complaint', $incident->complaint) }}</textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Temperature <span class="text-gray-500">(°C, optional)</span></label>
        <input type="number" step="0.1" min="30" max="45" name="temperature"
          value="{{ old('temperature', $incident->temperature) }}"
          class="block w-32 px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
      </div>

      <fieldset>
        <legend class="block text-sm font-medium text-gray-700 mb-2">Action taken</legend>
        <div class="space-y-2">
          @foreach ([
            'first_aid_given' => 'First aid given',
            'parents_contacted' => 'Parents contacted',
            'sent_home' => 'Sent home',
            'taken_to_hospital' => 'Taken to hospital',
          ] as $field => $label)
            <label class="inline-flex items-center mr-6">
              <input type="hidden" name="{{ $field }}" value="0">
              <input type="checkbox" name="{{ $field }}" value="1"
                @checked(old($field, $incident->$field))
                class="h-4 w-4 rounded border-gray-300 text-gray-900">
              <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
            </label>
          @endforeach
        </div>
      </fieldset>

      <div>
        <label for="medication_given" class="block text-sm font-medium text-gray-700">Medication given <span class="text-gray-500">(optional)</span></label>
        <input type="text" id="medication_given" name="medication_given" maxlength="191"
          placeholder="What was given, and how much? e.g. Paracetamol 250 mg"
          value="{{ old('medication_given', $incident->medication_given) }}"
          class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
      </div>

      <div>
        <label for="action_taken" class="block text-sm font-medium text-gray-700">Action / remarks <span class="text-gray-500">(optional)</span></label>
        <textarea id="action_taken" name="action_taken" rows="3" placeholder="Describe what was done"
          class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">{{ old('action_taken', $incident->action_taken) }}</textarea>
      </div>

      <fieldset class="p-3 border border-gray-200 rounded-md bg-gray-50">
        <legend class="px-1 text-sm font-medium text-gray-700">Follow-up</legend>
        <label class="inline-flex items-center">
          <input type="hidden" name="needs_follow_up" value="0">
          <input type="checkbox" name="needs_follow_up" value="1"
            @checked(old('needs_follow_up', $incident->exists && $incident->isOpen()))
            class="h-4 w-4 rounded border-gray-300 text-gray-900">
          <span class="ml-2 text-sm text-gray-700">Still open, needs follow-up</span>
        </label>
        <p class="mt-2 text-xs text-gray-500">
          Open incidents stay on the incident worklist until someone closes them. Leave this unticked if the incident
          was handled there and then.
          @if ($incident->exists && $incident->closed_on)
            <span class="block mt-1">Closed on {{ $incident->closed_on->format('d M Y') }}.</span>
          @endif
        </p>
      </fieldset>

      <div class="flex items-center justify-between pt-4 border-t border-gray-200">
        <a href="{{ route('students.show', $student) . '#health' }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
        <div class="flex items-center gap-4">
          @if ($incident->exists)
            <form method="POST" action="{{ route('health.incidents.destroy', $incident) }}" class="inline"
                  onsubmit="return confirm('Delete this incident?')">
              @csrf @method('DELETE')
              <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
            </form>
          @endif
          <button type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">
            {{ $incident->exists ? 'Save changes' : 'Record incident' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</x-page>
