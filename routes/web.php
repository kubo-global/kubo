<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NewInterfaceControllers\InstallController;

// First-run setup wizard (gated by EnsureInstalled; redirects away once set up).
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');
    Route::get('/school', [InstallController::class, 'school'])->name('school');
    Route::post('/school', [InstallController::class, 'storeSchool'])->name('school.store');
    Route::get('/structure', [InstallController::class, 'structure'])->name('structure');
    Route::post('/structure', [InstallController::class, 'storeStructure'])->name('structure.store');
    Route::get('/classes', [InstallController::class, 'classes'])->name('classes');
    Route::post('/classes', [InstallController::class, 'storeClasses'])->name('classes.store');
    Route::get('/features', [InstallController::class, 'features'])->name('features');
    Route::post('/features', [InstallController::class, 'storeFeatures'])->name('features.store');
    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/admin', [InstallController::class, 'storeAdmin'])->name('admin.store');
    Route::get('/review', [InstallController::class, 'review'])->name('review');
    Route::post('/complete', [InstallController::class, 'complete'])->name('complete');
});
use App\Http\Controllers\NewInterfaceControllers\Auth\LoginController;
use App\Http\Controllers\NewInterfaceControllers\Auth\PasswordChangeController;
use App\Http\Controllers\NewInterfaceControllers\Auth\UserController;
use App\Http\Controllers\NewInterfaceControllers\BackupController;
use App\Http\Controllers\NewInterfaceControllers\ContactController;
use App\Http\Controllers\NewInterfaceControllers\DashboardController;
use App\Http\Controllers\NewInterfaceControllers\StudentController;
use App\Http\Controllers\NewInterfaceControllers\NatReportController;
use App\Http\Controllers\NewInterfaceControllers\ReportController;
use App\Http\Controllers\NewInterfaceControllers\AttendanceController;
use App\Http\Controllers\NewInterfaceControllers\InstructionalHoursController;
use App\Http\Controllers\NewInterfaceControllers\ScorebookController;
use App\Http\Controllers\NewInterfaceControllers\TermResultController;
use App\Http\Controllers\NewInterfaceControllers\TimetableController;
use App\Http\Controllers\NewInterfaceControllers\ScoreSheetController;
use App\Http\Controllers\NewInterfaceControllers\HealthController;
use App\Http\Controllers\NewInterfaceControllers\ExerciseController;
use App\Http\Controllers\NewInterfaceControllers\ProgressController;
use App\Http\Controllers\NewInterfaceControllers\AssessmentController;
use App\Http\Controllers\NewInterfaceControllers\ContentBrowserController;
use App\Http\Controllers\NewInterfaceControllers\RolloverController;
use App\Http\Controllers\NewInterfaceControllers\SettingsController;
use App\Http\Controllers\NewInterfaceControllers\UserManagementController;
use App\Http\Controllers\NewInterfaceControllers\LearnController;
use App\Http\Controllers\NewInterfaceControllers\StudentLoginController;

// Home
Route::redirect('/', 'dashboard');

// Demo: pick a role and be signed in as that person, and switch role at will.
// All of it 404s unless DEMO_MODE is on.
Route::get('demo', [\App\Http\Controllers\NewInterfaceControllers\DemoController::class, 'picker'])
    ->name('demo.picker');
Route::post('demo/login/{role}', [\App\Http\Controllers\NewInterfaceControllers\DemoController::class, 'login'])
    ->name('demo.login');
// Shareable deep link: /demo/teacher etc. signs the visitor in as that persona.
// GET-with-side-effect is fine here: DEMO_MODE only, sandbox accounts, nightly reset.
Route::get('demo/{role}', [\App\Http\Controllers\NewInterfaceControllers\DemoController::class, 'loginLink'])
    ->name('demo.login-link');

