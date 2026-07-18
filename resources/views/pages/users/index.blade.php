<x-page title="Users" :wrap="true">

    <div class="flex items-center justify-between mb-6">
      <p class="text-sm text-gray-500">Manage teachers, staff, and administrators.</p>
      <div class="flex items-center gap-4">
        @if ($archivedCount > 0 || $showArchived)
        <a href="{{ route('users.index', $showArchived ? [] : ['show_archived' => 1]) }}"
          class="text-sm text-gray-500 hover:text-gray-700 underline">
          {{ $showArchived ? 'Hide archived' : 'Show archived (' . $archivedCount . ')' }}
        </a>
        @endif
        <a href="{{ route('users.staff-list') }}" target="_blank" rel="noopener"
          class="inline-flex items-center px-4 py-2 text-sm font-semibold text-indigo-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
          Staff list PDF
        </a>
        <a href="{{ route('users.create') }}"
          class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-gray-800 rounded-md shadow-sm hover:bg-gray-900">
          Add user
        </a>
      </div>
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded-lg shadow">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Roles</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Health access</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @foreach($users as $user)
          <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }} {{ $user->archived ? 'opacity-50' : '' }}">
            <td class="px-4 py-3 text-sm font-medium text-gray-900">
              {{ $user->first_name }} {{ $user->last_name }}
              @if($user->archived)
              <span class="text-xs text-gray-500 ml-1">(archived)</span>
              @endif
            </td>
            <td class="px-4 py-3">
              @php
                $roleLabels = [
                  'headmaster' => 'Headmaster',
                  'admin' => 'Administration',
                  'system_admin' => 'System admin',
                  'teacher' => 'Teacher',
                  'caregiver' => 'Caregiver',
                  'assistant_coordinator' => 'Assistant coordinator',
                  'student' => 'Student',
                ];
                $roleClasses = [
                  'headmaster' => 'bg-indigo-100 text-indigo-800',
                  'admin' => 'bg-indigo-100 text-indigo-800',
                  'system_admin' => 'bg-amber-100 text-amber-800',
                  'teacher' => 'bg-green-100 text-green-800',
                ];
              @endphp
              <div class="flex flex-wrap gap-1">
                @foreach($user->getRoleNames() as $role)
                <span class="px-2 py-0.5 text-xs rounded-full {{ $roleClasses[$role] ?? 'bg-gray-100 text-gray-600' }}">
                  {{ $roleLabels[$role] ?? ucfirst(str_replace('_', ' ', $role)) }}
                </span>
                @endforeach
              </div>
            </td>
            <td class="px-4 py-3 text-sm">
              @php
                $hasHealthExtra = $user->hasDirectPermission('view medical records');
                // Has it via any role's grant, not a per-user override
                $hasHealthByRole = $user->hasPermissionTo('view medical records') && !$hasHealthExtra;
              @endphp
              @if($hasHealthByRole)
                <span class="text-xs text-gray-500" title="Granted via role">via role</span>
              @elseif(!$user->hasRole('student'))
                <form method="POST" action="{{ route('users.toggle-health-access', $user) }}" class="inline">
                  @csrf
                  @if($hasHealthExtra)
                    <button type="submit" class="text-xs text-green-700 hover:underline">Granted (revoke)</button>
                  @else
                    <button type="submit" class="text-xs text-gray-500 hover:text-gray-700 hover:underline">Grant</button>
                  @endif
                </form>
              @else
                <span class="text-xs text-gray-500">—</span>
              @endif
            </td>
            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
              @if(!$user->hasRole('student'))
              <div class="inline-flex items-center justify-end gap-x-4">
                <a href="{{ route('users.edit', $user) }}" class="text-gray-500 hover:text-gray-700">Edit</a>
                <form method="POST" action="{{ route('users.reset-password', $user) }}"
                      onsubmit="return confirm('Reset password for {{ $user->first_name }}?')">
                  @csrf
                  <button type="submit" class="text-gray-500 hover:text-gray-700">Reset password</button>
                </form>
                <form method="POST" action="{{ route('users.toggle-archive', $user) }}"
                      onsubmit="return confirm('{{ $user->archived ? 'Restore' : 'Archive' }} {{ $user->first_name }} {{ $user->last_name }}?')">
                  @csrf
                  @if($user->archived)
                    <button type="submit" class="text-green-600 hover:text-green-800">Restore</button>
                  @else
                    <button type="submit" class="text-gray-500 hover:text-gray-700">Archive</button>
                  @endif
                </form>
                <form method="POST" action="{{ route('users.destroy', $user) }}"
                      onsubmit="return confirm('Delete {{ $user->first_name }} {{ $user->last_name }}?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="text-red-600 hover:text-red-600">Delete</button>
                </form>
              </div>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
</x-page>
