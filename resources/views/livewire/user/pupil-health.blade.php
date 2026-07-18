@php
  $input = 'block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900';
  $label = 'block text-sm font-medium text-gray-700';
  $conditions = ['Poor', 'Good', 'Excellent'];
@endphp

<div>

  {{-- The charts are SVG that Alpine injects, so Livewire's morph would wipe them
       on every update (closing an incident, opening the form). wire:ignore keeps
       Livewire out of that subtree; the key changes only when a checkup is added
       or edited, and a new key means a new node, which Alpine draws afresh. --}}
  <div wire:ignore
    wire:key="growth-{{ $user->healthReports->count() }}-{{ optional($user->healthReports->max('updated_at'))->timestamp }}">
    @include('livewire.user.growth-chart')
  </div>

  @if ($milestone)
    <h3 class="mt-8 mb-4 text-sm font-medium text-gray-700">Milestones</h3>
    <dl class="grid grid-cols-2 mb-8 text-sm gap-x-6 gap-y-2 sm:grid-cols-3">
      @foreach ([
        'first_menstruated_on' => 'First menstruation',
        'hep_a_received_on' => 'Hep A vaccine',
        'polio_received_on' => 'Polio vaccine',
        'tetanus_received_on' => 'Tetanus vaccine',
        'yellow_fever_received_on' => 'Yellow fever vaccine',
      ] as $col => $text)
        <div>
          <dt class="text-gray-500">{{ $text }}</dt>
          <dd class="text-gray-900">
            @if ($milestone->$col)
              {{ $milestone->$col->format('d M Y') }}
            @else
              <span class="text-gray-500">not recorded</span>
            @endif
          </dd>
        </div>
      @endforeach
    </dl>
  @endif

  {{-- ============ Record an entry, without leaving the record ============ --}}
  <div class="flex items-center justify-between mt-8 mb-4">
    <h3 class="text-sm font-medium text-gray-700">Timeline</h3>
    @if (!$type)
      <div class="flex flex-wrap gap-2">
        @foreach (\App\Livewire\PupilHealth::LABELS as $key => $text)
          <button type="button" wire:click="start('{{ $key }}')"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
            + {{ $text }}
          </button>
        @endforeach
      </div>
    @endif
  </div>

  @if (session('health-saved'))
    <div class="p-3 mb-4 text-sm border rounded-md text-emerald-800 border-emerald-200 bg-emerald-50">
      {{ session('health-saved') }}
    </div>
  @endif

  @if ($type)
    <form wire:submit="save" class="p-4 mb-6 bg-white border border-gray-300 shadow-sm rounded-xl">
      <h4 class="text-sm font-semibold text-gray-900">
        @if ($type === 'follow-up')
          Follow-up
        @else
          {{ $editingId ? 'Edit' : 'New' }} {{ strtolower(\App\Livewire\PupilHealth::LABELS[$type]) }}
        @endif
        <span class="font-normal text-gray-500">for {{ $user->first_name }} {{ $user->last_name }}</span>
      </h4>

      @if ($errors->any())
        <div class="p-3 mt-3 text-sm text-red-700 border border-red-200 rounded-md bg-red-50">
          <ul class="pl-5 list-disc">
            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
          </ul>
        </div>
      @endif

      {{-- ---------- Checkup ---------- --}}
      @if ($type === 'checkup')
        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
          <div>
            <label for="height" class="{{ $label }}">Height (cm)</label>
            <input id="height" type="number" min="40" max="220" step="1" wire:model="height" class="{{ $input }}">
          </div>
          <div>
            <label for="weight" class="{{ $label }}">Weight (kg)</label>
            <input id="weight" type="number" min="3" max="150" step="0.1" wire:model="weight" class="{{ $input }}">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4 sm:grid-cols-5">
          @foreach (['teeth' => 'Teeth', 'eyes' => 'Eyes', 'ears' => 'Ears', 'hair' => 'Hair', 'nails' => 'Nails'] as $field => $text)
            <div>
              <label for="{{ $field }}" class="{{ $label }}">{{ $text }}</label>
              <select id="{{ $field }}" wire:model="{{ $field }}" class="{{ $input }}">
                <option value="">—</option>
                @foreach ($conditions as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
              </select>
            </div>
          @endforeach
        </div>

        <div class="mt-4">
          <label for="generalCondition" class="{{ $label }}">General condition</label>
          <textarea id="generalCondition" rows="2" wire:model="generalCondition" class="{{ $input }}"></textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
          <div>
            <label for="woundObservations" class="{{ $label }}">Wounds and bruises</label>
            <input id="woundObservations" type="text" wire:model="woundObservations" class="{{ $input }}">
          </div>
          <div>
            <label for="wormTreatment" class="{{ $label }}">Worm treatment received</label>
            <select id="wormTreatment" wire:model="wormTreatment" class="{{ $input }}">
              <option value="">Unknown</option>
              <option value="1">Yes</option>
              <option value="0">No</option>
            </select>
          </div>
        </div>

        @if (!$editingId)
          <fieldset class="p-3 mt-4 border border-gray-200 rounded-md bg-gray-50">
            <legend class="px-1 text-sm font-medium text-gray-700">Recorded today, if it happened</legend>
            <p class="mb-2 text-xs text-gray-500">Once-true facts. They are dated today and never asked again.</p>
            <div class="flex flex-wrap gap-4">
              @foreach ([
                'menstruated' => 'First menstruation',
                'hep-a-vax' => 'Hep A vaccine',
                'polio-vax' => 'Polio vaccine',
                'tetanus-vax' => 'Tetanus vaccine',
                'yellow-fever-vax' => 'Yellow fever vaccine',
              ] as $key => $text)
                <label class="inline-flex items-center">
                  <input type="checkbox" value="1" wire:model="milestones.{{ $key }}"
                    class="w-4 h-4 border-gray-300 rounded text-gray-900">
                  <span class="ml-2 text-sm text-gray-700">{{ $text }}</span>
                </label>
              @endforeach
            </div>
          </fieldset>
        @endif
      @endif

      {{-- ---------- Incident ---------- --}}
      @if ($type === 'incident')
        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-3">
          <div>
            <label for="when" class="{{ $label }}">When</label>
            <input id="when" type="datetime-local" wire:model="when" class="{{ $input }}">
          </div>
          <div>
            <label for="location" class="{{ $label }}">Location</label>
            <input id="location" type="text" placeholder="e.g. Playground" wire:model="location" class="{{ $input }}">
          </div>
          <div>
            <label for="temperature" class="{{ $label }}">Temperature (°C)</label>
            <input id="temperature" type="number" step="0.1" min="30" max="45" wire:model="temperature" class="{{ $input }}">
          </div>
        </div>

        <div class="mt-4">
          <label for="complaint" class="{{ $label }}">Complaint</label>
          <textarea id="complaint" rows="2" placeholder="What was the symptom or injury?" wire:model="complaint" class="{{ $input }}"></textarea>
        </div>

        <fieldset class="mt-4">
          <legend class="mb-2 {{ $label }}">Action taken</legend>
          <div class="flex flex-wrap gap-4">
            @foreach ([
              'firstAidGiven' => 'First aid given',
              'parentsContacted' => 'Parents contacted',
              'sentHome' => 'Sent home',
              'takenToHospital' => 'Taken to hospital',
            ] as $field => $text)
              <label class="inline-flex items-center">
                <input type="checkbox" wire:model="{{ $field }}" class="w-4 h-4 border-gray-300 rounded text-gray-900">
                <span class="ml-2 text-sm text-gray-700">{{ $text }}</span>
              </label>
            @endforeach
          </div>
        </fieldset>

        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
          <div>
            <label for="medicationGiven" class="{{ $label }}">Medication given</label>
            <input id="medicationGiven" type="text" placeholder="e.g. Paracetamol 250 mg" wire:model="medicationGiven" class="{{ $input }}">
          </div>
          <div>
            <label for="actionTaken" class="{{ $label }}">Action / remarks</label>
            <input id="actionTaken" type="text" placeholder="What was done" wire:model="actionTaken" class="{{ $input }}">
          </div>
        </div>

        <label class="inline-flex items-center mt-4">
          <input type="checkbox" wire:model="needsFollowUp" class="w-4 h-4 border-gray-300 rounded text-gray-900">
          <span class="ml-2 text-sm text-gray-700">Still open, needs follow-up</span>
        </label>
        <p class="mt-1 text-xs text-gray-500">Open incidents stay on the health desk worklist until someone closes them.</p>
      @endif

      {{-- ---------- Wound case ---------- --}}
      @if ($type === 'wound')
        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
          <div>
            <label for="when" class="{{ $label }}">Opened on</label>
            <input id="when" type="date" wire:model="when" class="{{ $input }}">
          </div>
          <div>
            <label for="diagnosis" class="{{ $label }}">Diagnosis</label>
            <input id="diagnosis" type="text" placeholder="e.g. Cut on the knee" wire:model="diagnosis" class="{{ $input }}">
          </div>
        </div>
        <div class="mt-4">
          <label for="firstVisitTreatment" class="{{ $label }}">First treatment <span class="text-gray-500">(optional)</span></label>
          <textarea id="firstVisitTreatment" rows="2" placeholder="Cleaned and dressed the wound" wire:model="firstVisitTreatment" class="{{ $input }}"></textarea>
          <p class="mt-1 text-xs text-gray-500">Further visits are added on the case itself.</p>
        </div>
      @endif

      {{-- ---------- Follow-up on an incident ---------- --}}
      @if ($type === 'follow-up')
        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-3">
          <div>
            <label for="when" class="{{ $label }}">Checked back on</label>
            <input id="when" type="date" wire:model="when" class="{{ $input }}">
          </div>
        </div>
        <div class="mt-4">
          <label for="content" class="{{ $label }}">What did you find?</label>
          <textarea id="content" rows="2" placeholder="e.g. Elbow still sore, mother came to collect her"
            wire:model="content" class="{{ $input }}"></textarea>
          <p class="mt-1 text-xs text-gray-500">
            The incident stays open until you mark it as closed, so it keeps showing on the health desk.
          </p>
        </div>
      @endif

      {{-- ---------- Medical note ---------- --}}
      @if ($type === 'note')
        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-3">
          <div>
            <label for="when" class="{{ $label }}">Date</label>
            <input id="when" type="date" wire:model="when" class="{{ $input }}">
          </div>
          <div>
            <label for="location" class="{{ $label }}">Location</label>
            <input id="location" type="text" wire:model="location" class="{{ $input }}">
          </div>
          <div>
            <label for="temperature" class="{{ $label }}">Temperature (°C)</label>
            <input id="temperature" type="number" step="0.1" min="30" max="45" wire:model="temperature" class="{{ $input }}">
          </div>
        </div>
        <div class="mt-4">
          <label for="content" class="{{ $label }}">Note</label>
          <textarea id="content" rows="3" wire:model="content" class="{{ $input }}"></textarea>
        </div>
      @endif

      <div class="flex items-center justify-end gap-4 pt-4 mt-4 border-t border-gray-200">
        <button type="button" wire:click="cancel" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
        <button type="submit"
          class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">
          <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Save' }}</span>
          <span wire:loading wire:target="save">Saving...</span>
        </button>
      </div>
    </form>
  @endif

  {{-- ============ Timeline ============ --}}
  @if ($entries->isEmpty())
    <div class="p-6 text-sm italic text-center text-gray-500 border border-gray-200 border-dashed rounded-md">
      No health entries yet for this pupil.
    </div>
  @else
    <div class="mb-8 space-y-2">
      @foreach ($entries as $entry)
        @switch($entry['type'])
          @case('checkup')
            @php $r = $entry['model']; @endphp
            <div class="flex items-start justify-between gap-4 p-3 bg-white border border-gray-200 rounded-md">
              <div class="flex-1">
                <div class="flex items-center gap-2 text-xs text-gray-500">
                  <span class="inline-block px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-medium">Checkup</span>
                  <span>{{ $entry['when']->format('d M Y') }}</span>
                </div>
                <div class="mt-1 text-sm text-gray-700">
                  @if ($r->height_in_cm) Height: {{ $r->height_in_cm }} cm.@endif
                  @if ($r->weight_kg !== null) Weight: {{ number_format($r->weight_kg, 1) }} kg.@endif
                  @if ($r->general_condition) {{ $r->general_condition }}@endif
                </div>
              </div>
              <button type="button" wire:click="edit('checkup', {{ $r->id }})"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm shrink-0 hover:bg-gray-50">
                Edit
              </button>
            </div>
            @break

          @case('incident')
            @php $i = $entry['model']; @endphp
            <div class="flex items-start justify-between gap-4 p-3 border rounded-md border-amber-200 bg-amber-50">
              <div class="flex-1">
                <div class="flex items-center gap-2 text-xs text-gray-600">
                  <span class="inline-block px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">Incident</span>
                  <span>{{ $entry['when']->format('d M Y, H:i') }}</span>
                  @if ($i->isOpen())
                    <span class="inline-block px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 font-medium">open · follow up</span>
                  @endif
                  @if ($i->location)<span class="text-gray-500">· {{ $i->location }}</span>@endif
                  @if ($i->temperature)<span class="text-gray-500">· {{ $i->temperature }}°C</span>@endif
                </div>
                <p class="mt-1 text-sm text-gray-800">{{ $i->complaint }}</p>
                @php $action = $i->actionLabel(); @endphp
                @if ($action)<p class="mt-1 text-xs text-amber-800">→ {{ $action }}</p>@endif
                @if ($i->action_taken)<p class="mt-1 text-xs text-gray-600">{{ $i->action_taken }}</p>@endif

                @if ($i->followUps->isNotEmpty())
                  <ul class="mt-2 space-y-1 text-xs text-gray-700">
                    @foreach ($i->followUps as $f)
                      <li>
                        <span class="text-gray-500">{{ $f->noted_on->format('d M') }}:</span> {{ $f->note }}
                      </li>
                    @endforeach
                  </ul>
                @endif

                @if ($i->isOpen())
                  <button type="button" wire:click="followUp({{ $i->id }})"
                    class="inline-flex items-center px-4 py-2 mt-3 text-sm font-medium bg-white border rounded-md shadow-sm text-amber-900 border-amber-300 hover:bg-amber-50">
                    + Add follow-up
                  </button>
                @endif
              </div>
              <div class="flex flex-col items-end gap-2 whitespace-nowrap">
                @if ($i->isOpen())
                  <button type="button" wire:click="closeIncident({{ $i->id }})"
                    class="px-4 py-2 text-sm font-medium border rounded-md shadow-sm text-amber-900 border-amber-300 bg-amber-100 hover:bg-amber-200">
                    Mark as closed
                  </button>
                @endif
                <button type="button" wire:click="edit('incident', {{ $i->id }})"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                  Edit
                </button>
              </div>
            </div>
            @break

          @case('wound')
            @php $c = $entry['model']; @endphp
            <div class="p-3 border border-red-200 rounded-md bg-red-50">
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                  <div class="flex items-center gap-2 text-xs text-gray-600">
                    <span class="inline-block px-1.5 py-0.5 rounded bg-red-100 text-red-800 font-medium">Wound</span>
                    <span>opened {{ $entry['when']->format('d M Y') }}</span>
                    @if (!$c->isOpen())
                      <span class="text-gray-500">· closed {{ $c->closed_on->format('d M Y') }}</span>
                    @else
                      <span class="font-medium text-red-700">· still open</span>
                    @endif
                  </div>
                  <p class="mt-1 text-sm font-medium text-gray-800">{{ $c->diagnosis }}</p>
                  @if ($c->visits->isNotEmpty())
                    <ul class="mt-2 space-y-1 text-xs text-gray-700">
                      @foreach ($c->visits as $v)
                        <li>
                          <span class="text-gray-500">{{ $v->visited_on->format('d M') }}:</span>
                          {{ $v->treatment }}
                          @if ($v->remarks)<span class="text-gray-500"> — {{ $v->remarks }}</span>@endif
                        </li>
                      @endforeach
                    </ul>
                  @endif
                </div>
                <div class="flex flex-col items-end gap-2 whitespace-nowrap">
                  @if ($c->isOpen())
                    <button type="button" wire:click="closeWound({{ $c->id }})"
                      class="px-4 py-2 text-sm font-medium text-red-900 bg-red-100 border border-red-300 rounded-md shadow-sm hover:bg-red-200">
                      Mark as closed
                    </button>
                  @endif
                  {{-- Wound cases keep their own page: that is where follow-up visits are added. --}}
                  <a href="{{ route('health.wound-cases.edit', $c) }}"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                    Add visit
                  </a>
                </div>
              </div>
            </div>
            @break

          @case('note')
            @php $n = $entry['model']; @endphp
            <div class="flex items-start justify-between gap-4 p-3 bg-white border border-gray-200 rounded-md">
              <div class="flex-1">
                <div class="flex items-center gap-2 text-xs text-gray-500">
                  <span class="inline-block px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 font-medium">Note</span>
                  <span>{{ $entry['when']->format('d M Y') }}</span>
                  @if ($n->location)<span>· {{ $n->location }}</span>@endif
                  @if ($n->temperature)<span>· {{ $n->temperature }}°C</span>@endif
                </div>
                <p class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $n->content }}</p>
              </div>
              <button type="button" wire:click="edit('note', {{ $n->id }})"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm shrink-0 hover:bg-gray-50">
                Edit
              </button>
            </div>
            @break
        @endswitch
      @endforeach
    </div>
  @endif
</div>
