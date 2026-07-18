@props(['user'])

@php
  // The page header says what kind of page this is; the name belongs here, on the
  // record itself, with the facts a caregiver checks before recording anything.
  $offering = $user->currentEnrollment()?->offering;
  $facts = array_filter([
    $offering?->displayName(),
    $user->getAge() !== null ? $user->getAge().' years old' : null,
    match ($user->profile?->gender) { 'F' => 'Female', 'M' => 'Male', default => null },
    $user->profile?->birth_date ? 'born '.\Carbon\Carbon::parse($user->profile->birth_date)->format('d M Y') : null,
  ]);
@endphp

<div class="flex items-center gap-4">
  <div class="flex items-center justify-center text-base font-semibold text-indigo-700 uppercase rounded-full w-14 h-14 bg-indigo-50 shrink-0"
    aria-hidden="true">
    {{ $user->getInitials() }}
  </div>
  <div class="min-w-0">
    <h2 class="text-2xl font-bold text-gray-900 truncate">{{ $user->getFullNameAttribute() }}</h2>
    @if ($facts)
      <p class="text-sm text-gray-600">{{ implode(' · ', $facts) }}</p>
    @endif
  </div>
</div>
