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

@php
    $school = \App\Models\School::first();
    $positions = $positions ?? collect();
    // Only the weighted types make up the card (Assessments + Exams); NAT (weight 0)
    // is a separate exercise and would otherwise add a stray "Marks 0" column.
    $assessmentTypes = $school ? $school->assessmentTypes->where('weight', '>', 0)->sortBy('weight')->values() : collect();

    // The school's pre-printed form lists subjects in a fixed order; the card
    // follows it so staff can lay them side by side. The lower grades' Integrated
    // studies sits in the Science/S.E.S. slot (it is their equivalent), and
    // Phonics among the marked subjects, so every grade orders the same way.
    // Only Reading is off the form and follows after, alphabetically.
    $subjectOrder = [
        'Religious Knowledge', 'Verbal aptitude', 'Science', 'Integrated studies', 'French', 'S.E.S.',
        'Physical Education', 'Phonics', 'Spelling/Dictation', 'English language', 'Mathematics',
        'Art and craft', 'Health', 'Quantitative', 'Information Technology',
    ];
    $subjectRank = function ($name) use ($subjectOrder) {
        $i = array_search($name, $subjectOrder, true);
        return sprintf('%03d-%s', $i === false ? count($subjectOrder) + 1 : $i, $name);
    };

    // Subjects the school does not report on the card (kept in the scorebook, just
    // not printed here).
    $hiddenSubjects = ['Reading'];
@endphp
@foreach ($reports as $report)
    @php
        $gradeNum = (int) preg_replace('/\D/', '', $report['grade']->name ?? '');
        $position = $positions[$report['student']->id]['position'] ?? null;
        $remark = ($remarks ?? collect())[$report['student']->id] ?? null;
    @endphp
    <div class="main @if (!$loop->last) page-break @endif">
        @include('print.partials.report-header', ['title' => 'Report form', 'logoFallback' => true])
        <div class="header">
            <table>
                <tr>
                    <td colspan="2">
                        <strong>Name:</strong>{{ $report['student']->first_name . ' ' . $report['student']->last_name }}
                    </td>
                    @php $age =  $report['student']->getAge($report['term']->end); @endphp

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
                    <td><strong>Position:</strong> {{ $position }}</td>
                    <td><strong>Average:</strong> {{ $report['results']['average'] }}</td>
                    <td><strong>No. in class:</strong> {{ ($classSize ?? 0) ?: '' }}</td>
                </tr>
            </table>
        </div>
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
                    @foreach (collect($report['results']['subjectResults'])->except($hiddenSubjects)->sortBy(fn ($r, $name) => $subjectRank($name)) as $subject => $result)
                        @php
                            $total = $result['subjectTotal'];
                            $band = ($total !== null) ? \App\Models\GradingScale::resolve($school, (float) $total, $gradeNum) : null;
                        @endphp
                        <tr>
                            <td style="white-space: nowrap; padding: 0 5px;">{{ $subject }}</td>
                            @foreach($assessmentTypes as $type)
                            <td style="text-align: right; padding-right: 5px">
                                {{ $result['typeResults'][$type->id]['weightedScore'] ?? '' }}</td>
                            @endforeach
                            <td style="text-align: right; padding-right: 5px">{{ $total }}</td>
                            <td style="text-align: left; padding: 0 5px">
                                @if ($band){{ trim(($band->label ? $band->label.' ' : '').$band->remark) }}@endif
                            </td>
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
                    <td class="bigcell" colspan="3"><strong>Conduct:</strong> {{ $remark['conduct'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="bigcell" colspan="3"><strong>General Remarks:</strong> {{ $remark['general'] ?? '' }}</td>
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
