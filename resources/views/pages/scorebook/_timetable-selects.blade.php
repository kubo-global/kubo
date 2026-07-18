{{-- Subject / teacher / (optional) length selects for one timetable cell.
     Used by both the desktop grid and the phone stacked layout so the editor
     markup stays single-sourced. Expects: $period, $dayNum, $dayName, $lesson,
     $subjects, $teachers, $maxLen, and $compact (true for the dense grid cell). --}}
@php
  $pid = $period->id;
  $maxL = $maxLen[$pid] ?? 1;
  $sz = ($compact ?? false) ? 'px-1.5 py-1 mb-1 text-xs' : 'px-2.5 py-2 mb-1.5 text-sm';
@endphp
<select name="cells[{{ $pid }}][{{ $dayNum }}][subject_id]"
  aria-label="Subject for {{ $dayName }}, {{ $period->label }}"
  class="block w-full {{ $sz }} border border-gray-300 rounded focus:outline-none focus:ring-gray-900 focus:border-gray-900">
  <option value="">—</option>
  @foreach ($subjects as $s)
    <option value="{{ $s->id }}" @selected($lesson && $lesson->subject_id == $s->id)>{{ $s->name }}</option>
  @endforeach
</select>
<select name="cells[{{ $pid }}][{{ $dayNum }}][teacher_id]"
  aria-label="Teacher for {{ $dayName }}, {{ $period->label }}"
  class="block w-full {{ $sz }} text-gray-600 border border-gray-200 rounded focus:outline-none focus:ring-gray-900 focus:border-gray-900">
  <option value="">— teacher —</option>
  @foreach ($teachers as $t)
    <option value="{{ $t->id }}" @selected($lesson && $lesson->teacher_id == $t->id)>{{ $t->first_name }} {{ $t->last_name }}</option>
  @endforeach
</select>
@if ($maxL > 1)
  <select name="cells[{{ $pid }}][{{ $dayNum }}][length]" title="Periods this lesson spans"
    aria-label="Length for {{ $dayName }}, {{ $period->label }}"
    class="block w-full {{ $sz }} text-gray-500 border border-gray-200 rounded focus:outline-none focus:ring-gray-900 focus:border-gray-900">
    @for ($n = 1; $n <= $maxL; $n++)
      <option value="{{ $n }}" @selected(($lesson->length ?? 1) == $n)>{{ $n }} period{{ $n > 1 ? 's' : '' }}</option>
    @endfor
  </select>
@endif
