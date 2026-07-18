{{-- One pupil's report-book page, matching the Bakoteh-style booklet. Expects:
     student, offering, schoolyear, subjects, rows, teacher, gradeKey, school, logo, empty. --}}
<div class="header">
  <table>
    <tr>
      @if ($logo)<td class="logo"><img src="{{ $logo }}" class="logo"></td>@endif
      <td>
        <div class="school">{{ $school->name ?? 'School' }}</div>
        <div class="doc">REPORT BOOK &mdash; ASSESSMENT BOOK</div>
      </td>
      <td style="text-align:right; font-size:10px; color:#6b7280;">{{ $schoolyear->name ?? '' }}</td>
    </tr>
  </table>
</div>

<table class="meta">
  <tr>
    <td class="lbl">Child's name:</td><td class="val">{{ trim($student->first_name.' '.$student->last_name) }}</td>
    <td class="lbl">Class:</td><td class="val">{{ $offering->displayName() }}</td>
    <td class="lbl">Class roll:</td><td class="val">&nbsp;</td>
    <td class="lbl">Class teacher:</td><td class="val">{{ $teacher ? $teacher->first_name.' '.$teacher->last_name : '' }}</td>
  </tr>
  <tr>
    <td class="lbl">Time present:</td><td class="val">@if(($timePresent ?? null) !== null){{ $timePresent }}@else&nbsp;@endif</td>
    <td class="lbl">Time absent:</td><td class="val">@if(($timeAbsent ?? null) !== null){{ $timeAbsent }}@else&nbsp;@endif</td>
    <td class="lbl">Conduct:</td><td class="val">&nbsp;</td>
    <td class="lbl">Class position:</td><td class="val">&nbsp;</td>
  </tr>
</table>

<table class="grid">
  <thead>
    <tr>
      <th class="term">Month</th>
      @foreach ($subjects as $s)<th>{{ $s->name }}</th>@endforeach
      <th>Total</th>
      <th>Position</th>
      <th class="hand">Remarks</th>
      <th class="hand">Parent's name</th>
      <th class="hand">Parent's signature</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($rows as $row)
      <tr class="{{ $row['exam'] ? 'exam' : '' }}">
        <td class="term">{{ $row['label'] }}</td>
        @foreach ($subjects as $s)
          <td>{{ $row['cells'][$s->id] ?? '' }}</td>
        @endforeach
        <td class="tot">{{ $row['total'] !== null ? $row['total'] : '' }}</td>
        <td class="tot">{{ $row['position'] !== null ? $row['position'] : '' }}</td>
        <td class="hand">&nbsp;</td>
        <td class="hand">&nbsp;</td>
        <td class="hand">&nbsp;</td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="layout">
  <div class="remarks">
    <div><b>Teacher's remark:</b></div>
    <div class="line"></div>
    <div><b>Head teacher's remark:</b></div>
    <div class="line"></div>
  </div>
  <table class="sigs">
    <tr>
      <td>Teacher's signature: ____________________</td>
      <td>Head teacher's signature: ____________________</td>
    </tr>
    <tr>
      <td>Parent's signature: ____________________</td>
      <td>Next term begins: ____________________</td>
    </tr>
  </table>
</div>

<div class="key">
  <table class="key-tbl">
    <thead><tr><th>Marks</th><th>Grade</th><th>Interpretation</th></tr></thead>
    <tbody>
      @forelse ($gradeKey as $band)
        <tr>
          <td>{{ (int) $band->min_score }}&ndash;{{ (int) $band->max_score }}</td>
          <td>{{ $band->label }}</td>
          <td>{{ $band->remark }}</td>
        </tr>
      @empty
        <tr><td colspan="3" class="blank">No grade key configured.</td></tr>
      @endforelse
    </tbody>
  </table>
  <div class="note">Blank monthly cells are filled by hand. Absent = x.</div>
</div>
