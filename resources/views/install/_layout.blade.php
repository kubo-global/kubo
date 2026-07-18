<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <title>{{ 'KUBO | Set up'.(isset($heading) ? ' · '.$heading : '') }}</title>
</head>
<body class="relative min-h-screen bg-[#0f1030]">
  <x-aurora-bg />
  @php
    $onWelcome = request()->routeIs('install.index');
    $step = ['install.school' => 1, 'install.structure' => 2, 'install.classes' => 3, 'install.features' => 4, 'install.admin' => 5, 'install.review' => 6][request()->route()?->getName()] ?? 0;
    $steps = ['School', 'Year', 'Classes', 'Features', 'Account', 'Review'];
  @endphp
  <div class="relative z-10 max-w-xl px-4 py-10 mx-auto sm:py-14">

    <div class="text-center">
      <svg class="w-auto h-16 mx-auto text-white fill-current drop-shadow" viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg">
        <path d="M228.39 76.13l11.24-6.48L125.94 4 1 76.13 38.9 98v87.53l87 50.24 87-50.24V85l-87 50.26-75.77-43.73 64.57-37.28-11.24-6.48L38.9 85l-15.43-8.87L125.94 17l102.45 59.13zM50.14 104.49l75.8 43.84 75.79-43.84V179l-75.79 43.83L50.14 179v-74.51z"/>
      </svg>
      <h1 class="mt-5 text-3xl font-extrabold tracking-tight text-white">Welcome to KUBO</h1>
      <p class="mt-2 text-base text-indigo-100">{{ $onWelcome ? "Let's install KUBO for your school. This is where it all begins, and it only takes a few minutes." : 'Setting up your school' }}</p>
    </div>

    {{-- Step indicator (hidden on the welcome intro) --}}
    @unless ($onWelcome)
    <ol class="flex items-center justify-center mt-7">
      @foreach ($steps as $i => $label)
        @php $n = $i + 1; @endphp
        <li class="flex items-center">
          <span class="flex items-center justify-center text-xs font-bold rounded-full w-7 h-7 {{ $step >= $n ? 'bg-white text-indigo-700' : 'text-white bg-white/20' }}">{{ $n }}</span>
          <span class="ml-2 mr-3 text-xs font-medium {{ $step >= $n ? 'text-white' : 'text-indigo-200' }} hidden sm:inline">{{ $label }}</span>
          @if ($n < count($steps))
            <span class="w-5 h-px mr-3 sm:w-8 {{ $step > $n ? 'bg-white' : 'bg-white/30' }}"></span>
          @endif
        </li>
      @endforeach
    </ol>
    @endunless

    <div class="p-6 mt-6 bg-white shadow-xl sm:p-8 rounded-2xl">
      @if ($errors->any())
        <div class="p-3 mb-5 text-sm text-red-700 border border-red-200 rounded-lg bg-red-50">
          <ul class="space-y-0.5 list-disc list-inside">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
        </div>
      @endif
      @yield('content')
    </div>

    <p class="mt-6 text-xs text-center text-indigo-200">KUBO School Platform</p>
  </div>
</body>
</html>