// Demo: on-demand wipe-and-reseed (banner button). 404 unless DEMO_MODE is on.
Route::post('demo/reset', function (\Illuminate\Http\Request $request) {
    abort_unless(config('app.demo'), 404);
    @set_time_limit(300);
    \Illuminate\Support\Facades\Artisan::call('demo:reset');

    // The reset rebuilt every user, so whoever you were signed in as no longer
    // exists: back to the picker to choose again.
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('demo.picker')->with('success', 'The demo is back to fresh sample data.');
})->name('demo.reset');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->name('home')
    ->middleware('auth');

// Student login (select class → select name)
Route::get('student-login', [StudentLoginController::class, 'selectGrade'])
    ->name('student-login.select-grade');
Route::get('student-login/grade/{grade}', [StudentLoginController::class, 'selectClass'])
    ->name('student-login.select-class');
Route::get('student-login/class/{offering}', [StudentLoginController::class, 'selectStudent'])
    ->name('student-login.select-student');
Route::get('student-login/go/{student}', [StudentLoginController::class, 'login'])
    ->name('student-login.login');

// Auth
Route::get('login', [LoginController::class, 'showLoginForm'])
    ->name('login');

Route::post('login', [LoginController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.attempt');

Route::get('logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// KUBO Support: a time-limited, signed sign-in link minted on the machine by
// `php artisan kubo:support` for servicing. No standing password, access is gated
// by having run the command on the box (the signature uses this install's APP_KEY).
Route::get('support/login/{user}', [LoginController::class, 'supportLogin'])
    ->name('support.login')
    ->middleware('signed');

// Forced password change (after a reset, or on a new staff account's first sign-in).
Route::middleware('auth')->group(function () {
    Route::get('password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('password/change', [PasswordChangeController::class, 'update'])->name('password.update');
});

Route::get('profile', [UserController::class, 'profile'])
    ->name('profile')
    ->middleware('auth');


// Academic screens: assistant_coordinator has oversight (see everything the
// headmaster sees here). Editing sub-routes below stay role-restricted where
// they were (e.g. students create/edit remain headmaster|admin).
Route::middleware(['auth', 'role:headmaster|admin|teacher|assistant_coordinator'])->group(function () {

    // Student administration
    // (students.index + students.show live in their own group below so the
    // caregiver can reach them too — see "Student browsing".)

    Route::get('students/create', [StudentController::class, 'create'])
        ->name('students.create')
        ->middleware('role:headmaster|admin');

    // Bulk import: upload a class list -> validate -> preview -> confirm.
    Route::get('students/import', fn () => view('pages.students-import'))
        ->name('students.import')
        ->middleware('role:headmaster|admin');

    Route::get('students/{student}/edit', [StudentController::class, 'edit'])
        ->name('students.edit')
        ->middleware('role:headmaster|admin');

    Route::get('students/{student}/contacts/create', [ContactController::class, 'create'])
        ->name('contacts.create');

    Route::get('students/{student}/contacts/{contact}', [ContactController::class, 'show'])
        ->name('contacts.show');

    //reporting

    // Scorebook: click-through class list -> per-class overview -> NAT page.
    // Keeps the reporting.grades name (the menu points at it) but renders the new flow.
    Route::get('reporting/grades', [ScorebookController::class, 'index'])
        ->name('reporting.grades');
    Route::get('scorebook/class/{offering}', [ScorebookController::class, 'class'])
        ->name('scorebook.class');
    Route::get('scorebook/class/{offering}/nat', [ScorebookController::class, 'nat'])
        ->name('scorebook.nat');
    Route::get('scorebook/class/{offering}/positions', [ScorebookController::class, 'positions'])
        ->name('scorebook.positions');
    // Prepare reports: type + save each pupil's conduct and general remark for the card.
    Route::get('scorebook/class/{offering}/prepare-reports', fn (\App\Models\Offering $offering) => view('pages.scorebook.prepare-reports', ['offering' => $offering]))
        ->name('term-report.prepare');
    Route::get('scorebook/class/{offering}/result-sheet', [ScorebookController::class, 'resultSheet'])
        ->name('scorebook.result-sheet');
    // Term exam grid: enter every pupil's mark for every subject in one screen.
    Route::get('scorebook/class/{offering}/term-grid', [TermResultController::class, 'grid'])
        ->name('term-grid.edit');
    Route::post('scorebook/class/{offering}/term-grid', [TermResultController::class, 'save'])
        ->name('term-grid.save');
    Route::get('scorebook/class/{offering}/term-result-sheet', [TermResultController::class, 'resultSheet'])
        ->name('term-grid.result-sheet');
    Route::get('scorebook/class/{offering}/term-analysis', [TermResultController::class, 'analysis'])
        ->name('term-grid.analysis');
    Route::get('scorebook/class/{offering}/term-histogram', [TermResultController::class, 'histogram'])
        ->name('term-grid.histogram');
    Route::get('scorebook/class/{offering}/term-results-bundle', [TermResultController::class, 'bundle'])
        ->name('term-grid.bundle');
    Route::get('scorebook/class/{offering}/term-report', [TermResultController::class, 'report'])
        ->name('term-grid.report');
    Route::get('scorebook/class/{offering}/term', [TermResultController::class, 'term'])
        ->name('term-grid.overview');
    Route::post('scorebook/class/{offering}/term-clear', [TermResultController::class, 'clearPeriod'])
        ->name('term-grid.clear');
    Route::get('scorebook/class/{offering}/attendance', [AttendanceController::class, 'show'])
        ->name('scorebook.attendance');
    Route::post('scorebook/class/{offering}/attendance', [AttendanceController::class, 'save'])
        ->name('attendance.save');
    Route::get('scorebook/class/{offering}/attendance/summary', [AttendanceController::class, 'monthlySummary'])
        ->name('attendance.summary');
    // Timetable is a top-level feature (its own nav item), not a scorebook tab.
    Route::get('timetable', [TimetableController::class, 'index'])
        ->name('timetable.index');
    Route::get('timetable/{offering}', [TimetableController::class, 'show'])
        ->name('timetable.show');
    Route::post('timetable/{offering}', [TimetableController::class, 'update'])
        ->name('timetable.update');
    Route::get('timetable/{offering}/print', [TimetableController::class, 'print'])
        ->name('timetable.print');

    // Instructional hours (Expected from the timetable; teacher logs Actual/Lost).
    Route::get('instructional-hours', [InstructionalHoursController::class, 'index'])
        ->name('instructional-hours.index');
    Route::get('timetable/{offering}/hours', [InstructionalHoursController::class, 'show'])
        ->name('instructional-hours.show');
    Route::post('timetable/{offering}/hours', [InstructionalHoursController::class, 'save'])
        ->name('instructional-hours.save');
    Route::get('timetable/{offering}/hours/pdf', [InstructionalHoursController::class, 'pdf'])
        ->name('instructional-hours.pdf');
    Route::get('timetable/{offering}/hours/chart', [InstructionalHoursController::class, 'chart'])
        ->name('instructional-hours.chart');

    Route::get('/termreport/{enrollmentId}/{termId}', [ReportController::class, 'showTermReport'])
        ->name('term-report.show');

    Route::get('/print/termreport/{enrollmentId}/{termId}', [ReportController::class, 'generateTermReport'])
        ->name('term-report.print');

    Route::get('/report-book/{enrollmentId}', [ReportController::class, 'reportBookScreen'])
        ->name('report-book.screen');
    Route::get('/print/report-book/{enrollmentId}', [ReportController::class, 'reportBook'])
        ->name('report-book.print');
    Route::get('/print/report-books/class/{offering}', [ReportController::class, 'classReportBook'])
        ->name('report-book.print-for-class');
    
    Route::get('termreports/overview', [ReportController::class, 'overview'])
            ->name('termreports.overview');
    
    Route::get('/print/termreports/{schoolyearId}/{termId}/{gradeId}', [ReportController::class, 'generateClassTermReport'])
            ->name('term-report.print-for-class');

    // National Assessment Test (NAT) deliverables — scores listing + analysis with charts
    Route::get('/print/nat/{offering}/scores', [NatReportController::class, 'scores'])
            ->name('nat.print.scores');
    Route::get('/print/nat/{offering}/analysis', [NatReportController::class, 'analysis'])
            ->name('nat.print.analysis');

    Route::get('scorebook/create', [ScoreSheetController::class, 'create'])
        ->name('scorebook.create');

    // Assessment entry (3-step progressive form)
    Route::get('assessment/create/{type}', [AssessmentController::class, 'create'])->name('reporting.assessment.create');
    Route::post('assessment', [AssessmentController::class, 'store'])->name('reporting.assessment.store');
    Route::get('assessment/{assessment}/scores', [AssessmentController::class, 'scores'])->name('reporting.assessment.scores');
    Route::post('assessment/{assessment}/scores', [AssessmentController::class, 'saveScores'])->name('reporting.assessment.saveScores');
    Route::get('assessment/{assessment}/confirm', [AssessmentController::class, 'confirm'])->name('reporting.assessment.confirm');
    Route::post('assessment/{assessment}/confirm', [AssessmentController::class, 'finalize'])->name('reporting.assessment.finalize');
    Route::get('assessment/{assessment}/edit', [AssessmentController::class, 'edit'])->name('reporting.assessment.edit');
    Route::delete('assessment/{assessment}', [AssessmentController::class, 'destroy'])->name('reporting.assessment.delete');

});


// Student browsing: the academic roles above, plus the caregiver, who opens a
// pupil's page to record health. Registered after 'students/create' above so the
// {student} wildcard doesn't swallow it. Creating/editing a pupil stays admin.
Route::middleware(['auth'])->group(function () {
    Route::get('students', [StudentController::class, 'index'])
        ->name('students.index')
        ->middleware('role:headmaster|admin|teacher|assistant_coordinator|caregiver');
    Route::get('students/{student}', [StudentController::class, 'show'])
        ->name('students.show')
        ->middleware('role:headmaster|admin|teacher|caregiver');
});


// Learning
Route::middleware(['auth'])->group(function () {
    Route::get('learn', [LearnController::class, 'index'])->name('learn.index');
    Route::get('learn/skill/{skill}', [LearnController::class, 'skill'])->name('learn.skill');
    Route::post('learn/exercise/{skill}/start', [LearnController::class, 'startRun'])->name('learn.start');
    Route::get('learn/exercise/{skill}', [LearnController::class, 'exercise'])->name('learn.exercise');
    Route::post('learn/exercise/{skill}/complete', [LearnController::class, 'completeRun'])->name('learn.completeRun');
    Route::get('learn/complete/{skill}', [LearnController::class, 'complete'])->name('learn.complete');
    Route::get('students/{student}/skill-graph', [LearnController::class, 'skillGraph'])->name('students.skill-graph');
});

// Lesson plans — paper form digitization. Teachers create/edit own;
// coordinator and assistant_coordinator add their respective remarks.
Route::middleware(['auth', 'role:headmaster|admin|teacher|assistant_coordinator'])->group(function () {
    Route::get('lesson-plans', [\App\Http\Controllers\NewInterfaceControllers\LessonPlanController::class, 'index'])->name('lesson-plans.index');
    Route::get('lesson-plans/create', [\App\Http\Controllers\NewInterfaceControllers\LessonPlanController::class, 'create'])->name('lesson-plans.create');
    Route::post('lesson-plans', [\App\Http\Controllers\NewInterfaceControllers\LessonPlanController::class, 'store'])->name('lesson-plans.store');
    Route::get('lesson-plans/{lessonPlan}', [\App\Http\Controllers\NewInterfaceControllers\LessonPlanController::class, 'show'])->name('lesson-plans.show');
    Route::post('lesson-plans/{lessonPlan}/sign-off', [\App\Http\Controllers\NewInterfaceControllers\LessonPlanController::class, 'signOff'])->name('lesson-plans.sign-off');
    Route::get('lesson-plans/{lessonPlan}/edit', [\App\Http\Controllers\NewInterfaceControllers\LessonPlanController::class, 'edit'])->name('lesson-plans.edit');
    Route::put('lesson-plans/{lessonPlan}', [\App\Http\Controllers\NewInterfaceControllers\LessonPlanController::class, 'update'])->name('lesson-plans.update');
    Route::delete('lesson-plans/{lessonPlan}', [\App\Http\Controllers\NewInterfaceControllers\LessonPlanController::class, 'destroy'])->name('lesson-plans.destroy');
});

// Student progress (teachers)
Route::middleware(['auth', 'role:headmaster|admin|teacher'])->group(function () {
    Route::get('progress', [ProgressController::class, 'index'])->name('progress.index');
    Route::get('progress/{offering}', [ProgressController::class, 'show'])->name('progress.show');
});

// Exercise & video mapping (headmaster + admin — manage the curriculum-to-Kolibri map)
Route::middleware(['auth', 'role:headmaster|admin'])->group(function () {
    Route::get('content', [ContentBrowserController::class, 'index'])->name('content.index');
    Route::post('content/clone', [ContentBrowserController::class, 'cloneExercise'])->name('content.clone');
});

// Exercise authoring (teachers)
Route::middleware(['auth', 'role:headmaster|admin|teacher'])->group(function () {
    Route::get('exercises', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::get('exercises/create', fn () => view('pages.exercises.create'))->name('exercises.create');
    Route::get('exercises/{exercise}/edit', fn ($exercise) => view('pages.exercises.edit', ['exerciseId' => $exercise]))->name('exercises.edit');
    Route::post('exercises', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::put('exercises/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');
});

//health reporting — gated on the `view medical records` permission so an
//admin can grant access to specific assistant_coordinator users without
//giving them an additional role.
Route::middleware(['auth', 'permission:view medical records'])->group(function () {
    Route::get('health', [HealthController::class, 'index'])
        ->name('health.index');
    Route::get('health/create/{student}', [HealthController::class, 'create'])
        ->name('health.create');
    Route::post('health/store', [HealthController::class, 'store'])
        ->name('health.store');
    Route::get('health/show/{healthReport}', [HealthController::class, 'show'])
        ->name('health.show');
    Route::get('health/edit/{healthReport}', [HealthController::class, 'edit'])
        ->name('health.edit');
    Route::put('health/update/{healthReport}', [HealthController::class, 'update'])
        ->name('health.update');

    // A pupil's health record lives under Health, not on the student page: the desk
    // is where health work starts, and jumping to a pupil shouldn't drop you into
    // another section of the app.
    Route::get('health/pupils/{student}', [\App\Http\Controllers\NewInterfaceControllers\HealthController::class, 'pupil'])
        ->name('health.pupil');

    // Incident reports
    Route::get('health/incidents', [\App\Http\Controllers\NewInterfaceControllers\IncidentReportController::class, 'index'])->name('health.incidents.index');
    Route::get('health/incidents/create/{student}', [\App\Http\Controllers\NewInterfaceControllers\IncidentReportController::class, 'create'])->name('health.incidents.create');
    Route::post('health/incidents', [\App\Http\Controllers\NewInterfaceControllers\IncidentReportController::class, 'store'])->name('health.incidents.store');
    Route::get('health/incidents/{incident}/edit', [\App\Http\Controllers\NewInterfaceControllers\IncidentReportController::class, 'edit'])->name('health.incidents.edit');
    Route::put('health/incidents/{incident}', [\App\Http\Controllers\NewInterfaceControllers\IncidentReportController::class, 'update'])->name('health.incidents.update');
    Route::post('health/incidents/{incident}/close', [\App\Http\Controllers\NewInterfaceControllers\IncidentReportController::class, 'close'])->name('health.incidents.close');
    Route::delete('health/incidents/{incident}', [\App\Http\Controllers\NewInterfaceControllers\IncidentReportController::class, 'destroy'])->name('health.incidents.destroy');

    // Wound cases + visits
    Route::get('health/wound-cases', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'index'])->name('health.wound-cases.index');
    Route::get('health/wound-cases/create/{student}', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'create'])->name('health.wound-cases.create');
    Route::post('health/wound-cases', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'store'])->name('health.wound-cases.store');
    Route::get('health/wound-cases/{case}/edit', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'edit'])->name('health.wound-cases.edit');
    Route::put('health/wound-cases/{case}', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'update'])->name('health.wound-cases.update');
    Route::post('health/wound-cases/{case}/close', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'close'])->name('health.wound-cases.close');
    Route::delete('health/wound-cases/{case}', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'destroy'])->name('health.wound-cases.destroy');
    Route::post('health/wound-cases/{case}/visits', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'addVisit'])->name('health.wound-cases.add-visit');
    Route::put('health/wound-visits/{visit}', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'updateVisit'])->name('health.wound-cases.update-visit');
    Route::delete('health/wound-visits/{visit}', [\App\Http\Controllers\NewInterfaceControllers\WoundCaseController::class, 'destroyVisit'])->name('health.wound-cases.destroy-visit');

    // Medical notes
    Route::get('health/notes/create/{student}', [\App\Http\Controllers\NewInterfaceControllers\MedicalNoteController::class, 'create'])->name('health.notes.create');
    Route::post('health/notes', [\App\Http\Controllers\NewInterfaceControllers\MedicalNoteController::class, 'store'])->name('health.notes.store');
    Route::get('health/notes/{note}/edit', [\App\Http\Controllers\NewInterfaceControllers\MedicalNoteController::class, 'edit'])->name('health.notes.edit');
    Route::put('health/notes/{note}', [\App\Http\Controllers\NewInterfaceControllers\MedicalNoteController::class, 'update'])->name('health.notes.update');
    Route::delete('health/notes/{note}', [\App\Http\Controllers\NewInterfaceControllers\MedicalNoteController::class, 'destroy'])->name('health.notes.destroy');
});


