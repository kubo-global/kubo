{{-- $title names the browser tab; $heading, when given, is what the page itself
     says it is ("Student record"), so the name can live in the page instead. --}}
@props(['title' => '', 'heading' => null])
<div x-data="{}" class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-white lg:px-8">
  <button @click="$dispatch('toggle')"
    class="text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 lg:hidden"
    aria-label="Open sidebar">
    <svg class="w-6 h-6" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"
      stroke="currentColor">
      <path d="M4 6h16M4 12h8m-8 6h16"></path>
    </svg>
  </button>
    @if(isset($linkTitle))
      <a href="{{url()->previous()}}" class="inline-flex items-center justify-center text-gray-700 hover:text-indigo-600">
        <svg class="hidden w-6 h-6 lg:inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
          </svg>{{ $linkTitle }}</a>
    @else
      <h1 class="inline-flex justify-center text-lg font-medium leading-6 text-gray-800 lg:text-left sm:truncate">
        {{ $heading ?: $title }}
      </h1>
    @endif
  <div class="flex items-center">
    <div x-data="{ isOpen: false }" class="relative inline-block text-left">
      <div @click="isOpen = !isOpen" @click.away="isOpen = false">
        <button class="flex items-center gap-2 text-gray-600 hover:text-gray-900 focus:outline-none"
          aria-label="Options" id="options-menu" aria-haspopup="true" aria-expanded="true">
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-700 ring-1 ring-indigo-700/20">
            <span class="text-lg font-medium leading-none text-white">{{Auth::user()->getInitials()}}</span>
          </span>
        </button>
      </div>
      <div x-show="isOpen" x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 w-56 mt-2 origin-top-right rounded-md shadow-lg z-50">
        <div class="bg-white rounded-md shadow-xs">
          <div class="px-4 py-3 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-900">{{ Auth::user()->full_name }}</p>
            @if (config('app.demo'))
              <p class="mt-0.5 text-xs text-gray-500">
                Demo: the {{ str_replace('_', ' ', Auth::user()->getRoleNames()->first() ?? 'visitor') }}
              </p>
            @endif
          </div>

          {{-- In the demo, switching role is the thing a visitor came to do. It sits
               in the banner, but this menu is where anyone looks for "who am I". --}}
          @if (config('app.demo'))
          <div class="py-1 border-b border-gray-100" role="menu" aria-orientation="vertical">
            <a href="{{ route('demo.picker') }}"
              class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 hover:text-gray-900"
              role="menuitem">Switch role</a>
            <form method="POST" action="{{ route('demo.reset') }}"
              onsubmit="return confirm('Reset the demo to fresh sample data? This wipes anything changed in the demo and takes about half a minute.')">
              @csrf
              <button type="submit"
                class="block w-full px-4 py-2 text-sm leading-5 text-left text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                role="menuitem">Reset the demo data</button>
            </form>
          </div>
          @endif

          <div class="py-1" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
            @hasanyrole('headmaster|admin|teacher|caregiver')
            <a href="{{route('profile')}}"
              class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900"
              role="menuitem">Account settings</a>
            @endhasanyrole
              <a href="{{route('logout')}}"
              class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900"
              role="menuitem">Sign out</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>