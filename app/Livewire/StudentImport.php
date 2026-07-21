<?php

namespace App\Livewire;

use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Profile;
use App\Models\Schoolyear;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bulk student import: upload a class list (CSV) -> validate -> preview ->
 * confirm. Nothing is written until the preview is confirmed, and the preview
 * says per row exactly what will happen (add / already enrolled / error), so a
 * school can load a whole class from a prepared list without raw database work.
 *
 * CSV: one pupil per line, "first name(s), last name [, gender [, birth date]]".
 * A header row is detected and skipped. Gender m/f is optional (used by the
 * boy/girl analyses); birth date is optional (YYYY-MM-DD).
 */
class StudentImport extends Component
{
    use WithFileUploads;

    public $file = null;

    public ?int $offeringId = null;

    /** @var array<int, array{line:int,first:string,last:string,gender:?string,birth:?string,status:string,note:string}> */
    public array $rows = [];

    public bool $imported = false;

    public int $importedCount = 0;

    public function updatedFile(): void
    {
        $this->imported = false;
        $this->validate(['file' => 'file|max:2048|mimes:csv,txt']);
        $this->parse();
    }

    public function updatedOfferingId(): void
    {
        // Re-check duplicates against the newly chosen class.
        if ($this->rows) {
            $this->revalidate();
        }
    }

    private function parse(): void
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($this->file->get()));
        $this->rows = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = array_map('trim', str_getcsv($line));

            // Header row: skip when the first cells look like column names.
            if ($i === 0 && preg_match('/first|name|voornaam/i', $cols[0] ?? '')) {
                continue;
            }

            $this->rows[] = [
                'line' => $i + 1,
                'first' => $cols[0] ?? '',
                'last' => $cols[1] ?? '',
                'gender' => isset($cols[2]) && $cols[2] !== '' ? strtolower(substr($cols[2], 0, 1)) : null,
                'birth' => isset($cols[3]) && $cols[3] !== '' ? $cols[3] : null,
                'status' => 'pending',
                'note' => '',
            ];
        }

        $this->revalidate();
    }

    private function revalidate(): void
    {
        $enrolledNames = $this->offeringId
            ? Enrollment::where('offering_id', $this->offeringId)
                ->join('users', 'users.id', '=', 'enrollments.user_id')
                ->get(['users.first_name', 'users.last_name'])
                ->map(fn ($u) => mb_strtolower($u->first_name.'|'.$u->last_name))
                ->flip()
            : collect();

        $seen = [];
        foreach ($this->rows as $k => $row) {
            $key = mb_strtolower($row['first'].'|'.$row['last']);
            [$status, $note] = ['ok', 'will be added'];

            if ($row['first'] === '' || $row['last'] === '') {
                [$status, $note] = ['error', 'first and last name are both required'];
            } elseif ($row['gender'] !== null && ! in_array($row['gender'], ['m', 'f'], true)) {
                [$status, $note] = ['error', 'gender must be m or f (or left empty)'];
            } elseif ($row['birth'] !== null && ! strtotime($row['birth'])) {
                [$status, $note] = ['error', 'birth date not understood — use YYYY-MM-DD'];
            } elseif (isset($seen[$key])) {
                [$status, $note] = ['error', 'appears twice in this file (line '.$seen[$key].')'];
            } elseif (isset($enrolledNames[$key])) {
                [$status, $note] = ['skip', 'already enrolled in this class — will be skipped'];
            }

            $seen[$key] = $seen[$key] ?? $row['line'];
            $this->rows[$k]['status'] = $status;
            $this->rows[$k]['note'] = $note;
        }
    }

    public function confirm(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['headmaster', 'admin']), 403);

        $offering = Offering::findOrFail($this->offeringId);

        // All-or-nothing: any problem line blocks the whole import (the UI says
        // so, and this enforces it server-side) — a typo must never half-land.
        if (array_filter($this->rows, fn ($r) => $r['status'] === 'error')) {
            return;
        }
        $toAdd = array_filter($this->rows, fn ($r) => $r['status'] === 'ok');
        if (! $toAdd) {
            return;
        }

        // A whole class at once must also finish on a Raspberry Pi: one shared
        // random hash instead of a bcrypt per pupil (they sign in via the name
        // picker, never with a password), the role fetched once, and no PHP time
        // limit while the transaction runs.
        set_time_limit(0);
        $sharedHash = bcrypt(str()->random(24));
        $studentRole = \Spatie\Permission\Models\Role::findByName('student');

        DB::transaction(function () use ($offering, $toAdd, $sharedHash, $studentRole) {
            foreach ($toAdd as $row) {
                $student = Student::create([
                    'first_name' => $row['first'],
                    'last_name' => $row['last'],
                    'password' => $sharedHash,
                    'archived' => false,
                ]);
                $student->assignRole($studentRole);
                if ($row['gender'] || $row['birth']) {
                    Profile::create([
                        'user_id' => $student->id,
                        'gender' => $row['gender'],
                        'birth_date' => $row['birth'] ? date('Y-m-d', strtotime($row['birth'])) : null,
                    ]);
                }
                Enrollment::create(['user_id' => $student->id, 'offering_id' => $offering->id]);
            }
        });

        $this->importedCount = count($toAdd);
        $this->imported = true;
        $this->rows = [];
        $this->file = null;
    }

    public function render()
    {
        $year = Schoolyear::current() ?? Schoolyear::orderByDesc('id')->first();
        $offerings = $year
            ? Offering::where('schoolyear_id', $year->id)->with('grade')->get()
                ->sortBy(fn ($o) => \App\Models\Grade::sortKey($o->grade?->name).'-'.$o->name)->values()
            : collect();

        return view('livewire.student-import', [
            'offerings' => $offerings,
            'okCount' => count(array_filter($this->rows, fn ($r) => $r['status'] === 'ok')),
            'errorCount' => count(array_filter($this->rows, fn ($r) => $r['status'] === 'error')),
            'skipCount' => count(array_filter($this->rows, fn ($r) => $r['status'] === 'skip')),
        ]);
    }
}
