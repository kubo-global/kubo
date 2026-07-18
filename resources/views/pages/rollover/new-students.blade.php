<x-page :title="'New students — ' . $schoolyear->name">
  <div class="w-full px-4 py-8 sm:w-3/4 sm:px-0 mx-auto">
    <h2 class="text-lg font-medium text-gray-900">Enroll new students</h2>
    <p class="mt-1 text-sm text-gray-500 mb-6">
      Add students who are joining {{ $schoolyear->name }} for the first time.
    </p>

    {{-- Current enrollment summary --}}
    <div class="mb-8 space-y-2">
      @foreach($offerings as $offering)
      @php $count = \App\Models\Enrollment::where('offering_id', $offering->id)->count(); @endphp
      <div class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-lg">
        <span class="text-sm font-medium text-gray-700">{{ $offering->displayName() }}</span>
        <span class="text-sm text-gray-500">{{ $count }} students</span>
      </div>
      @endforeach
    </div>

    {{-- Add new student form --}}
    <div class="border border-gray-200 rounded-lg p-4 bg-white">
      <h3 class="text-sm font-medium text-gray-700 mb-4">Add a student</h3>

      <form method="POST" action="{{ route('rollover.enroll-new', $schoolyear) }}">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="first_name" class="block text-sm font-medium text-gray-700">First name</label>
            <input required type="text" name="first_name" id="first_name" value="{{ old('first_name') }}"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
          <div>
            <label for="last_name" class="block text-sm font-medium text-gray-700">Last name</label>
            <input required type="text" name="last_name" id="last_name" value="{{ old('last_name') }}"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
          <div>
            <label for="offering_id" class="block text-sm font-medium text-gray-700">Class</label>
            <select required name="offering_id" id="offering_id"
              class="block w-full px-3 py-2 mt-1 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
              @foreach($offerings as $offering)
              <option value="{{ $offering->id }}">{{ $offering->displayName() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
            <select name="gender" id="gender"
              class="block w-full px-3 py-2 mt-1 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
              <option value="">—</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
            </select>
          </div>
          <div>
            <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of birth</label>
            <input type="date" name="date_of_birth" id="date_of_birth" value="{{ old('date_of_birth') }}"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
        </div>
        <div class="mt-4 text-right">
          <button type="submit"
            class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
            Enroll
          </button>
        </div>
      </form>
    </div>

    <div class="flex items-center justify-between mt-8 pt-4 border-t border-gray-200">
      <a href="{{ route('rollover.promote', $schoolyear) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to promotions</a>
      <a href="{{ route('home') }}"
        class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
        Done
      </a>
    </div>
  </div>
</x-page>
