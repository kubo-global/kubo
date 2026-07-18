<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    /** Staff roles that make up the printable staff list. */
    private const STAFF_ROLES = ['teacher', 'headmaster', 'admin', 'caregiver', 'assistant_coordinator'];

    /**
     * Printable staff list (No · Name · PRN · TIN · Gender · Status · Date of
     * Appointment · Date of Confirmation · Contact) — the board schools keep on
     * the office wall. Reads employment facts from StaffProfile, demographics
     * from Profile.
     */
    public function staffList()
    {
        $school = School::first();

        $staff = User::whereHas('roles', fn ($q) => $q->whereIn('name', self::STAFF_ROLES))
            ->where('archived', false)
            ->with(['staffProfile.status', 'profile'])
            ->orderBy('first_name')
            ->get();

        $pdf = PDF::loadView('print.staff-list', [
            'school' => $school,
            'staff'  => $staff,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Staff list'.($school ? ' '.$school->name : '').'.pdf');
    }

    public function index(Request $request)
    {
        $showArchived = $request->boolean('show_archived');

        $users = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['teacher', 'headmaster', 'admin', 'caregiver', 'assistant_coordinator']))
            ->when(!$showArchived, fn ($q) => $q->where('archived', false))
            // The row renders role names + per-user / role-granted permissions; eager-load
            // both so it doesn't N+1 across every staff member.
            ->with(['roles.permissions', 'permissions'])
            ->orderBy('first_name')
            ->get();

        $archivedCount = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['teacher', 'headmaster', 'admin', 'caregiver', 'assistant_coordinator']))
            ->where('archived', true)
            ->count();

        return view('pages.users.index', compact('users', 'showArchived', 'archivedCount'));
    }

    public function create()
    {
        return view('pages.users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:4',
            'roles' => 'required|array|min:1',
            'roles.*' => 'in:teacher,headmaster,admin,system_admin,caregiver,assistant_coordinator',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'password' => bcrypt($validated['password']),
            // New staff set their own password on first sign-in.
            'must_change_password' => true,
        ]);

        $user->syncRoles($validated['roles']);

        return redirect()->route('users.index')
            ->with('success', "{$user->first_name} {$user->last_name} created.");
    }

    public function edit(User $user)
    {
        $schoolyear = \App\Models\Schoolyear::current() ?? \App\Models\Schoolyear::latest();
        $offerings = $schoolyear
            ? \App\Models\Offering::where('schoolyear_id', $schoolyear->id)
                ->with('grade')->get()->sortBy('grade_id')
            : collect();

        // For each offering, the subjects taught in it (any term).
        $subjectsByOffering = [];
        foreach ($offerings as $offering) {
            $subjectsByOffering[$offering->id] = \App\Models\Subject::whereExists(function ($q) use ($offering) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('subject_term_offering')
                  ->whereColumn('subject_term_offering.subject_id', 'subjects.id')
                  ->where('subject_term_offering.offering_id', $offering->id);
            })->orderBy('name')->get();
        }

        $existingAssignments = \App\Models\TeacherAssignment::where('user_id', $user->id)
            ->get()
            ->map(fn ($a) => $a->offering_id . ':' . $a->subject_id)
            ->all();

        $user->load('staffProfile', 'profile');
        $statuses = \App\Models\StaffStatus::orderBy('display_order')->orderBy('label')->get();

        return view('pages.users.edit', compact('user', 'offerings', 'subjectsByOffering', 'existingAssignments', 'statuses'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'roles' => 'required|array|min:1',
            'roles.*' => 'in:teacher,headmaster,admin,system_admin,caregiver,assistant_coordinator',
            'assignments' => 'array',
            'assignments.*' => 'string|regex:/^\d+:\d+$/',
            // Employment record (staff list) + shared demographics.
            'prn' => 'nullable|string|max:40',
            'tin' => 'nullable|string|max:40',
            'staff_status_id' => 'nullable|exists:staff_statuses,id',
            'appointed_on' => 'nullable|date',
            'confirmed_on' => 'nullable|date',
            'gender' => 'nullable|in:M,F',
            'primary_phone' => 'nullable|string|max:40',
        ]);

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
        ]);

        $user->syncRoles($validated['roles']);

        // Employment record lives in staff_profiles (staff-only), demographics in the shared profile.
        $user->staffProfile()->updateOrCreate([], [
            'prn' => $validated['prn'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'staff_status_id' => $validated['staff_status_id'] ?? null,
            'appointed_on' => $validated['appointed_on'] ?? null,
            'confirmed_on' => $validated['confirmed_on'] ?? null,
        ]);
        $user->profile()->updateOrCreate([], [
            'gender' => $validated['gender'] ?? null,
            'primary_phone' => $validated['primary_phone'] ?? null,
        ]);

        // Replace the user's subject assignments with the submitted set.
        \App\Models\TeacherAssignment::where('user_id', $user->id)->delete();
        foreach ($validated['assignments'] ?? [] as $pair) {
            [$offeringId, $subjectId] = array_map('intval', explode(':', $pair));
            \App\Models\TeacherAssignment::create([
                'user_id' => $user->id,
                'offering_id' => $offeringId,
                'subject_id' => $subjectId,
                'is_class_teacher' => false,
            ]);
        }

        return redirect()->route('users.index')
            ->with('success', "{$user->first_name} {$user->last_name} updated.");
    }

    public function resetPassword(User $user)
    {
        $user->password = bcrypt('secret');
        $user->must_change_password = true;
        $user->save();

        return back()->with('success', "{$user->first_name}'s password was reset to \"secret\" — share it so they can sign in, then they'll set their own password.");
    }

    public function destroy(User $user)
    {
        if ($user->enrollments()->exists()) {
            return back()->with('error', "Cannot delete {$user->first_name} — they have enrollments.");
        }

        $name = $user->first_name . ' ' . $user->last_name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', "{$name} deleted.");
    }

    public function toggleArchive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', "You can't archive your own account.");
        }

        // `archived` is not in User::$fillable, so update() silently no-ops.
        // Set it directly and save.
        $user->archived = !$user->archived;
        $user->save();

        $verb = $user->archived ? 'archived' : 'restored';

        return back()->with('success', "{$user->first_name} {$user->last_name} {$verb}.");
    }

    public function toggleHealthAccess(User $user)
    {
        $hasViaRole = $user->hasPermissionTo('view medical records') && !$user->hasDirectPermission('view medical records');
        if ($hasViaRole) {
            // Already granted via a role — the per-user toggle can't override
            // role-level grants. Adjust the Settings → Permissions matrix
            // if you want to change that role's defaults.
            return back()->with('error', "{$user->first_name} already has health access via their role.");
        }

        if ($user->hasDirectPermission('view medical records')) {
            $user->revokePermissionTo('view medical records');
            $verb = 'revoked';
        } else {
            $user->givePermissionTo('view medical records');
            $verb = 'granted';
        }

        return back()->with('success', "Health access {$verb} for {$user->first_name} {$user->last_name}.");
    }
}
