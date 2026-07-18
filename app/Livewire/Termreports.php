<?php

namespace App\Livewire;

use App\Models\Offering;
use App\Models\Schoolyear;
use Livewire\Component;
use Livewire\Attributes\Computed;

class Termreports extends Component
{
    public $selectedSchoolyear = 0;
    public $selectedTerm = 0;
    public $selectedGrade = 0;
    

    public function mount()
    {
        $schoolyear = Schoolyear::orderBy('id', 'desc')->first();
        $this->selectedSchoolyear = $schoolyear->id;
        $this->selectedTerm = $schoolyear->terms()->first()->id ?? 0;
        
        $offering = Offering::inSchoolyear($this->selectedSchoolyear)->first();
        $this->selectedGrade = $offering?->grade->id ?? 0;
    }

    #[Computed]
    public function schoolyears()
    {
        return Schoolyear::orderBy('id', 'desc')->get();
    }

    #[Computed]
    public function terms()
    {
        return Schoolyear::find($this->selectedSchoolyear)?->terms ?? collect();
    }

    #[Computed]
    public function offerings()
    {
        return Offering::inSchoolyear($this->selectedSchoolyear)->get();
    }

    public function generateReport()
    {
        return $this->redirect(route('term-report.print-for-class', [
            'schoolyearId' => $this->selectedSchoolyear,
            'termId' => $this->selectedTerm,
            'gradeId' => $this->selectedGrade,
        ]));
    }

    public function prepareReports()
    {
        $offering = Offering::inSchoolyear($this->selectedSchoolyear)
            ->where('grade_id', $this->selectedGrade)
            ->first();

        if (! $offering) {
            return;
        }

        return $this->redirect(route('term-report.prepare', [
            'offering' => $offering->id,
            'term' => $this->selectedTerm,
        ]));
    }

    public function render()
    {
        return view('livewire.termreport.index');
    }
}
