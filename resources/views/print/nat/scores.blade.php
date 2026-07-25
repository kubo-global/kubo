<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @include('print.nat._styles')
</head>
<body>
    @include('print.nat._footer', ['kuboLogo' => $kuboLogo])
    @include('print.nat._header', ['logo' => $logo, 'org' => $org, 'title2' => $title2, 'subtitle' => 'result listing'])

    <table class="data">
        <thead>
            <tr>
                <th>Student</th>
                <th>Sex</th>
                @foreach ($assessments as $a)
                    <th>{{ $a->subject->name }} <span class="max">/{{ $a->max_score }}</span></th>
                @endforeach
                <th>Total <span class="max">/{{ $assessments->sum('max_score') }}</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr class="{{ $loop->even ? 'zebra' : '' }}">
                    <td>{{ $r['student']->first_name }} {{ $r['student']->last_name }}</td>
                    <td>{{ strtoupper($r['gender']) }}</td>
                    @foreach ($assessments as $a)
                        @php $val = $r['scores'][$a->id] ?? null; @endphp
                        @if ($val === null)
                            <td class="absent">A</td>
                        @else
                            <td class="num {{ $val < $failBelow ? 'fail' : ($val >= $masteryAt ? 'mastery' : '') }}">{{ $val }}</td>
                        @endif
                    @endforeach
                    <td class="num">{{ $r['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="legend">
        <span class="item"><span class="sw" style="background:#fbe0e0;"></span>Fail (&lt; {{ $failBelow }})</span>
        <span class="item"><span class="sw" style="background:#ddf0e5;"></span>Mastery (&ge; {{ $masteryAt }})</span>
        <span class="item">A = absent</span>
    </p>
</body>
</html>
