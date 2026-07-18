@php use App\Modules\Registry; $navColor = 'indigo'; @endphp

{{-- ======= STUDENTS ======= --}}
@role('student')

{{-- Navigate to learn — students only --}}
@if (Registry::enabled('learn'))
@include('components.navigation.menu-item',[
'title' => 'Learn',
'route' => 'learn.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />',
])
@endif

@endrole

{{-- ======= TEACHING (daily work) ======= --}}
@hasanyrole('headmaster|admin|teacher|assistant_coordinator')

@if (Registry::enabled('students'))
@include('components.navigation.menu-item',[
'title' => 'My Students',
'route' => 'students.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />',
])
@endif

@if (Registry::enabled('grades'))
@include('components.navigation.menu-item',[
'title' => 'Scorebook',
'route' => 'reporting.grades',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />',
])
@endif

@include('components.navigation.menu-item',[
'title' => 'Timetable',
'route' => 'timetable.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
])

@include('components.navigation.menu-item',[
'title' => 'Instructional hours',
'route' => 'instructional-hours.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
])

@endhasanyrole

@hasanyrole('headmaster|admin|teacher|assistant_coordinator')
@if (Registry::enabled('lesson_plans'))
@include('components.navigation.menu-item',[
'title' => 'Lesson Plans',
'route' => 'lesson-plans.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />',
])
@endif
@endhasanyrole

{{-- ======= LEARNING (Kolibri — experimental) ======= --}}
@php $navColor = 'green'; @endphp
@hasanyrole('headmaster|admin|teacher')
@if (Registry::enabled('library') || Registry::enabled('progress'))
@include('components.navigation.menu-section', ['label' => 'Learning'])

@if (Registry::enabled('library'))
@hasanyrole('headmaster|admin')
@include('components.navigation.menu-item',[
'title' => 'Content mapping',
'route' => 'content.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />',
])
@endhasanyrole
@endif

@if (Registry::enabled('progress'))
@include('components.navigation.menu-item',[
'title' => 'Skills',
'route' => 'progress.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
])
@endif
@endif
@endhasanyrole

{{-- Caregiver isn't teaching staff, but opens a pupil's page to record health --}}
@role('caregiver')
@if (Registry::enabled('students'))
@include('components.navigation.menu-item',[
'title' => 'Students',
'route' => 'students.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />',
])
@endif
@endrole

{{-- ======= HEALTH ======= --}}
@php $navColor = 'pink'; @endphp
@if (Registry::enabled('health'))
@can('view medical records')
@include('components.navigation.menu-section', ['label' => 'Health'])

{{-- One entry point. Checkups, incidents and wound cases are views inside the
     desk, not separate destinations: a caregiver starts from "who am I seeing"
     or "who needs follow-up", never from a record type. --}}
@include('components.navigation.menu-item',[
'title' => 'Health',
'route' => 'health.index',
'match' => ['health.index', 'health.pupil', 'health.create', 'health.store', 'health.show', 'health.edit', 'health.update',
            'health.incidents.index', 'health.incidents.create', 'health.incidents.edit',
            'health.wound-cases.index', 'health.wound-cases.create', 'health.wound-cases.edit',
            'health.notes.create', 'health.notes.edit'],
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />',
])
@endcan
@endif

{{-- ======= ADMINISTRATION ======= --}}
{{-- New School Year is a once-a-year action — it lives in Settings > School year
     and is surfaced on the dashboard near year-end, not as a permanent nav item. --}}
@php $navColor = 'slate'; @endphp
@canany(['manage users', 'manage backups', 'manage settings'])
@include('components.navigation.menu-section', ['label' => 'Administration'])

@can('manage users')
@include('components.navigation.menu-item',[
'title' => 'Users',
'route' => 'users.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />',
])
@endcan

@can('manage backups')
@include('components.navigation.menu-item',[
'title' => 'Backups',
'route' => 'backup.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />',
])
@endcan

@can('manage settings')
@include('components.navigation.menu-item',[
'title' => 'Settings',
'route' => 'settings.index',
'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
])
@endcan
@endcanany
