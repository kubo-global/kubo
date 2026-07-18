{{--
    Shared header for the printed term cards: school logo + name + form title.
    The card tables below differ per school, but this masthead is identical, so
    each layout @includes it instead of re-copying the logo/file_exists dance.

    Params: $title (the card's subtitle, e.g. "Report form" / "Terminal Report");
    $logoFallback (show the bundled Swallow logo when the school has none).
--}}
@php $school = $school ?? \App\Models\School::first(); @endphp
@if($school?->logo_path && file_exists(public_path($school->logo_path)))
    <img src="{{ public_path($school->logo_path) }}" width="50px" alt="Logo" />
@elseif(($logoFallback ?? false) && file_exists(public_path('/images/logo-swallow.png')))
    <img src="{{ public_path('/images/logo-swallow.png') }}" width="50px" alt="Logo" />
@endif
<h1>{{ $school?->name ?? 'School' }}<br><u><i>{{ $title ?? 'Report' }}</i></u></h1>
