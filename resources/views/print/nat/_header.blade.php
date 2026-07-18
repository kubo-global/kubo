{{-- expects: $logo (school, nullable), $org, $title2, $subtitle --}}
{{-- position: fixed -> repeats on every page (context when pages are hung separately) --}}
<div class="page-header">
    <table class="report-head">
        <tr>
            <td style="width: 56px;">
                @if ($logo)
                    <img src="{{ $logo }}" width="50" alt="School logo">
                @endif
            </td>
            <td style="text-align: center;">
                <div class="org">{{ $org }}</div>
                <div class="sub">{{ $title2 }} {{ $subtitle }}</div>
            </td>
            <td style="width: 56px;"></td>
        </tr>
    </table>
    <div class="rule">&nbsp;</div>
</div>
