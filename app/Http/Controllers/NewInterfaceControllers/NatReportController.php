<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Domain\Nat\NatAnalysis;
use App\Http\Controllers\Controller;
use App\Models\AssessmentType;
use App\Models\Offering;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

/**
 * Generates the NAT deliverables (scores listing + analysis with charts) as PDF,
 * mirroring the existing term-report flow (dompdf via PDF::loadView).
 */
class NatReportController extends Controller
{
    /** Resolve the offering, its NAT assessment type, and report headings. */
    private function context(int $offeringId): array
    {
        $offering = Offering::with(['grade', 'schoolyear'])->findOrFail($offeringId);

        $type = AssessmentType::query()
            ->where('name', 'National Assessment Test')
            ->whereHas('assessments', fn ($q) => $q->where('offering_id', $offeringId))
            ->firstOrFail();

        [$failBelow, $masteryAt] = NatAnalysis::thresholdsForSchool($type->school);

        // WAEC brands the exam by the later calendar year of the school year
        // (e.g. school year "2023-2024" -> "NAT 2024"), matching the printed sheet.
        $schoolyear = $offering->schoolyear->name ?? '';
        $examYear = preg_match_all('/\d{4}/', $schoolyear, $m) ? max($m[0]) : $schoolyear;

        return [
            'analysis' => new NatAnalysis($offering, $type, $failBelow, $masteryAt),
            'org' => $type->school?->name ?? 'National Assessment Test',
            'title2' => trim('NAT '.$examYear.', '.($offering->grade->name ?? '')),
            'failBelow' => $failBelow,
            'masteryAt' => $masteryAt,
            'logo' => $this->logoPath($type->school),
            'kuboLogo' => file_exists(public_path('images/Kubo-logo-black.png'))
                ? public_path('images/Kubo-logo-black.png')
                : null,
        ];
    }

    /** Absolute path to the configured school logo for dompdf (Settings > School). */
    private function logoPath(?\App\Models\School $school): ?string
    {
        if ($school?->logo_path && file_exists(public_path($school->logo_path))) {
            return public_path($school->logo_path);
        }

        return null;
    }

    public function scores(\Illuminate\Http\Request $request, int $offeringId)
    {
        ['analysis' => $analysis, 'org' => $org, 'title2' => $title2,
            'failBelow' => $failBelow, 'masteryAt' => $masteryAt, 'logo' => $logo,
            'kuboLogo' => $kuboLogo] = $this->context($offeringId);

        $rows = $analysis->studentRows();

        // Optionally drop students who were absent for every subject (didn't sit at all),
        // so the listing can match the official WAEC candidate sheet. Analysis is unaffected.
        if ($request->boolean('hide_absent')) {
            $rows = $rows->filter(fn ($r) => collect($r['scores'])->contains(fn ($v) => $v !== null))->values();
        }

        $pdf = PDF::loadView('print.nat.scores', [
            'assessments' => $analysis->assessments(),
            'rows' => $rows,
            'org' => $org,
            'title2' => $title2,
            'failBelow' => $failBelow,
            'masteryAt' => $masteryAt,
            'logo' => $logo,
            'kuboLogo' => $kuboLogo,
        ])->setPaper('a4');

        return $pdf->download("NAT {$title2} scores.pdf");
    }

    public function analysis(int $offeringId)
    {
        ['analysis' => $analysis, 'org' => $org, 'title2' => $title2,
            'logo' => $logo, 'kuboLogo' => $kuboLogo] = $this->context($offeringId);

        $pdf = PDF::loadView('print.nat.analysis', [
            'breakdowns' => $analysis->breakdowns(),
            'org' => $org,
            'title2' => $title2,
            'logo' => $logo,
            'kuboLogo' => $kuboLogo,
        ])->setPaper('a4');

        return $pdf->download("NAT {$title2} graphs.pdf");
    }
}
