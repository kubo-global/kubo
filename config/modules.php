<?php

/**
 * Module registry — defines what the school's headmaster can toggle on
 * and off from the Modules tab in Settings. Routes for disabled modules
 * still respond if URLs are typed directly; this only governs menu
 * visibility (the Spatie role checks remain the actual permission floor).
 *
 * Schema per entry:
 *   label           Display name shown in Settings.
 *   description     One-line explainer for the Settings UI.
 *   default_enabled Whether the module is on for new schools (default true).
 *   always_on       If true, the module can't be disabled. Use for core
 *                   workflows where disabling them would orphan data
 *                   (Students, Grades).
 */

return [
    'students' => [
        'label' => 'Students',
        'description' => 'Student profiles, contacts, enrollments.',
        'default_enabled' => true,
        'always_on' => true,
    ],
    'grades' => [
        'label' => 'Grades & Assessments',
        'description' => 'Tests, exams, score-book, and term reports.',
        'default_enabled' => true,
        'always_on' => true,
    ],
    'progress' => [
        'label' => 'Progress',
        'description' => 'Per-student progress analytics and dashboards.',
        'default_enabled' => true,
    ],
    'lesson_plans' => [
        'label' => 'Lesson Plans',
        'description' => 'Daily lesson planning with coordinator sign-off.',
        'default_enabled' => true,
    ],
    'library' => [
        'label' => 'Kolibri Library',
        'description' => 'Browse and map Kolibri content to the curriculum. Requires a running Kolibri server.',
        // Experimental: ships off for new schools; opt in from Settings.
        'default_enabled' => false,
        'beta' => true,
    ],
    'learn' => [
        'label' => 'Student Learn',
        'description' => 'Student-facing exercise dashboard backed by mapped Kolibri content.',
        'default_enabled' => false,
        'beta' => true,
    ],
    'health' => [
        'label' => 'Health',
        'description' => 'Student health reports and milestone tracking.',
        'default_enabled' => true,
    ],
];
