<?php

namespace App\Observers;

use App\Models\Enrollment;
use App\Models\School;
use KuboKolibri\Services\KolibriProvisioner;

class EnrollmentObserver
{
    /**
     * When a student is enrolled, ensure they exist in Kolibri
     * and are added to the correct Kolibri classroom.
     */
    public function created(Enrollment $enrollment): void
    {
        $this->syncToKolibri($enrollment);
    }

    private function syncToKolibri(Enrollment $enrollment): void
    {
        $student = $enrollment->student;
        $offering = $enrollment->offering;

        if (!$student || !$offering) {
            return;
        }

        $school = School::whereNotNull('kolibri_facility_id')->first();
        if (!$school?->kolibri_facility_id) {
            return;
        }

        try {
            $provisioner = app(KolibriProvisioner::class);

            // Ensure the classroom exists in Kolibri
            $classroomId = $offering->kolibri_classroom_id;
            if (!$classroomId) {
                $classroomId = $provisioner->provisionClass($offering, $school->kolibri_facility_id);
            }

            if (!$classroomId) {
                return;
            }

            // Ensure the student exists in Kolibri and is in the classroom
            $provisioner->provisionLearner($student, $school->kolibri_facility_id, $classroomId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Kolibri enrollment sync failed', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
