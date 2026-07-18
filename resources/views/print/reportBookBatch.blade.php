<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  @include('print._report-book-styles')
</head>
<body>
  @forelse ($books as $book)
    <div @if(! $loop->last) style="page-break-after: always;" @endif>
      @include('print._report-book-page', $book + ['gradeKey' => $gradeKey, 'school' => $school, 'logo' => $logo, 'empty' => $empty])
    </div>
  @empty
    <p>No pupils enrolled in this class.</p>
  @endforelse
  <div class="foot">Generated with KUBO</div>
</body>
</html>
