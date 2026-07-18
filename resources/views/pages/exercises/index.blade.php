<x-page title='Exercises'>
  <div class="mx-6">
    <div class="min-w-full mt-8 align-middle">

      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500 hidden sm:block">Create exercises for your students.</p>
        <a href="{{ route('exercises.create') }}"
          class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-gray-900 rounded-md shadow-sm hover:bg-gray-800">
          Create Exercise
        </a>
      </div>

      @if($exercises->isEmpty())
        <div class="my-8 p-8 text-center text-gray-500 border border-gray-200 rounded-md">
          No exercises yet. Create your first one to get started.
        </div>
      @else
        {{-- Mobile: card layout --}}
        <div class="sm:hidden mt-4 space-y-3">
          @foreach($exercises as $exercise)
          <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-start justify-between">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ $exercise->title }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $exercise->skill->name ?? $exercise->subject->name ?? '' }} &middot; {{ $exercise->questions->count() }} questions</p>
              </div>
              @if($exercise->channel_synced_at)
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Published</span>
              @else
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Draft</span>
              @endif
            </div>
            <div class="flex items-center gap-4 mt-3 text-sm">
              <a href="{{ route('exercises.edit', $exercise) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
              <form method="POST" action="{{ route('exercises.destroy', $exercise) }}" class="inline"
                    onsubmit="return confirm('Delete this exercise?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
              </form>
            </div>
          </div>
          @endforeach
        </div>

        {{-- Desktop: table layout --}}
        <div class="hidden sm:block mt-6 border border-gray-200 rounded-lg shadow overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skill</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Questions</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              @foreach($exercises as $exercise)
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $exercise->title }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $exercise->skill->name ?? '—' }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">{{ $exercise->questions->count() }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    @if($exercise->channel_synced_at)
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Published</span>
                    @else
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Draft</span>
                    @endif
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                    <a href="{{ route('exercises.edit', $exercise) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                    <form method="POST" action="{{ route('exercises.destroy', $exercise) }}" class="inline"
                          onsubmit="return confirm('Delete this exercise?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif

    </div>
  </div>
</x-page>
