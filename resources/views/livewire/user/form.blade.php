<div class="flex overflow-hidden bg-white" x-data="{ tab: window.location.hash || '#profile',
  activeClasses: 'text-indigo-600 border-indigo-500 focus:text-indigo-800 focus:border-indigo-700',
  inactiveClasses: 'text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300 border-transparent'
}" x-init="
  $watch('tab', val => history.replaceState(null, '', val));
  if (tab !== '#profile' && tab !== '#skills') {
    var section = tab.replace('#', '');
    $wire.set('section', section);
  }
">
  <!-- Content area -->
  <div class="flex flex-col flex-1">
    <main class="flex-1 overflow-y-auto focus:outline-none" tabindex="0">
      {{-- One width for every tab: health and skills need the room, and switching
           tabs used to make the whole page jump wider. Same padding as the page
           header above, so the tabs line up with the title and their own content. --}}
      <div class="relative max-w-7xl px-4 mx-auto sm:px-6 lg:px-8">
        @if($method !== 'newStudent')
        <div>
          <div>
            {{-- Who am I looking at. The health record is not a tab here: it lives
                 under Health, so health work never drops you into another section. --}}
            <div class="flex flex-wrap items-center justify-between gap-4 pt-6">
              <x-pupil-identity :user="$user" />

              @can('view medical records')
                <a href="{{ route('health.pupil', $user->id) }}"
                  class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-800 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                  <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                  </svg>
                  Open health record
                </a>
              @endcan
            </div>

            <div class="pt-6">
              <div class="flex items-center border-b border-gray-200">
                <div class="flex-1 lg:block">
                  <div>
                    <nav class="flex -mb-px" role="tablist">
                      <button type="button" wire:click="$set('section', 'profile')" x-on:click="tab='#profile'"
                        role="tab" :aria-selected="tab === '#profile'"
                        :class="tab === '#profile' ? activeClasses : inactiveClasses"
                        class="px-1 py-4 text-sm font-medium leading-5 whitespace-no-wrap border-b-2 focus:outline-none ">
                        Personal details
                      </button>
                      <button type="button" wire:click="$set('section','contacts')" x-on:click="tab='#contacts'"
                        role="tab" :aria-selected="tab === '#contacts'"
                        :class="tab === '#contacts' ? activeClasses : inactiveClasses"
                        class="px-1 py-4 ml-8 text-sm font-medium leading-5 whitespace-no-wrap border-b-2 focus:outline-none ">
                        Contacts
                      </button>
                      <button type="button" wire:click="$set('section','records')" x-on:click="tab='#records'"
                        role="tab" :aria-selected="tab === '#records'"
                        :class="tab === '#records' ? activeClasses : inactiveClasses"
                        class="px-1 py-4 ml-8 text-sm font-medium leading-5 whitespace-no-wrap border-b-2 focus:outline-none">
                        School records
                      </button>
                      <button type="button" x-on:click="tab='#skills'"
                        role="tab" :aria-selected="tab === '#skills'"
                        :class="tab === '#skills' ? activeClasses : inactiveClasses"
                        class="px-1 py-4 ml-8 text-sm font-medium leading-5 whitespace-no-wrap border-b-2 focus:outline-none">
                        Skills
                      </button>
                    </nav>
                  </div>
                </div>
                <div x-show="tab === '#profile'">
                  @if ($section==="profile" && $allowEditing===false)
                    <x-button href='#' wire:click="makeProfileEditable"
                      class="inline-flex justify-center">
                      Edit details
                    </x-button>
                  @endif
                </div>
                {{-- No header button for contacts: the list itself carries an add
                     card, so you type the name where the contact will appear. --}}
              </div>
            </div>
          </div>
        </div>
        @endif
      </div>
      <div class="relative max-w-7xl px-4 mx-auto sm:px-6 lg:px-8">
        <div>
              {{-- One top margin for every tab's panel, so no panel has to remember
                   to space itself off the tab row. --}}
              <div class="pt-6 space-y-6">
                <div x-show="tab !== '#skills'">
                  @includeWhen($section=="profile",'livewire.user.profile')
                  @if ($section === 'contacts')
                    @livewire('pupil-contacts', ['user' => $user], key('pupil-contacts-'.$user->id))
                  @endif
                  @includeWhen($section=="records",'livewire.user.school_records')
                </div>
                <div x-show="tab === '#skills'" x-cloak wire:ignore>
                  @include('livewire.user.skills')
                </div>
                @includeWhen($section=="new",'livewire.user.new_student')
              </div>
        </div>
      </div>
    </main>
  </div>
</div>