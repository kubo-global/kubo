{{-- Index cell: sign-off status + remark. Expects $signedAt, $remark, $canSign, $level, $plan. --}}
@if ($signedAt)
    <span class="inline-flex items-center gap-1 font-medium text-gray-900 whitespace-nowrap" title="Signed off {{ $signedAt->format('d M Y') }}">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
        Signed off
    </span>
@elseif ($canSign)
    <form method="POST" action="{{ route('lesson-plans.sign-off', $plan) }}">
        @csrf
        <input type="hidden" name="level" value="{{ $level }}">
        <input type="hidden" name="signed" value="1">
        <button type="submit" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-md ring-1 ring-gray-200 hover:bg-gray-200 whitespace-nowrap">Sign off</button>
    </form>
@else
    <span class="text-gray-400">Not signed</span>
@endif

@if ($remark)
    <span class="block max-w-[14rem] mt-1 text-xs text-gray-500 truncate" title="{{ $remark }}">{{ $remark }}</span>
@endif
