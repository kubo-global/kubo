<x-page title="Backups">
  <div class="w-full px-4 py-8 sm:w-3/4 sm:px-0 mx-auto">

    {{-- Warning banner --}}
    @if($isOverdue)
    <div class="p-4 mb-6 rounded-lg border-2 {{ $lastBackup ? 'bg-amber-50 border-amber-300' : 'bg-red-50 border-red-300' }}">
      <div class="flex items-start gap-3">
        <svg class="w-6 h-6 {{ $lastBackup ? 'text-amber-500' : 'text-red-500' }} shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
        </svg>
        <div>
          @if($lastBackup)
          <p class="font-semibold text-amber-800">Backup overdue</p>
          <p class="text-sm text-amber-700 mt-1">
            Last backup was {{ $lastBackup->created_at->diffForHumans() }} ({{ $lastBackup->created_at->format('d M Y') }}).
            Student data, scores, and reports could be lost if the server fails.
          </p>
          @else
          <p class="font-semibold text-red-800">No backups found</p>
          <p class="text-sm text-red-700 mt-1">
            This system has never been backed up. All student data, scores, and reports are at risk.
            Download a backup now or ask your IT administrator to set up automated backups.
          </p>
          @endif
        </div>
      </div>
    </div>
    @else
    <div class="p-4 mb-6 bg-green-50 border border-green-200 rounded-lg">
      <div class="flex items-center gap-3">
        <svg class="w-6 h-6 text-green-500 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
        <div>
          <p class="font-semibold text-green-800">Backups up to date</p>
          <p class="text-sm text-green-700">Last backup: {{ $lastBackup->created_at->diffForHumans() }} ({{ $lastBackup->humanSize() }})</p>
        </div>
      </div>
    </div>
    @endif

    {{-- Actions --}}
    <div class="flex items-center justify-between mb-8">
      <h2 class="text-lg font-medium text-gray-900">Backup management</h2>
      <a href="{{ route('backup.download') }}"
        class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-gray-800 rounded-md shadow-sm hover:bg-gray-900">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Download backup now
      </a>
    </div>

    {{-- Integration info for DevOps --}}
    <details class="mb-8 border border-gray-200 rounded-lg">
      <summary class="px-4 py-3 text-sm font-medium text-gray-500 cursor-pointer select-none hover:text-gray-700">
        For IT administrators: automated backup integration
      </summary>
      <div class="px-4 pb-4 border-t border-gray-200 pt-4 text-sm text-gray-600 space-y-3">
        <p>External backup scripts can report their status to KUBO so the dashboard stays informed.</p>
        <p><strong>Report a successful backup:</strong></p>
        <pre class="bg-gray-100 p-3 rounded text-xs overflow-x-auto">curl -X POST {{ url('/api/backup/report') }} \
  -H "Content-Type: application/json" \
  -d '{"method":"scheduled","destination":"usb","status":"success","size_bytes":12345}'</pre>
        <p><strong>Report a failed backup:</strong></p>
        <pre class="bg-gray-100 p-3 rounded text-xs overflow-x-auto">curl -X POST {{ url('/api/backup/report') }} \
  -H "Content-Type: application/json" \
  -d '{"method":"scheduled","destination":"usb","status":"failed","error":"Disk full"}'</pre>
        <p>Add this call at the end of your backup cron script. KUBO will show warnings if no successful backup is reported within 7 days.</p>
      </div>
    </details>

    {{-- Backup history --}}
    @if($recentLogs->isNotEmpty())
    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3">Recent backups</h3>
    <div class="overflow-x-auto border border-gray-200 rounded-lg shadow">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Size</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @foreach($recentLogs as $log)
          <tr class="{{ $log->status === 'failed' ? 'bg-red-50' : '' }}">
            <td class="px-4 py-3 text-sm text-gray-900">
              {{ $log->created_at->format('d M Y H:i') }}
              <span class="text-xs text-gray-500 block">{{ $log->created_at->diffForHumans() }}</span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($log->method) }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($log->destination) }}</td>
            <td class="px-4 py-3 text-center">
              @if($log->status === 'success')
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">OK</span>
              @else
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800" title="{{ $log->error }}">Failed</span>
              @endif
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ $log->humanSize() }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <div class="p-8 text-center text-gray-500 border border-gray-200 rounded-md">
      No backup history yet.
    </div>
    @endif

  </div>
</x-page>
