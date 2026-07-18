@php
    // An explicit $match (string or array of route patterns) wins — used where the
    // derived prefix would be too greedy (e.g. 'health.index' -> 'health.*' would
    // also light up under health.wound-cases.* and health.incidents.*).
    if (!empty($match ?? null)) {
        $isActive = request()->routeIs($match);
    } else {
        // 'lesson-plans.index' -> match 'lesson-plans.*' so edit/show pages
        // still highlight the menu entry. Routes without .index (e.g.
        // 'reporting.grades') fall back to a prefix match too.
        $prefix = str_ends_with($route, '.index')
            ? substr($route, 0, -6) . '.*'
            : $route . '*';
        $isActive = request()->routeIs($prefix);
    }

    // Per-group accent: the section sets $navColor; an item may override with $color.
    // [ idle icon colour, active link bg+text, active icon colour ]
    $accents = [
        'indigo' => ['text-indigo-500', 'bg-indigo-50 text-indigo-700', 'text-indigo-600'],
        'green'  => ['text-green-600',  'bg-green-50 text-green-700',    'text-green-600'],
        'pink'   => ['text-pink-500',   'bg-pink-50 text-pink-700',      'text-pink-600'],
        'slate'  => ['text-slate-400',  'bg-slate-100 text-slate-700',   'text-slate-600'],
    ];
    [$iconIdle, $activeClass, $iconActive] = $accents[$color ?? ($navColor ?? 'slate')] ?? $accents['slate'];
@endphp
<a href="{{ route($route) }}"
    class="group flex items-center px-2 py-2 text-sm leading-5 font-medium rounded-md focus:outline-none transition ease-in-out duration-150
    {{ $isActive ? $activeClass : 'text-gray-700 hover:bg-gray-100 focus:bg-gray-50' }}"
    :title="!sidebarOpen ? '{{ $title }}' : ''">
    <svg class="h-6 w-6 flex-shrink-0 transition ease-in-out duration-150 {{ $isActive ? $iconActive : $iconIdle }}"
        :class="sidebarOpen ? 'mr-3' : 'mx-auto'"
        fill="none" viewBox="0 0 24 24" stroke="currentColor">
        {!! $icon !!}
    </svg>
    <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ $title }}</span>
</a>
