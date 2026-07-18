<x-page title="Add User">
  <div class="w-full px-4 py-8 sm:w-3/4 sm:px-0 mx-auto">
    <h2 class="text-lg font-medium text-gray-900">Add a new user</h2>
    <p class="mt-1 text-sm text-gray-500 mb-6">Create a teacher, admin, or staff account.</p>

    @if($errors->any())
    <div class="p-3 mb-4 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
      @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('users.store') }}">
      @csrf
      <div class="space-y-4">
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
        </div>
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">Email (optional)</label>
          <input type="email" name="email" id="email" value="{{ old('email') }}"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
          <input required type="password" name="password" id="password"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Roles</label>
          @php
            $roleOptions = [
              'headmaster' => 'Headmaster',
              'admin' => 'Administration',
              'system_admin' => 'System admin',
              'teacher' => 'Teacher',
              'caregiver' => 'Caregiver',
              'assistant_coordinator' => 'Assistant coordinator',
            ];
          @endphp
          <div class="flex flex-col gap-2">
            @foreach($roleOptions as $role => $label)
            <label class="inline-flex items-center">
              <input type="checkbox" name="roles[]" value="{{ $role }}" {{ in_array($role, old('roles', [])) ? 'checked' : '' }}
                class="h-4 w-4 rounded border-gray-300 text-gray-900">
              <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
            </label>
            @endforeach
          </div>
        </div>
        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
          <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
          <button type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">
            Create user
          </button>
        </div>
      </div>
    </form>
  </div>
</x-page>