// School year rollover (admin/headmaster only)
// Backup management (admin/headmaster)
Route::middleware(['auth', 'permission:manage backups'])->group(function () {
    Route::get('backups', [BackupController::class, 'index'])->name('backup.index');
    Route::get('backups/download', [BackupController::class, 'download'])->name('backup.download');
});

// Backup-status endpoint for the on-device cron / external backup tools. Not a
// logged-in user, so it's guarded by loopback-or-shared-token (VerifyBackupReport).
Route::post('api/backup/report', [BackupController::class, 'report'])
    ->middleware(\App\Http\Middleware\VerifyBackupReport::class)
    ->name('backup.report');

Route::middleware(['auth', 'permission:manage settings'])->group(function () {
    // School settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings/logo', [SettingsController::class, 'updateLogo'])->name('settings.update-logo');
    Route::post('settings/period', [SettingsController::class, 'storePeriod'])->name('settings.store-period');
    Route::delete('settings/period/{period}', [SettingsController::class, 'destroyPeriod'])->name('settings.destroy-period');
    Route::post('settings/grade', [SettingsController::class, 'storeGrade'])->name('settings.store-grade');
    Route::post('settings/grade/{grade}/color', [SettingsController::class, 'setGradeColor'])->name('settings.grade-color');
    Route::post('settings/grade/{grade}/login', [SettingsController::class, 'toggleGradeLogin'])->name('settings.grade-login');
    Route::delete('settings/grade/{grade}', [SettingsController::class, 'destroyGrade'])->name('settings.destroy-grade');
    Route::post('settings/staff-status', [SettingsController::class, 'storeStaffStatus'])->name('settings.store-staff-status');
    Route::delete('settings/staff-status/{staffStatus}', [SettingsController::class, 'destroyStaffStatus'])->name('settings.destroy-staff-status');
    Route::post('settings/subject', [SettingsController::class, 'storeSubject'])->name('settings.store-subject');
    Route::post('settings/subject/{subject}/counts-total', [SettingsController::class, 'toggleSubjectTotal'])->name('settings.toggle-subject-total');
    Route::post('settings/report-type', [SettingsController::class, 'updateReportType'])->name('settings.update-report-type');
    Route::post('settings/period-mode', [SettingsController::class, 'updatePeriodMode'])->name('settings.update-period-mode');
    Route::post('settings/term-card-layout', [SettingsController::class, 'updateTermCardLayout'])->name('settings.update-term-card-layout');
    Route::post('settings/term/{term}/lock', [SettingsController::class, 'toggleTermLock'])->name('settings.toggle-term-lock');
    Route::post('settings/grade-band', [SettingsController::class, 'storeGradeBand'])->name('settings.store-grade-band');
    Route::post('settings/grade-band/{band}', [SettingsController::class, 'updateGradeBand'])->name('settings.update-grade-band');
    Route::delete('settings/grade-band/{band}', [SettingsController::class, 'destroyGradeBand'])->name('settings.destroy-grade-band');
    Route::delete('settings/subject/{subject}', [SettingsController::class, 'destroySubject'])->name('settings.destroy-subject');
    Route::post('settings/teacher', [SettingsController::class, 'assignTeacher'])->name('settings.assign-teacher');
    Route::post('settings/teacher/remove', [SettingsController::class, 'removeTeacher'])->name('settings.remove-teacher');
    Route::post('settings/teacher/principal', [SettingsController::class, 'setPrincipal'])->name('settings.set-principal');
    Route::post('settings/teacher/subjects', [SettingsController::class, 'setSubjectTeachers'])->name('settings.set-subject-teachers');
    Route::post('settings/offering', [SettingsController::class, 'addOffering'])->name('settings.add-offering');
    Route::post('settings/offering/{offering}/rename', [SettingsController::class, 'renameOffering'])->name('settings.rename-offering');
    Route::delete('settings/offering/{offering}', [SettingsController::class, 'deleteOffering'])->name('settings.delete-offering');
    Route::post('settings/class-subject', [SettingsController::class, 'addClassSubject'])->name('settings.add-class-subject');
    Route::post('settings/class-subject/remove', [SettingsController::class, 'removeClassSubject'])->name('settings.remove-class-subject');
    Route::post('settings/class-subject/counting', [SettingsController::class, 'setClassSubjectCounting'])->name('settings.set-class-subject-counting');
    Route::post('settings/assessment-type/{assessmentType}', [SettingsController::class, 'updateAssessmentType'])->name('settings.update-assessment-type');
    Route::post('settings/gate-by-lesson-plan', [SettingsController::class, 'updateGateByLessonPlan'])->name('settings.update-gate-by-lesson-plan');
    Route::post('settings/instructional-hours', [SettingsController::class, 'updateInstructionalHours'])->name('settings.instructional-hours');
    Route::post('settings/modules/{slug}', [SettingsController::class, 'toggleModule'])->name('settings.toggle-module');
});

