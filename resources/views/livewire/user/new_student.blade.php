<div>
  @if (session()->has('message'))
  <div class="alert alert-success">
    {{ session('message') }}
  </div>
  @endif
</div>
@if ($errors->any())
  <div class="mx-4 mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3">
    <ul class="list-disc pl-5">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif
<form class="px-4 divide-y divide-gray-200 sm:px-6 md:px-0" wire:submit.prevent="enrollStudent">
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
    <label for="location" class="my-auto text-sm font-medium leading-5 text-gray-500">Offering</label>
    <div class="rounded-md">
      <select required id="class" name="class" wire:model="selectedOffering"
        class="block w-full px-3 py-2 mt-1 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        @foreach ($offerings as $offering)
        <option value={{ $offering->id }}>{{ $offering->grade->name.' '.$offering->name }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
    <label for="fname" class="my-auto text-sm font-medium leading-5 text-gray-500">First name</label>
    <div class="rounded-md">
      <input id="fname" value="{{ $user->first_name }}" @if (!$allowEditing) disabled @endif
        wire:model.blur="firstName"
        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
    </div>
  </div>
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
    <label for="lname" class="my-auto text-sm font-medium leading-5 text-gray-500">Last name</label>
    <div class="rounded-md">
      <input id="lname" value="{{ $user->last_name }}" @if (!$allowEditing) disabled @endif
        wire:model.blur="lastName"
        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
    </div>
  </div>
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
    <label for="gender" class="my-auto text-sm font-medium leading-5 text-gray-500">Gender</label>
    <div class="rounded-md">
      <select id="gender" wire:model.blur="gender"
        class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
        <option value="F">Female</option>
        <option value="M">Male</option>
        <option value="Other">Other</option>
      </select>
    </div>
  </div>
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
    <label for="birthday" class="my-auto text-sm font-medium leading-5 text-gray-500">Date of birth</label>
    <div class="rounded-md">
      <input placeholder="2000-31-01" id="birthday" value="{{ $profile->birth_date }}" @if (!$allowEditing) disabled
        @endif wire:model.blur="birthDate"
        type='date'
        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
    </div>
  </div>
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
    <label for="tribe" class="my-auto text-sm font-medium leading-5 text-gray-500">Tribe</label>
    <div class="rounded-md">
      <input id="tribe" value="{{ $profile->tribe }}" @if (!$allowEditing) disabled @endif wire:model.blur="tribe"
        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
    </div>
  </div>
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
    <label for="phone" class="my-auto text-sm font-medium leading-5 text-gray-500">Primary phone</label>
    <div class="rounded-md">
      <input id="phone" value="{{ $profile->primary_phone }}" @if (!$allowEditing) disabled @endif
        wire:model.blur="primaryPhone"
        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
    </div>
  </div>
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
    <label for="address" class="my-auto text-sm font-medium leading-5 text-gray-500">Address</label>
    <div class="rounded-md">
      <textarea id="address" @if (!$allowEditing) disabled @endif wire:model.blur="address"
        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">{{ $profile->address }}</textarea>
    </div>
  </div>
  <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
    <label for="comment" class="my-auto text-sm font-medium leading-5 text-gray-500">Comment</label>
    <div class="rounded-md">
      <textarea id="comment" @if (!$allowEditing) disabled @endif wire:model.blur="comment"
        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">{{ $profile->comment }}</textarea>
    </div>
  </div>
  <div class="col-span-4 mt-4 text-right bg-gray-50">
    <button type="submit"
      class="inline-flex justify-center px-4 py-2 mr-4 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
      Enroll new student
    </button>
  </div>
</form>