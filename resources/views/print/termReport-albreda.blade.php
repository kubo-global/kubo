<style>
    h1 { font-size: 13px; margin-left: 10px; margin-top: 0px; vertical-align: top; display: inline-block; }
    table, td, th { border: 1px solid black; height: 16px; }
    table { border-collapse: collapse; width: 100%; }
    .main { font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
    .page-break { page-break-after: always; }
    .header { margin-top: 10px; margin-bottom: 12px; }
    .header table td strong { padding: 0 5px; }
    .grades thead td { font-weight: bold; padding: 0 4px; white-space: nowrap; text-align: center; }
    .grades td { padding: 0 4px; }
    .footer { margin-top: 14px; }
    .bigcell { vertical-align: top; height: 28px; }
    .sig td { border: none; text-align: center; height: 34px; vertical-align: bottom; }
    .sig { margin-top: 14px; }
</style>

@php
    $school = \App\Models\School::first();
    // The two weighted types: Ass (continuous assessment, lower weight) and Exam.
    $weighted = $school ? $school->assessmentTypes->where('weight', '>', 0)->sortBy('weight')->values() : collect();
    $assType = $weighted->first();
    $examType = $weighted->count() > 1 ? $weighted->last() : null;
@endphp

@foreach ($reports as $report)
    @php $gradeNum = (int) preg_replace('/\D/', '', $report['grade']->name ?? ''); @endphp
    <div class="main @if (!$loop->last) page-break @endif">
        @include('print.partials.report-header', ['title' => 'Terminal Report'])

        <div class="header">
            <table>
                <tr>
                    <td colspan="3"><strong>Name:</strong> {{ $report['student']->first_name.' '.$report['student']->last_name }}</td>
                    <td><strong>Class:</strong> {{ $report['grade']->name }}</td>
                </tr>
                <tr>
                    <td><strong>Term:</strong> {{ $report['term']->name }}</td>
                    <td colspan="2"><strong>Academic year:</strong> {{ $report['schoolyear']->start->format('Y').' - '.$report['schoolyear']->end->format('Y') }}</td>
                    <td><strong>No. of Students:</strong></td>
                </tr>
                <tr>
                    <td><strong>Possible Attendance:</strong></td>
                    <td><strong>Time Absent:</strong></td>
                    <td><strong>Time Late:</strong></td>
                    <td><strong>Conduct:</strong></td>
                </tr>
                <tr>
                    <td><strong>Position:</strong></td>
                    <td><strong>Average:</strong> {{ $report['results']['average'] }}</td>
                    <td colspan="2"><strong>Percentage:</strong> {{ $report['results']['average'] }}%</td>
                </tr>
            </table>
        </div>

        <div class="grades">
            <table>
                <thead>
                    <tr>
                        <td>Subject</td>
                        <td>Ass ({{ $assType ? round($assType->weight * 100) : 25 }})</td>
                        <td>Exam ({{ $examType ? round($examType->weight * 100) : 75 }})</td>
                        <td>Total</td>
                        <td>Grade</td>
                        <td style="width:35%; text-align:left;">Remarks</td>
                        <td>T/Sign</td>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['results']['subjectResults'] as $subject => $result)
                        @php
                            $total = $result['subjectTotal'];
                            $letter = ($total !== null) ? optional(\App\Models\GradingScale::resolve($school, (float) $total, $gradeNum))->label : null;
                        @endphp
                        <tr>
                            <td style="white-space: nowrap;">{{ $subject }}</td>
                            <td style="text-align:right;">{{ $assType ? ($result['typeResults'][$assType->id]['weightedScore'] ?? '') : '' }}</td>
                            <td style="text-align:right;">{{ $examType ? ($result['typeResults'][$examType->id]['weightedScore'] ?? '') : '' }}</td>
                            <td style="text-align:right;">{{ $total }}</td>
                            <td style="text-align:center;">{{ $letter }}</td>
                            <td></td>
                            <td></td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="font-weight:bold;">Total</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right; font-weight:bold;">{{ $report['results']['total'] }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <table>
                <tr><td class="bigcell"><strong>Teacher's Remarks:</strong></td></tr>
                <tr><td class="bigcell"><strong>Head-Teacher's Remarks:</strong></td></tr>
                <tr>
                    <td>
                        <strong>Promoted to:</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <strong>Next Term Begins:</strong>
                    </td>
                </tr>
            </table>
        </div>

        <div class="sig">
            <table>
                <tr>
                    <td>Teacher's Signature</td>
                    <td>Head-Teacher's Signature</td>
                    <td>Parent/Guardian's Signature</td>
                </tr>
            </table>
        </div>
    </div>
@endforeach
