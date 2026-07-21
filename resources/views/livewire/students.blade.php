<div class="min-w-full mt-8 align-middle sm:px-4 lg:px-8">
  <div class="min-w-full mt-8 align-middle">
    <div class="flex items-center justify-between gap-4 mt-6 mx-4 sm:mx-0">
      <div class="relative mt-1 mb-3 rounded-md shadow-sm flex-1 sm:max-w-sm">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd"
              d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
              clip-rule="evenodd"></path>
          </svg>
        </div>
        <input id="search" wire:model.live="search"
          class="block w-full py-2 pl-10 pr-32 border border-gray-300 rounded-md sm:text-sm sm:leading-5"
          placeholder="Search student">
      </div>
      @hasanyrole('headmaster|admin')
      <a href="{{ route('students.create') }}"
         class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add student
      </a>
      <a href="{{ route('students.import') }}"
         class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-indigo-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"/>
        </svg>
        Import list
      </a>
      @endhasanyrole
    </div>
  </div>
  <div class="mx-4 overflow-hidden border-t border-b border-gray-200 shadow sm:mx-0 sm:rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
      <thead>
        <tr class="items-center ">
          <th
            class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 uppercase bg-gray-50">
            Name
          </th>
          <th
            class="w-24 px-4 py-3 text-xs font-medium leading-4 tracking-wider text-center text-gray-500 uppercase bg-gray-50">
            Gender
          </th>
          <th
            class="w-40 px-4 py-3 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 uppercase bg-gray-50">
            Class
          </th>
          <th
            class="hidden w-20 px-4 py-3 text-xs font-medium leading-4 tracking-wider text-left text-gray-500 uppercase sm:table-cell bg-gray-50">
            Age
          </th>
        </tr>
      </thead>
      <tbody>
        @foreach ($students as $student)
        @if ($loop->even)
        <tr class="bg-white cursor-pointer hover:bg-indigo-100" wire:click="show({{ $student->id }})">
          @else
        <tr class="items-center bg-gray-100 cursor-pointer hover:bg-indigo-100" wire:click="show({{ $student->id }})">
          @endif
          <td
            class="py-4 pl-6 text-sm font-medium leading-5 text-indigo-900 whitespace-nowrap">
            {{ucFirst($student->first_name).' '.ucFirst($student->last_name)}}
          </td>
          <td class="px-4 py-4 text-indigo-900">
            @if ($student->profile?->isFemale())
            <svg class="w-5 h-5 mx-auto text-indigo-800" fill="currentColor" stroke="currentColor"
              xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 24">
              <path
                d="M21 9c0-4.97-4.03-9-9-9s-9 4.03-9 9c0 4.632 3.501 8.443 8 8.941v2.059h-3v2h3v2h2v-2h3v-2h-3v-2.059c4.499-.498 8-4.309 8-8.941zm-16 0c0-3.86 3.14-7 7-7s7 3.14 7 7-3.14 7-7 7-7-3.14-7-7z" />
            </svg>
            @elseif($student->profile?->isMale())
            <svg class="w-5 h-5 mx-auto text-indigo-800" fill="currentColor" stroke="currentColor"
              xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 24">
              <path
                d="M16 2v2h3.586l-3.972 3.972c-1.54-1.231-3.489-1.972-5.614-1.972-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-2.125-.741-4.074-1.972-5.614l3.972-3.972v3.586h2v-7h-7zm-6 20c-3.86 0-7-3.14-7-7s3.14-7 7-7 7 3.14 7 7-3.14 7-7 7z" />
            </svg>
            @endif
          </td>
          <td class="px-4 py-4 whitespace-nowrap">
            <span class="inline-flex px-2 text-xs font-semibold leading-5 text-indigo-800 bg-indigo-200 rounded-full">
              {{ $student->grade_name }}
            </span>
          </td>
          <td class="hidden px-4 py-4 text-sm leading-5 text-gray-700 whitespace-nowrap sm:table-cell">
            {{ $student->getAge() }}
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="mx-4 my-8 sm:mx-0">
    {{ $students->links() }}
  </div>
</div>