<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @include('print.nat._styles')
</head>
<body>
    @include('print.nat._footer', ['kuboLogo' => $kuboLogo])
    @include('print.nat._header', ['logo' => $logo, 'org' => $org, 'title2' => $title2, 'subtitle' => 'analysis'])

    @foreach ($breakdowns as $b)
        @unless ($loop->first)<hr class="block-sep">@endunless
        <div class="block">
            <div class="subject-title">{{ $b['subject']->name }}</div>
            <div class="chart-box">
                <div class="chart-sub">% pass / fail / mastery by sex</div>
                @include('print.nat._chart', ['b' => $b])
            </div>
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ $b['subject']->name }}</th>
                        <th>No. of children</th>
                        <th>fail</th>
                        <th>% fail</th>
                        <th>pass</th>
                        <th>% pass</th>
                        <th>mastery</th>
                        <th>% mastery</th>
                        <th>average</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (['Male', 'Female', 'All'] as $label)
                        @php $g = $b['groups'][$label]; @endphp
                        <tr class="{{ $label === 'All' ? 'total' : '' }}">
                            <td>{{ $label }}</td>
                            <td class="num">{{ $g['n'] }}</td>
                            <td class="num">{{ $g['fail'] }}</td>
                            <td class="num">{{ round($g['pct_fail'] * 100) }}%</td>
                            <td class="num">{{ $g['pass'] }}</td>
                            <td class="num">{{ round($g['pct_pass'] * 100) }}%</td>
                            <td class="num">{{ $g['mastery'] }}</td>
                            <td class="num">{{ round($g['pct_mastery'] * 100) }}%</td>
                            <td class="num">{{ $g['average'] !== null ? $g['average'] : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
