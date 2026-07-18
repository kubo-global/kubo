@extends('install._layout')

@section('content')
  <h2 class="text-lg font-bold text-gray-900">Your account</h2>
  <p class="mt-1 text-sm text-gray-500">This is the administrator account that sets up and runs KUBO. Once you're in, you'll add your headmaster, teachers and students, and configure the rest.</p>

  <form method="POST" action="{{ route('install.admin.store') }}" class="mt-6 space-y-5">
    @csrf

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label for="first_name" class="block text-sm font-medium text-gray-700">First name</label>
        <input id="first_name" name="first_name" type="text" required value="{{ old('first_name', $data['first_name'] ?? '') }}"
          class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
      </div>
      <div>
        <label for="last_name" class="block text-sm font-medium text-gray-700">Last name</label>
        <input id="last_name" name="last_name" type="text" required value="{{ old('last_name', $data['last_name'] ?? '') }}"
          class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
      </div>
    </div>

    <div>
      <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
      <input id="password" name="password" type="password" required
        class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
    </div>

    <div>
      <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
      <input id="password_confirmation" name="password_confirmation" type="password" required
        class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
    </div>

    <div class="flex items-center justify-between pt-2">
      <a href="{{ route('install.structure') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">&larr; Back</a>
      <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg bg-indigo-600 hover:bg-indigo-700">Continue &rarr;</button>
    </div>
  </form>

  <x-install.help>
    This is the main account you'll sign in with to run KUBO. Use your own name. You don't need an email,
    because you log in by picking your name from a list and typing your password. Choose a password you'll
    remember and keep it safe. You can change it later, and reset passwords for teachers and staff once you're in.
  </x-install.help>
@endsection