// The role × permission matrix is the access-control surface itself —
// gated separately so not everyone who can open Settings can rewrite it.
Route::middleware(['auth', 'permission:manage permissions'])->group(function () {
    Route::post('settings/role-permissions/{role}', [SettingsController::class, 'updateRolePermissions'])->name('settings.update-role-permissions');
});

Route::middleware(['auth', 'permission:manage rollover'])->group(function () {
    Route::get('rollover', [RolloverController::class, 'index'])->name('rollover.index');
    Route::post('rollover', [RolloverController::class, 'store'])->name('rollover.store');
    Route::get('rollover/{schoolyear}/promote', [RolloverController::class, 'promote'])->name('rollover.promote');
    Route::post('rollover/{schoolyear}/promote', [RolloverController::class, 'processPromotions'])->name('rollover.process-promotions');
    Route::get('rollover/{schoolyear}/new-students', [RolloverController::class, 'newStudents'])->name('rollover.new-students');
    Route::post('rollover/{schoolyear}/new-students', [RolloverController::class, 'enrollNewStudent'])->name('rollover.enroll-new');
});

Route::middleware(['auth', 'permission:manage users'])->group(function () {
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('users/staff-list', [UserManagementController::class, 'staffList'])->name('users.staff-list');
    Route::get('users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::post('users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('users/{user}/toggle-health-access', [UserManagementController::class, 'toggleHealthAccess'])->name('users.toggle-health-access');
    Route::post('users/{user}/toggle-archive', [UserManagementController::class, 'toggleArchive'])->name('users.toggle-archive');
    Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
});

