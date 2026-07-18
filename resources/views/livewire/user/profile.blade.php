@php
  $input = 'block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900';
  $label = 'block text-sm font-medium text-gray-700';

  $gender = match ($profile?->gender) { 'F' => 'Female', 'M' => 'Male', null, '' => null, default => $profile->gender };
  $born = $profile?->birth_date
    ? \Carbon\Carbon::parse($profile->birth_date)->format('j F Y').($profile->age() !== null ? ' ('.$profile->age().' years old)' : '')
    : null;
@endphp

<div>
  @if (session()->has('message'))
    @include('components.notifications.message', ['message' => session('message')])
  @endif

  @if ($errors->any())
    <div class="p-3 mb-4 text-sm text-red-700 border border-red-200 rounded bg-red-50">
      <ul class="pl-5 list-disc">
        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
      </ul>
    </div>
  @endif

  @if ($allowEditing !== true)
    {{-- Reading, not editing: a row per database column read like a table dump, so
         say the facts about a child instead, and be honest about what is missing. --}}
    @php
      $groups = [
        'About' => [
          'Born' => $born,
          'Gender' => $gender,
          'Tribe' => $profile?->tribe ? ucfirst($profile->tribe) : null,
        ],
        'How to reach the family' => [
          'Phone' => $profile?->primary_phone,
          'Address' => $profile?->address,
        ],
      ];
    @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      @foreach ($groups as $heading => $rows)
        <div class="p-5 bg-white border border-gray-200 rounded-lg shadow-sm">
          <h3 class="mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">{{ $heading }}</h3>
          <dl class="space-y-3">
            @foreach ($rows as $term => $value)
              <div class="flex items-baseline gap-4">
                <dt class="w-32 text-sm text-gray-500 shrink-0">{{ $term }}</dt>
                <dd class="text-sm {{ $value ? 'text-gray-900' : 'italic text-gray-400' }}">
                  {{ $value ?: 'Not recorded' }}
                </dd>
              </div>
            @endforeach
          </dl>
        </div>
      @endforeach
    </div>

    @if ($profile?->comment)
      <div class="p-5 mt-4 bg-white border border-gray-200 rounded-lg shadow-sm">
        <h3 class="mb-2 text-sm font-semibold tracking-wide text-gray-500 uppercase">Note</h3>
        <p class="text-sm text-gray-800 whitespace-pre-line">{{ $profile->comment }}</p>
      </div>
    @endif

  @else
    {{-- Editing. The name lives here too: it is the one place it can be corrected. --}}
    <form wire:submit.prevent="updateProfile"
      class="p-5 bg-white border border-gray-200 rounded-lg shadow-sm">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label for="fname" class="{{ $label }}">First name</label>
          <input id="fname" type="text" wire:model.blur="firstName" class="{{ $input }}">
        </div>
        <div>
          <label for="lname" class="{{ $label }}">Last name</label>
          <input id="lname" type="text" wire:model.blur="lastName" class="{{ $input }}">
        </div>
        <div>
          <label for="gender" class="{{ $label }}">Gender</label>
          <select id="gender" wire:model.blur="gender" class="{{ $input }}">
            <option value="">—</option>
            <option value="F">Female</option>
            <option value="M">Male</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div>
          <label for="birthday" class="{{ $label }}">Date of birth</label>
          <input id="birthday" type="date" wire:model.blur="birthDate" class="{{ $input }}">
        </div>
        <div>
          <label for="tribe" class="{{ $label }}">Tribe</label>
          <input id="tribe" type="text" wire:model.blur="tribe" class="{{ $input }}">
        </div>
        <div>
          <label for="phone" class="{{ $label }}">Phone</label>
          <input id="phone" type="tel" inputmode="tel" wire:model.blur="primaryPhone" class="{{ $input }}">
        </div>
        <div class="sm:col-span-2">
          <label for="address" class="{{ $label }}">Address</label>
          <textarea id="address" rows="2" wire:model.blur="address" class="{{ $input }}"></textarea>
        </div>
        <div class="sm:col-span-2">
          <label for="comment" class="{{ $label }}">Note <span class="text-gray-500">(optional)</span></label>
          <textarea id="comment" rows="2" wire:model.blur="comment" class="{{ $input }}"></textarea>
        </div>
      </div>

      <div class="flex items-center justify-end gap-4 pt-4 mt-4 border-t border-gray-200">
        <button type="button" wire:click="cancelProfileEditing" class="text-sm text-gray-500 hover:text-gray-700">
          Cancel
        </button>
        <button type="submit"
          class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md shadow-sm hover:bg-gray-900">
          Save changes
        </button>
      </div>
    </form>
  @endif
</div>
