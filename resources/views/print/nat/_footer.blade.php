{{-- expects: $kuboLogo (nullable) --}}
<footer>
    <table>
        <tr>
            <td style="text-align: left; width: 50%;">
                @if ($kuboLogo)
                    <img src="{{ $kuboLogo }}" height="13" alt="KUBO" style="vertical-align: middle;">
                @endif
                <span style="vertical-align: middle;">Generated with KUBO</span>
            </td>
            <td style="text-align: right; width: 50%;">
                <span class="pagenum"></span>
            </td>
        </tr>
    </table>
</footer>
