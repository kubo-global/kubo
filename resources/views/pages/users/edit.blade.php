<x-page title="Edit User">
  <div class="w-full px-4 py-8 sm:w-3/4 sm:px-0 mx-auto">
    <h2 class="text-lg font-medium text-gray-900">Edit {{ $user->first_name }} {{ $user->last_name }}</h2>
    <p class="mt-1 text-sm text-gray-500 mb-6">Update name, email, or roles. Use the Reset password button on the listing to set a new password.</p>

    @if($errors->any())
    <div class="p-3 mb-4 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
      @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('users.update', $user) }}">
      @csrf @method('PUT')
      <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="first_name" class="block text-sm font-medium text-gray-700">First name</label>
            <input required type="text" name="first_name" id="first_name" value="{{ old('first_name', $user->first_name) }}"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
          <div>
            <label for="last_name" class="block text-sm font-medium text-gray-700">Last name</label>
            <input required type="text" name="last_name" id="last_name" value="{{ old('last_name', $user->last_name) }}"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
        </div>
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">Email (optional)</label>
          <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Roles</label>
          @php
            $currentRoles = old('roles', $user->getRoleNames()->all());
            $roleOptions = [
              'headmaster' => 'Headmaster',
              'admin' => 'Administration',
              'system_admin' => 'System admin',
              'teacher' => 'Teacher',
              'caregiver' => 'Caregiver',
              'assistant_coordinator' => 'Assistant coordinator',
            ];
          @endphp
          <div class="flex flex-col gap-2">
            @foreach($roleOptions as $role => $label)
            <label class="inline-flex items-center">
              <input type="checkbox" name="roles[]" value="{{ $role }}" {{ in_array($role, $currentRoles) ? 'checked' : '' }}
                class="h-4 w-4 rounded border-gray-300 text-gray-900">
              <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
            </label>
            @endforeach
          </div>
        </div>

        {{-- Employment record for the printable staff list. PRN/TIN/status/dates live on
             staff_profiles; gender + contact on the shared profile. --}}
        @php $sp = $user->staffProfile; $pf = $user->profile; @endphp
        <div class="pt-4 border-t border-gray-200">
          <label class="block text-sm font-medium text-gray-700 mb-1">Employment details</label>
          <p class="text-xs text-gray-500 mb-3">Shown on the printable Staff List. Leave blank if not applicable.</p>
          @php $inp = 'block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm'; @endphp
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="prn" class="block text-sm font-medium text-gray-700">PRN</label>
              <input type="text" name="prn" id="prn" value="{{ old('prn', $sp?->prn) }}" class="{{ $inp }}">
            </div>
            <div>
              <label for="tin" class="block text-sm font-medium text-gray-700">TIN No.</label>
              <input type="text" name="tin" id="tin" value="{{ old('tin', $sp?->tin) }}" class="{{ $inp }}">
            </div>
            <div>
              <label for="staff_status_id" class="block text-sm font-medium text-gray-700">Status</label>
              <select name="staff_status_id" id="staff_status_id" class="{{ $inp }}">
                <option value="">—</option>
                @foreach ($statuses as $st)
                  <option value="{{ $st->id }}" @selected((string) old('staff_status_id', $sp?->staff_status_id) === (string) $st->id)>{{ $st->label }}</option>
                @endforeach
              </select>
              @if ($statuses->isEmpty())
                <p class="mt-1 text-xs text-gray-400">No statuses yet — add them in <a href="{{ route('settings.index') }}#academic" class="underline">Settings</a>.</p>
              @endif
            </div>
            <div>
              <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
              <select name="gender" id="gender" class="{{ $inp }}">
                <option value="">—</option>
                <option value="M" @selected(old('gender', $pf?->gender) === 'M')>Male</option>
                <option value="F" @selected(old('gender', $pf?->gender) === 'F')>Female</option>
              </select>
            </div>
            <div>
              <label for="appointed_on" class="block text-sm font-medium text-gray-700">Date of appointment</label>
              <input type="date" name="appointed_on" id="appointed_on" value="{{ old('appointed_on', $sp?->appointed_on?->format('Y-m-d')) }}" class="{{ $inp }}">
            </div>
            <div>
              <label for="confirmed_on" class="block text-sm font-medium text-gray-700">Date of confirmation</label>
              <input type="date" name="confirmed_on" id="confirmed_on" value="{{ old('confirmed_on', $sp?->confirmed_on?->format('Y-m-d')) }}" class="{{ $inp }}">
            </div>
            <div>
              <label for="primary_phone" class="block text-sm font-medium text-gray-700">Contact phone</label>
              <input type="text" name="primary_phone" id="primary_phone" value="{{ old('primary_phone', $pf?->primary_phone) }}" class="{{ $inp }}">
            </div>
          </div>
        </div>

        @if ($offerings->isNotEmpty())
        <div class="pt-4 border-t border-gray-200">
          <label class="block text-sm font-medium text-gray-700 mb-1">Subject assignments</label>
          <p class="text-xs text-gray-500 mb-3">Tick the subjects this teacher is responsible for in each class. Used to gate grade entry — class principals and admin/headmaster always have access regardless.</p>
          <div class="space-y-3">
            @foreach ($offerings as $offering)
              @php $subjects = $subjectsByOffering[$offering->id] ?? collect(); @endphp
              @if ($subjects->isNotEmpty())
              <div class="p-3 bg-gray-50 border border-gray-200 rounded-md">
                <p class="text-xs font-semibold text-gray-700 mb-2">{{ $offering->grade->name ?? 'Unknown' }}</p>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                  @foreach ($subjects as $subject)
                    @php $pair = $offering->id . ':' . $subject->id; @endphp
                    <label class="inline-flex items-center text-sm text-gray-700">
                      <input type="checkbox" name="assignments[]" value="{{ $pair }}"
                        @checked(in_array($pair, old('assignments', $existingAssignments)))
                        class="h-4 w-4 rounded border-gray-300 text-gray-900">
                      <span class="ml-2">{{ $subject->name }}</span>
                    </label>
                  @endforeach
                </div>
              </div>
              @endif
            @endforeach
          </div>
        </div>
        @endif

        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
          <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
          <button type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">
            Save changes
          </button>
        </div>
      </div>
    </form>
  </div>
</x-page>
