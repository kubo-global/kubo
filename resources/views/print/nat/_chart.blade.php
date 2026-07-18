@php
    // $b = ['subject' => Subject, 'groups' => Collection<label => stats>]
    // dompdf renders SVG reliably only when embedded as a data: image, not inline.
    $labels = ['Male', 'Female', 'All'];
    // charts stay in colour (small area, cheap to print): grey/light/dark blue
    $series = [
        ['key' => 'pct_fail', 'color' => '#A6A6A6', 'name' => '% fail'],
        ['key' => 'pct_pass', 'color' => '#96a5cd', 'name' => '% pass'],
        ['key' => 'pct_mastery', 'color' => '#3d5494', 'name' => '% mastery'],
    ];

    // headroom above 100% (axis to 120%) like the 2023 reference, kept short
    // enough that two subjects fit on one page
    $axisMax = 120;
    $W = 760; $H = 192;
    $left = 50; $right = 752; $top = 12; $base = 156;
    $plotH = $base - $top;
    $groupW = ($right - $left) / count($labels);
    $barW = 32; $innerGap = 7;

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$W.'" height="'.$H.'" '
         . 'viewBox="0 0 '.$W.' '.$H.'" font-family="sans-serif" font-size="10">';

    // horizontal help lines + y-axis labels (0..120%)
    for ($p = 0; $p <= $axisMax; $p += 20) {
        $y = $base - ($p / $axisMax) * $plotH;
        $svg .= '<line x1="'.$left.'" y1="'.$y.'" x2="'.$right.'" y2="'.$y.'" stroke="#D9D9D9" stroke-width="1"/>';
        $svg .= '<text x="'.($left - 7).'" y="'.($y + 3).'" text-anchor="end" fill="#666">'.$p.'%</text>';
    }

    // grouped bars per category
    foreach ($labels as $gi => $label) {
        $stats = $b['groups'][$label];
        $slotX = $left + $gi * $groupW;
        $blockW = count($series) * $barW + (count($series) - 1) * $innerGap;
        $startX = $slotX + ($groupW - $blockW) / 2;
        foreach ($series as $si => $s) {
            $h = ($stats[$s['key']] * 100 / $axisMax) * $plotH;
            if ($h > 0.5) {
                $x = $startX + $si * ($barW + $innerGap);
                $svg .= '<rect x="'.$x.'" y="'.($base - $h).'" width="'.$barW.'" height="'.$h.'" fill="'.$s['color'].'"/>';
            }
        }
        $svg .= '<text x="'.($slotX + $groupW / 2).'" y="'.($base + 15).'" text-anchor="middle" fill="#333">'.$label.'</text>';
    }

    $svg .= '<line x1="'.$left.'" y1="'.$base.'" x2="'.$right.'" y2="'.$base.'" stroke="#999" stroke-width="1"/>';

    // legend, centred under the plot
    $legendW = count($series) * 110;
    $lx = $left + (($right - $left) - $legendW) / 2;
    foreach ($series as $s) {
        $svg .= '<rect x="'.$lx.'" y="'.($H - 13).'" width="10" height="10" fill="'.$s['color'].'"/>';
        $svg .= '<text x="'.($lx + 14).'" y="'.($H - 5).'" fill="#333">'.$s['name'].'</text>';
        $lx += 110;
    }

    $svg .= '</svg>';
    $dataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);
@endphp
<img src="{{ $dataUri }}" style="width: 100%; height: auto;" alt="{{ $b['subject']->name }} chart"/>
