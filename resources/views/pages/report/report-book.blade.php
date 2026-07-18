<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Report book &mdash; {{ trim($student->first_name.' '.$student->last_name) }} | KUBO</title>
  {{-- Reuse the exact booklet styles + page markup used for the PDF, so the
       on-screen view and the printout can never drift. --}}
  @include('print._report-book-styles')
  <style>
    body { background: #f3f4f6; margin: 0; }
    .toolbar {
      position: sticky; top: 0; z-index: 10;
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
      padding: 10px 16px; background: #fff; border-bottom: 1px solid #e5e7eb;
    }
    .toolbar a { text-decoration: none; font-size: 13px; }
    .toolbar .back { color: #4b5563; font-weight: 600; }
    .toolbar .back:hover { color: #111827; }
    .toolbar .actions { display: flex; gap: 8px; align-items: center; }
    .toolbar .btn { padding: 6px 14px; border-radius: 6px; font-weight: 600; }
    .btn-pdf { background: #22c55e; color: #fff; }
    .btn-pdf:hover { background: #16a34a; }
    .btn-ghost { background: #fff; color: #3d5494; border: 1px solid #d1d5db; }
    .btn-ghost:hover { background: #f9fafb; }
    .sheet {
      max-width: 1120px; margin: 16px auto; padding: 22px 26px;
      background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.12); overflow-x: auto;
    }
    @media print {
      .toolbar { display: none; }
      body { background: #fff; }
      .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
    }
  </style>
</head>
<body>
  @php $pdfParams = ['enrollmentId' => $enrollmentId] + ($empty ? ['empty' => 1] : []); @endphp
  <div class="toolbar">
    <a class="back" href="{{ url()->previous() }}">&larr; Back</a>
    <div class="actions">
      @if ($empty)
        <a class="btn btn-ghost" href="{{ route('report-book.screen', ['enrollmentId' => $enrollmentId]) }}">Show scores</a>
      @else
        <a class="btn btn-ghost" href="{{ route('report-book.screen', ['enrollmentId' => $enrollmentId, 'empty' => 1]) }}">Blank version</a>
      @endif
      <a class="btn btn-pdf" href="{{ route('report-book.print', $pdfParams) }}" target="_blank" rel="noopener">Download PDF</a>
    </div>
  </div>

  <div class="sheet">
    @include('print._report-book-page')
  </div>
</body>
</html>
