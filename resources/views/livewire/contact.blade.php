<div class="flex overflow-hidden bg-white ">
    <!-- Content area -->
    <div class="flex flex-col flex-1">
        <main class="flex-1 overflow-y-auto focus:outline-none" tabindex="0">
            <div class="relative max-w-4xl mx-auto md:px-8 xl:px-0">
                @if($method !== 'newContact')
                <div class="pt-10 pb-16">
                    <div class="px-4 sm:px-6 md:px-0">
                        <h1 class="text-3xl font-extrabold leading-9 text-gray-900">
                            {{ $username }}</h1>
                    </div>
                    @endif
                    <div class="px-4 sm:px-6 md:px-0">
                        <div>
                            @if (session()->has('message'))
                            @include('components.notifications.message',[
                              'message' => session('message')
                              ])
                            @endif
                          </div>
                          <div class="divide-y divide-gray-200">
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                              <label for="fname" class="my-auto text-sm font-medium leading-5 text-gray-500">First name</label>
                              <div class="rounded-md">
                                <input required id="fname" value="{{ $contact->first_name }}" @if (!$allowEditing) disabled @endif
                                  wire:model.blur="contact.first_name"
                                  class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                              </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                              <label for="lname" class="my-auto text-sm font-medium leading-5 text-gray-500">Last name</label>
                              <div class="rounded-md">
                                <input required id="lname" value="{{ $contact->last_name }}" @if (!$allowEditing) disabled @endif
                                  wire:model.blur="contact.last_name"
                                  class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                              </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                              <label for="gender" class="my-auto text-sm font-medium leading-5 text-gray-500">Gender</label>
                              <div class="rounded-md">
                                <select id="gender" wire:model.blur="contact.gender"
                                  class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                  <option @if ($contact->gender === "F") selected @endif value="F">Female</option>
                                  <option @if ($contact->gender === "M") selected @endif value="M">Male</option>
                                  <option value="Other">Other</option>
                                </select>
                              </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
                              <label for="tribe" class="my-auto text-sm font-medium leading-5 text-gray-500">Tribe</label>
                              <div class="rounded-md">
                                <input id="tribe" value="{{ $contact->tribe }}" @if (!$allowEditing) disabled @endif wire:model.blur="contact.tribe"
                                  class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                              </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
                              <label for="phone" class="my-auto text-sm font-medium leading-5 text-gray-500">Primary phone</label>
                              <div class="rounded-md">
                                <input id="phone" value="{{ $contact->primary_phone }}" @if (!$allowEditing) disabled @endif
                                  wire:model.blur="contact.primary_phone"
                                  class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                              </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
                              <label for="address" class="my-auto text-sm font-medium leading-5 text-gray-500">Address</label>
                              <div class="rounded-md">
                                <textarea id="address" @if (!$allowEditing) disabled @endif wire:model.blur="contact.address"
                                  class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">{{ $contact->address }}</textarea>
                              </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
                              <label for="comment" class="my-auto text-sm font-medium leading-5 text-gray-500">Comment</label>
                              <div class="rounded-md">
                                <textarea id="comment" @if (!$allowEditing) disabled @endif wire:model.blur="contact.comment"
                                  class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">{{ $contact->comment }}</textarea>
                              </div>
                            </div> 
                            <div class="col-span-4 mb-4 text-right bg-gray-50">
                                @if($method === 'newContact')
                              <button wire:click="addContact"
                                class="inline-flex justify-center px-4 py-2 mt-4 mr-4 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
                                Add contact
                              </button>
                              @else
                              <button wire:click="deleteContact"
                                class="inline-flex justify-center px-4 py-2 mt-4 mr-4 text-sm font-medium text-white bg-red-700 border border-transparent rounded-md shadow-sm hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
                                Delete contact
                              </button>
                              <button wire:click="saveContact"
                                class="inline-flex justify-center px-4 py-2 mt-4 mr-4 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
                                Save contact
                              </button>
                              @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>