@extends('install._layout')

@section('content')
  <h2 class="text-lg font-bold text-gray-900">Let's start with your school</h2>
  <p class="mt-1 text-sm text-gray-500">Just a few details to begin. Nothing here is final, you can update any of it later in Settings.</p>

  <form method="POST" action="{{ route('install.school.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
    @csrf

    <div>
      <label for="name" class="block text-sm font-medium text-gray-700">School name</label>
      <input id="name" name="name" type="text" required value="{{ old('name', $data['name'] ?? '') }}"
        class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
    </div>

    <div>
      <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
      <select id="country" name="country"
        class="block w-full px-3 py-2 mt-1 text-sm bg-white border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
        @foreach ($countries as $code => $label)
          <option value="{{ $code }}" @selected(old('country', $data['country'] ?? 'GM') === $code)>{{ $label }}</option>
        @endforeach
      </select>
      <p class="mt-1 text-xs text-gray-500">Pick The Gambia and we'll set up your grades, subjects and the National Assessment Test for you. Other countries start with a clean slate.</p>
    </div>

    <div>
      <label for="address" class="block text-sm font-medium text-gray-700">Address <span class="font-normal text-gray-500">(optional)</span></label>
      <input id="address" name="address" type="text" value="{{ old('address', $data['address'] ?? '') }}"
        class="block w-full px-3 py-2 mt-1 text-sm border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
    </div>

    <div>
      <label for="logo" class="block text-sm font-medium text-gray-700">Logo <span class="font-normal text-gray-500">(optional)</span></label>
      <input id="logo" name="logo" type="file" accept="image/*"
        class="block w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
      @if (!empty($data['logo_path']))
        <p class="mt-1 text-xs text-emerald-600">A logo is already uploaded. Choose a new file to replace it.</p>
      @endif
      <p class="mt-1 text-xs text-gray-500">Shown on report headers. PNG or JPG, up to 4&nbsp;MB.</p>
    </div>

    <div class="flex justify-end pt-2">
      <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg bg-indigo-600 hover:bg-indigo-700">
        Continue &rarr;
      </button>
    </div>
  </form>

  <x-install.help>
    Your school's name appears at the top of every report. Choosing your country sets up the right grades and
    subjects automatically. For Gambian schools, pick <strong>The Gambia</strong>. The motto and logo are
    optional and only show on printed reports. Nothing here is permanent; you can change all of it later in Settings.
  </x-install.help>
@endsection
