<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\School;
use App\Models\Schoolyear;

/**
 * Bakoteh Proper Lower Basic School — a public-school demo. Reuses Albreda's
 * structure and public-school calendar, with Bakoteh's own grade key, subjects
 * and roster (from the school's report book and result master list).
 *
 *   php artisan db:seed --class=Database\\Seeders\\BakotehSeeder   (fresh database)
 *
 * Grade key is numeric (1/4/5/6/8/9). Subjects: six calculated (summed into the
 * total) and five graded (letter/grade only). NB: the school's assessment book
 * also lists "National Language" as a calculated subject — add it if wanted.
 */
class BakotehSeeder extends AlbredaSeeder
{
    protected function schoolName(): string
    {
        return 'Bakoteh Proper Lower Basic School';
    }

    protected function password(): string
    {
        return 'bakoteh';
    }

    protected function headLastName(): string
    {
        return 'Touray';
    }

    protected function teacherLastNames(): array
    {
        return ['Ceesay', 'Bojang'];
    }

    /** Bakoteh uses the report/assessment book (per-month grid), not a one-page card. */
    protected function termCardLayout(): string
    {
        return 'default';
    }

    protected function reportType(): string
    {
        return 'book';
    }

    /** Bakoteh's numeric grade key. */
    protected function gradeKeyBands(): array
    {
        return [
            ['1', 80, 100, 'Excellent'], ['4', 70, 79, 'Very Good'], ['5', 60, 69, 'Good'],
            ['6', 50, 59, 'Average'], ['8', 40, 49, 'Pass'], ['9', 0, 39, 'Fail'],
        ];
    }

    protected function seedClasses(School $school, Schoolyear $year, array $terms): array
    {
        // Calculated first (summed into the total), then graded (grade only).
        $subjects = [
            'English language', 'Mathematics', 'Integrated / S.E.S.', 'Science', 'Verbal Aptitude', 'Quantitative',
            'Religious Education', 'Arts', 'Spelling', 'Handwriting', 'Reading',
        ];
        $graded = ['Religious Education', 'Arts', 'Spelling', 'Handwriting', 'Reading'];

        $g6 = [
            ['Alhagie', 'Touray', 'M'], ['Fatou', 'Ceesay', 'F'], ['Ebrima', 'Jallow', 'M'], ['Isatou', 'Sonko', 'F'],
            ['Modou', 'Colley', 'M'], ['Adama', 'Sanneh', 'F'], ['Lamin', 'Bojang', 'M'], ['Mariama', 'Darboe', 'F'],
            ['Ousman', 'Ceesay', 'M'], ['Awa', 'Manneh', 'F'], ['Sulayman', 'Fofana', 'M'], ['Binta', 'Camara', 'F'],
            ['Momodou', 'Kinteh', 'M'], ['Haddy', 'Njie', 'F'], ['Yankuba', 'Drammeh', 'M'], ['Jainaba', 'Sowe', 'F'],
        ];
        $g3 = [
            ['Bakary', 'Sillah', 'M'], ['Rohey', 'Barrow', 'F'], ['Alagie', 'Conteh', 'M'], ['Fatoumatta', 'Jammeh', 'F'],
            ['Sang', 'Jarju', 'M'], ['Isatou', 'Bah', 'F'], ['Cherno', 'Saidy', 'M'], ['Aminata', 'Gai', 'F'],
            ['Abdou', 'Ndoye', 'M'], ['Salla', 'Baye', 'F'], ['Amadou', 'Jobe', 'M'], ['Naffie', 'Traore', 'F'],
        ];

        return [
            $this->seedClass($school, $year, $terms, 'Grade 6', 'A', $subjects, $this->roster($g6, count($subjects), 7), $graded),
            $this->seedClass($school, $year, $terms, 'Grade 3', 'A', $subjects, $this->roster($g3, count($subjects), 3), $graded),
        ];
    }

    /** Turn [first, last, gender] rows into rosters with a deterministic score (30-95) per subject. */
    private function roster(array $names, int $subjectCount, int $salt): array
    {
        $out = [];
        foreach ($names as $i => [$first, $last, $gender]) {
            $row = [$first, $last, $gender];
            for ($s = 0; $s < $subjectCount; $s++) {
                $row[] = 30 + ((($i + 1) * 7 + ($s + 1) * 13 + $salt * 5) % 66);
            }
            $out[] = $row;
        }

        return $out;
    }
}
