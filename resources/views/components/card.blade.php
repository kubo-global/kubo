<a href="{{ !isset($routeParams) ? route($route) : route($route, $routeParams) }}" class="block bg-gray-100 rounded-lg shadow hover:bg-indigo-100 transition-colors">
    <div class="px-4 py-5 sm:p-6">
      <div class="flex items-center">
        <img class="w-14 sm:w-20 shrink-0" src="{{ $illustration}}" alt="" role="presentation"/>
        <div class="ml-4 min-w-0">
          <p class="text-lg sm:text-xl font-medium text-gray-600">
            {{ $title }}
          </p>
          @isset($data)
          <p class="text-xl font-semibold text-gray-900">{{ $data }}</p>
          @endisset
        </div>
      </div>
    </div>
</a>