<style>
    h1 {
        font-size: 13px;
        margin-left: 10px;
        margin-top: 0px;
        vertical-align: top;
        display: inline-block;
    }

    table,
    td,
    th {
        border: 1px solid black;
        height: 16px;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    .main {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11px;
    }

    .page-break {
        page-break-after: always;
    }

    .header {
        margin-top: 10px;
        margin-bottom: 20px;
    }

    .header table td strong {
        padding: 0 5px;
    }

    .grades thead td {
        font-weight: bold;
        padding: 0 5px;
        white-space: nowrap;
        text-align: center;
    }

    .grades thead tr:last-child {
        font-weight: normal;
        font-size: 10px;
    }

    .grades table td:last-child {
        width: 100%;
    }

    .footer {
        margin-top: 20px;
    }

    .bigcell {
        vertical-align: top;
        height: 30px;
    }

    .signatures {
        margin-top: 20px;
    }

    .signatures table,
    .signatures td {
        border: none;
    }

</style>

@php $school = \App\Models\School::first(); @endphp
@foreach ($reports as $report)
    <div class="main @if (!$loop->last) page-break @endif">
        @include('print.partials.report-header', ['title' => 'Report form', 'logoFallback' => true])
        <div class="header">
            <table>
                <tr>
                    <td colspan="2">
                        <strong>Name:</strong>{{ $report['student']->first_name . ' ' . $report['student']->last_name }}
                    </td>
                    @php $age =  $report['student']->getAge(); 
                    @endphp
                    
                    {{-- Don't show age if ridiculously high, as it is error in DB --}}
                    <td><strong>Age:</strong>@if ($age > 5 && $age < 20) {{ $age }} @endif</td>
                </tr>
                <tr>
                    <td><strong>Term:</strong> {{ $report['term']->name }}</td>
                    <td><strong>Academic year:</strong>
                        {{ $report['schoolyear']->start->format('Y') . ' - ' . $report['schoolyear']->end->format('Y') }}
                    </td>
                    <td><strong>Class:</strong> {{ $report['grade']->name }}</td>
                </tr>
                <tr>
                    <td colspan="3"><strong>Teacher:</strong>
                        @if ($report['teacher'])
                            {{ $report['teacher']->first_name . ' ' . $report['teacher']->last_name }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>Position:</strong> </td>
                    <td><strong>Average:</strong></td>
                    <td><strong>No. in class:</strong></td>
                </tr>
            </table>
        </div>
        @php
            // Get assessment types for dynamic columns
            $school = \App\Models\School::first();
            $assessmentTypes = $school ? $school->assessmentTypes : collect();
        @endphp
        <div class="grades">
            <table>
                <thead>
                    <tr>
                        <td rowspan="2">Subject</td>
                        @foreach($assessmentTypes as $type)
                        <td>{{ $type->name }}s</td>
                        @endforeach
                        <td>Total</td>
                        <td></td>
                    </tr>
                    <tr>
                        @foreach($assessmentTypes as $type)
                        <td>Marks {{ round($type->weight * 100) }}</td>
                        @endforeach
                        <td>%</td>
                        <td style="text-align: left">Remarks</td>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['results']['subjectResults'] as $subject => $result)
                        <tr>
                            <td style="white-space: nowrap; padding: 0 5px;">{{ $subject }}</td>
                            @foreach($assessmentTypes as $type)
                            <td style="text-align: right; padding-right: 5px">
                                {{ $result['typeResults'][$type->id]['weightedScore'] ?? '' }}</td>
                            @endforeach
                            <td style="text-align: right; padding-right: 5px">{{ $result['subjectTotal'] }}</td>
                            <td style="text-align: left; padding: 0 5px"></td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="white-space: nowrap; padding: 0 5px;">Total</td>
                        @foreach($assessmentTypes as $type)
                        <td></td>
                        @endforeach
                        <td style="white-space: nowrap; text-align: right; padding: 0 5px; font-weight: bold">
                            {{ $report['results']['total'] }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="footer">
            <table>
                <tr>
                    <td><strong>Possible Attendance:</strong> </td>
                    <td><strong>Time Absent:</strong> </td>
                    <td><strong>Time Late:</strong> </td>
                </tr>
                <tr>
                    <td class="bigcell" colspan="3"><strong>Conduct:</strong> </td>
                </tr>
                <tr>
                    <td class="bigcell" colspan="3"><strong>General Remarks:</strong> </td>
                </tr>
            </table>
        </div>
        <div class="signatures">
            <table>
                <tr>
                    <td class="bigcell" style="text-align:center;">School Coordinator</td>
                    <td class="bigcell" style="text-align:center;">Parent/Guardian</td>
                </tr>
                <tr>
                    <td style="text-align:center;">Date:</td>
                    <td style="text-align:center;">Date:</td>
                </tr>
            </table>
            <hr>
            <br>
            Next term begins:
        </div>
    </div>
@endforeach
