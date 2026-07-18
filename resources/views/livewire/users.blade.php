<div class="min-w-full mt-8 align-middle sm:px-4 lg:px-8">
  <div class="relative mx-4 mt-1 mb-3 rounded-md shadow-sm sm:mx-0 sm:max-w-sm">
    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
        <path fill-rule="evenodd"
          d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
          clip-rule="evenodd"></path>
      </svg>
    </div>
    <input id="search" wire:model.live="search"
      class="block w-full py-2 pl-10 pr-32 border border-gray-300 rounded-md sm:text-sm sm:leading-5"
      placeholder="Search staff">
  </div>
  <div class="mx-4 overflow-hidden border-t border-b border-gray-200 shadow sm:mx-0 sm:rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
      <thead>
        <tr class="items-center ">
          <th
            class="w-6 px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 uppercase bg-gray-50">
            Name
          </th>
          <th
            class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 uppercase bg-gray-50">
            Roles
          </th>
        </tr>
      </thead>
      <tbody>
        @foreach ($users as $user)
        @if ($loop->even)
        <tr class="bg-white hover:bg-indigo-100">
          @else
        <tr class="items-center bg-gray-100 hover:bg-indigo-100">
          @endif
          <td
            class="py-4 pl-6 text-sm font-medium leading-5 text-indigo-900 whitespace-no-wrap cursor-pointer hover:underline"
            wire:click="show({{ $user->id }})">
            {{ucFirst($user->first_name).' '.ucFirst($user->last_name)}}
          </td>
          <td class="px-6 py-4 text-left whitespace-no-wrap">
            @foreach ($user->getRoleNames() as $role)
            <span class="inline-flex px-2 mr-2 text-xs font-semibold leading-5 text-indigo-800 bg-indigo-200 rounded-full">
              {{ $role }}
            </span>
            @endforeach
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="mx-4 my-8 sm:mx-0">
    {{ $users->links() }}
  </div>
</div>