<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; }
    @page { margin: 20px 22px; }
    body { color: #1f2937; font-size: 9px; }
    .school { text-align: center; font-size: 15px; font-weight: bold; }
    .title { text-align: center; font-size: 12px; font-weight: bold; letter-spacing: 0.5px; }
    .meta { margin: 3px 0 8px; font-size: 9px; text-align: center; color: #6b7280; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #6b7280; padding: 2px 3px; text-align: center; }
    thead th { background: #eef1f8; color: #34486f; font-size: 8px; font-weight: bold; }
    td.name, th.name { text-align: left; }
    td.no { width: 20px; color: #6b7280; }
    th.name, td.name { width: 128px; }
    tr:nth-child(even) td { background: #f8f9fc; }
    .foot { margin-top: 8px; color: #9ca3af; font-size: 9px; text-align: right; }
    .empty td { text-align: center; color: #6b7280; padding: 16px; }
  </style>
</head>
<body>
  <div class="school">{{ $school->name ?? 'School' }}</div>
  <div class="title">STAFF LIST</div>
  <div class="meta">{{ now()->format('F Y') }} &nbsp;&middot;&nbsp; {{ $staff->count() }} staff</div>

  <table>
    <thead>
      <tr>
        <th class="no">#</th>
        <th class="name">NAME</th>
        <th>PRN</th>
        <th>TIN NO.</th>
        <th>GENDER</th>
        <th>STATUS</th>
        <th>DATE OF APP.</th>
        <th>DATE OF CONF.</th>
        <th>CONTACT</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($staff as $i => $u)
        @php $sp = $u->staffProfile; $p = $u->profile; @endphp
        <tr>
          <td class="no">{{ $i + 1 }}</td>
          <td class="name">{{ ucfirst($u->first_name).' '.ucfirst($u->last_name) }}</td>
          <td>{{ $sp?->prn }}</td>
          <td>{{ $sp?->tin }}</td>
          <td>{{ $p?->gender }}</td>
          <td>{{ $sp?->status?->label }}</td>
          <td>{{ $sp?->appointed_on?->format('d/m/Y') }}</td>
          <td>{{ $sp?->confirmed_on?->format('d/m/Y') }}</td>
          <td>{{ $p?->primary_phone }}</td>
        </tr>
      @empty
        <tr class="empty"><td colspan="9">No staff on record yet.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="foot">Generated with KUBO</div>
</body>
</html>
