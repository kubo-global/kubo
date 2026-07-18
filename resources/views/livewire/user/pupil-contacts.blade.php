@php
  $input = 'block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900';
  $label = 'block text-sm font-medium text-gray-700';
@endphp

<div>
  @if (session('contact-saved'))
    <div class="p-3 mb-4 text-sm border rounded-md text-emerald-800 border-emerald-200 bg-emerald-50">
      {{ session('contact-saved') }}
    </div>
  @endif

  {{-- items-start: a card keeps its own height instead of stretching to match the
       open form next to it. --}}
  <div class="grid grid-cols-1 gap-4 items-start sm:grid-cols-2 lg:grid-cols-3">

    @foreach ($contacts as $contact)
      <a href="{{ route('contacts.show', ['student' => $user, 'contact' => $contact]) }}"
        class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-indigo-300 hover:bg-indigo-50">
        <span class="flex items-center justify-center w-12 h-12 text-sm font-semibold text-white uppercase bg-indigo-500 rounded-full shrink-0"
          aria-hidden="true">
          {{ $contact->getInitials() }}
        </span>
        <span class="min-w-0">
          <span class="block font-medium text-gray-900 truncate">{{ $contact->first_name }} {{ $contact->last_name }}</span>
          @if ($contact->relation)
            <span class="block text-sm text-gray-600">{{ $contact->relation }}</span>
          @endif
          @if ($contact->primary_phone)
            <span class="block text-sm text-gray-600">{{ $contact->primary_phone }}</span>
          @endif
          @if ($contact->email)
            <span class="block text-sm text-gray-500 truncate">{{ $contact->email }}</span>
          @endif
        </span>
      </a>
    @endforeach

    {{-- The add card sits where the next contact would be: click it and type.
         Only staff may add one, so nobody else is shown a card that refuses them. --}}
    @if (!$canManage)
      @if ($contacts->isEmpty())
        <p class="text-sm text-gray-500 sm:col-span-2 lg:col-span-3">No contacts recorded for this pupil.</p>
      @endif
    @elseif (!$adding)
      <button type="button" wire:click="start"
        class="flex flex-col items-center justify-center gap-1 p-6 text-gray-600 border-2 border-gray-300 border-dashed rounded-lg min-h-32 hover:border-indigo-400 hover:text-indigo-700 hover:bg-indigo-50">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span class="text-sm font-medium">Add a contact</span>
      </button>
    @else
      <form wire:submit="save"
        class="p-4 bg-white border-2 border-indigo-300 rounded-lg shadow-sm sm:col-span-2 lg:col-span-1">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1">
          <div>
            <label for="firstName" class="{{ $label }}">First name</label>
            <input id="firstName" type="text" wire:model="firstName" autofocus class="{{ $input }}">
            @error('firstName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="lastName" class="{{ $label }}">Last name</label>
            <input id="lastName" type="text" wire:model="lastName" class="{{ $input }}">
            @error('lastName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="relation" class="{{ $label }}">Relation</label>
            <input id="relation" type="text" list="contact-relations" placeholder="e.g. Mother"
              wire:model="relation" class="{{ $input }}">
            <datalist id="contact-relations">
              @foreach (['Mother', 'Father', 'Grandmother', 'Grandfather', 'Aunt', 'Uncle', 'Guardian', 'Sister', 'Brother'] as $r)
                <option value="{{ $r }}"></option>
              @endforeach
            </datalist>
          </div>
          <div>
            <label for="primaryPhone" class="{{ $label }}">Phone</label>
            <input id="primaryPhone" type="tel" inputmode="tel" wire:model="primaryPhone" class="{{ $input }}">
          </div>
        </div>

        <div class="flex items-center justify-end gap-4 mt-4">
          <button type="button" wire:click="cancel" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
          <button type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md shadow-sm hover:bg-gray-900">
            <span wire:loading.remove wire:target="save">Save contact</span>
            <span wire:loading wire:target="save">Saving...</span>
          </button>
        </div>
        <p class="mt-2 text-xs text-gray-500">
          Saved straight away. Open the contact afterwards to add an address, e-mail or a second number.
        </p>
      </form>
    @endif
  </div>
</div>
