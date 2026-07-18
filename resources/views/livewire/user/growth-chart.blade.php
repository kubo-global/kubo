{{--
  WHO Child Growth Curves
  Data source: WHO Growth Reference 2007 (5-19 years), expanded z-score tables
  https://www.who.int/tools/growth-reference-data-for-5to19-years

  Expects: $user (with ->profile->birth_date, ->profile->gender, ->healthReports)
--}}
@php
  $profile = $user->profile;
  $birthDate = $profile?->birth_date;
  $sex = $profile?->isFemale() ? 'girls' : 'boys';

  $measurements = $user->healthReports
    ->filter(fn ($r) => $r->height_in_cm && $birthDate)
    ->map(function ($r) use ($birthDate) {
      $ageMonths = \Carbon\Carbon::parse($birthDate)->diffInMonths($r->created_at);
      $weightKg = $r->weight_kg;
      return [
        'age' => round($ageMonths, 1),
        'height' => $r->height_in_cm,
        'weight' => $weightKg ? round($weightKg, 1) : null,
        'bmi' => ($r->height_in_cm && $weightKg)
          ? round($weightKg / (($r->height_in_cm / 100) ** 2), 1)
          : null,
        'date' => $r->created_at->format('d M Y'),
      ];
    })
    ->sortBy('age')
    ->values();
@endphp

@php $hasData = $measurements->isNotEmpty(); @endphp
{{-- With no measurements the curves are still drawn, just dimmed: an empty frame
     that shows what a checkup would fill in beats three blank boxes. --}}
@if($birthDate)
<div class="mt-8"
  x-data
  x-init="
    $nextTick(() => {
      var sex = @js($sex);
      var measurements = @js($measurements);
      // With no measurements there is nothing to window the x-axis around, and the
      // full 5-19 range crams the tick labels together. Anchor it on the pupil's
      // own age instead, so the empty chart shows the years that matter to them.
      var anchorAge = @js($hasData ? null : (int) \Carbon\Carbon::parse($birthDate)->diffInMonths(now()));

      function loadD3(cb) {
        if (typeof d3 !== 'undefined') { cb(); return; }
        var s = document.createElement('script');
        s.src = '/vendor/d3.v7.min.js';
        s.onload = cb;
        document.head.appendChild(s);
      }

      loadD3(() => {
        // -------------------------------------------------------------------
        // WHO Growth Reference 2007 — official z-score expanded tables
        // Downloaded from: who.int/tools/growth-reference-data-for-5to19-years
        // Format: [month, SD-2, SD-1, median, SD+1, SD+2]
        // Sampled every 3 months for smooth curves
        // -------------------------------------------------------------------
        var WHO = {
          height: {
            boys: [[61,101.1,105.7,110.3,114.9,119.4],[63,102.0,106.7,111.3,116.0,120.6],[66,103.4,108.2,112.9,117.7,122.4],[69,104.8,109.6,114.5,119.3,124.1],[72,106.1,111.0,116.0,120.9,125.8],[75,107.4,112.4,117.4,122.4,127.5],[78,108.7,113.8,118.9,124.0,129.1],[81,109.9,115.1,120.3,125.5,130.7],[84,111.2,116.4,121.7,127.0,132.3],[87,112.4,117.8,123.1,128.5,133.9],[90,113.6,119.1,124.5,130.0,135.5],[93,114.8,120.4,125.9,131.5,137.0],[96,116.0,121.6,127.3,132.9,138.6],[99,117.1,122.9,128.6,134.3,140.1],[102,118.3,124.1,129.9,135.8,141.6],[105,119.4,125.3,131.2,137.2,143.1],[108,120.5,126.6,132.6,138.6,144.6],[111,121.7,127.8,133.9,140.0,146.1],[114,122.8,129.0,135.2,141.4,147.6],[117,123.9,130.2,136.5,142.8,149.1],[120,125.0,131.4,137.8,144.2,150.5],[123,126.2,132.6,139.1,145.5,152.0],[126,127.3,133.8,140.4,146.9,153.5],[129,128.5,135.1,141.7,148.4,155.0],[132,129.7,136.4,143.1,149.8,156.6],[135,130.9,137.7,144.5,151.3,158.2],[138,132.2,139.1,146.0,152.9,159.8],[141,133.5,140.5,147.5,154.5,161.5],[144,134.9,142.0,149.1,156.2,163.3],[147,136.4,143.6,150.7,157.9,165.1],[150,137.9,145.2,152.4,159.7,167.0],[153,139.5,146.9,154.2,161.6,168.9],[156,141.2,148.6,156.0,163.5,170.9],[159,142.9,150.4,157.9,165.4,172.9],[162,144.5,152.1,159.7,167.3,174.8],[165,146.2,153.8,161.5,169.1,176.7],[168,147.8,155.5,163.2,170.9,178.6],[171,149.3,157.1,164.8,172.5,180.3],[174,150.8,158.5,166.3,174.1,181.8],[177,152.1,159.9,167.7,175.5,183.3],[180,153.3,161.2,169.0,176.8,184.6],[183,154.5,162.3,170.1,177.9,185.7],[186,155.5,163.3,171.1,178.9,186.8],[189,156.5,164.3,172.1,179.9,187.7],[192,157.4,165.1,172.9,180.7,188.4],[195,158.1,165.9,173.6,181.4,189.1],[198,158.8,166.5,174.2,181.9,189.7],[201,159.4,167.1,174.7,182.4,190.1],[204,159.9,167.5,175.2,182.8,190.4],[207,160.3,167.9,175.5,183.1,190.7],[210,160.6,168.2,175.8,183.3,190.9],[213,160.9,168.5,176.0,183.5,191.0],[216,161.2,168.7,176.1,183.6,191.1],[219,161.4,168.9,176.3,183.7,191.1],[222,161.6,169.0,176.4,183.8,191.1],[225,161.8,169.1,176.5,183.8,191.2],[228,161.9,169.2,176.5,183.8,191.1]],
            girls: [[61,100.1,104.8,109.6,114.4,119.1],[63,101.0,105.8,110.6,115.5,120.3],[66,102.3,107.2,112.2,117.1,122.0],[69,103.6,108.6,113.7,118.7,123.7],[72,104.9,110.0,115.1,120.2,125.4],[75,106.1,111.3,116.6,121.8,127.0],[78,107.4,112.7,118.0,123.3,128.6],[81,108.6,114.0,119.4,124.8,130.2],[84,109.9,115.3,120.8,126.3,131.7],[87,111.1,116.7,122.2,127.8,133.3],[90,112.4,118.0,123.7,129.3,134.9],[93,113.7,119.4,125.1,130.8,136.5],[96,115.0,120.8,126.6,132.4,138.2],[99,116.3,122.1,128.0,133.9,139.8],[102,117.6,123.5,129.5,135.5,141.4],[105,118.9,125.0,131.0,137.0,143.1],[108,120.3,126.4,132.5,138.6,144.7],[111,121.6,127.8,134.0,140.2,146.4],[114,123.0,129.3,135.5,141.8,148.1],[117,124.4,130.8,137.1,143.4,149.7],[120,125.8,132.2,138.6,145.0,151.4],[123,127.3,133.7,140.2,146.7,153.1],[126,128.7,135.3,141.8,148.3,154.8],[129,130.2,136.8,143.4,150.0,156.6],[132,131.7,138.3,145.0,151.6,158.3],[135,133.2,139.9,146.6,153.3,160.0],[138,134.7,141.4,148.2,154.9,161.7],[141,136.1,142.9,149.7,156.5,163.3],[144,137.6,144.4,151.2,158.1,164.9],[147,138.9,145.8,152.7,159.5,166.4],[150,140.2,147.1,154.0,160.9,167.8],[153,141.4,148.3,155.2,162.2,169.1],[156,142.5,149.4,156.4,163.3,170.3],[159,143.5,150.4,157.4,164.3,171.3],[162,144.4,151.3,158.3,165.3,172.2],[165,145.2,152.1,159.1,166.0,173.0],[168,145.9,152.8,159.8,166.7,173.7],[171,146.5,153.5,160.4,167.3,174.2],[174,147.1,154.0,160.9,167.8,174.7],[177,147.5,154.4,161.3,168.2,175.1],[180,147.9,154.8,161.7,168.5,175.4],[183,148.2,155.1,162.0,168.8,175.7],[186,148.5,155.4,162.2,169.0,175.9],[189,148.7,155.6,162.4,169.2,176.0],[192,148.9,155.7,162.5,169.3,176.1],[195,149.1,155.9,162.6,169.4,176.2],[198,149.2,156.0,162.7,169.5,176.2],[201,149.4,156.1,162.8,169.5,176.2],[204,149.5,156.2,162.9,169.5,176.2],[207,149.6,156.2,162.9,169.6,176.3],[210,149.7,156.3,163.0,169.6,176.3],[213,149.8,156.4,163.0,169.6,176.3],[216,149.8,156.5,163.1,169.7,176.3],[219,149.9,156.5,163.1,169.7,176.3],[222,150.0,156.6,163.1,169.7,176.3],[225,150.0,156.6,163.1,169.7,176.3],[228,150.1,156.6,163.2,169.7,176.2]]
          },
          weight: {
            boys: [[61,14.4,16.3,18.5,21.1,24.2],[63,14.6,16.6,18.9,21.5,24.7],[66,15.0,17.0,19.4,22.2,25.5],[69,15.4,17.5,19.9,22.8,26.3],[72,15.9,18.0,20.5,23.5,27.1],[75,16.3,18.5,21.1,24.2,28.0],[78,16.8,19.0,21.7,24.9,28.9],[81,17.2,19.5,22.3,25.6,29.8],[84,17.7,20.0,22.9,26.4,30.7],[87,18.1,20.6,23.5,27.1,31.7],[90,18.6,21.1,24.1,27.9,32.6],[93,19.0,21.6,24.8,28.7,33.7],[96,19.5,22.1,25.4,29.5,34.7],[99,19.9,22.7,26.1,30.3,35.8],[102,20.4,23.2,26.7,31.2,37.0],[105,20.8,23.8,27.4,32.1,38.2],[108,21.3,24.3,28.1,33.0,39.4],[111,21.7,24.9,28.8,33.9,40.7],[114,22.2,25.5,29.6,34.9,42.1],[117,22.7,26.1,30.4,36.0,43.5],[120,23.2,26.7,31.2,37.0,45.0]],
            girls: [[61,14.0,15.9,18.3,21.2,24.8],[63,14.2,16.2,18.6,21.6,25.4],[66,14.6,16.6,19.1,22.2,26.2],[69,14.9,17.0,19.6,22.9,27.0],[72,15.3,17.5,20.2,23.5,27.8],[75,15.6,17.9,20.7,24.2,28.7],[78,16.0,18.3,21.2,24.9,29.6],[81,16.4,18.8,21.8,25.6,30.5],[84,16.8,19.3,22.4,26.3,31.4],[87,17.2,19.8,23.0,27.1,32.5],[90,17.6,20.3,23.6,27.9,33.5],[93,18.1,20.9,24.3,28.8,34.6],[96,18.6,21.4,25.0,29.7,35.8],[99,19.1,22.0,25.8,30.6,37.0],[102,19.6,22.7,26.6,31.6,38.3],[105,20.2,23.3,27.4,32.6,39.6],[108,20.8,24.0,28.2,33.6,41.0],[111,21.4,24.7,29.1,34.7,42.4],[114,22.0,25.5,30.0,35.9,43.8],[117,22.6,26.2,30.9,37.0,45.3],[120,23.3,27.0,31.9,38.2,46.9]]
          },
          bmi: {
            boys: [[61,13.0,14.1,15.3,16.6,18.3],[63,13.0,14.1,15.3,16.7,18.3],[66,13.0,14.1,15.3,16.7,18.4],[69,13.0,14.1,15.3,16.7,18.4],[72,13.0,14.1,15.3,16.8,18.5],[75,13.1,14.1,15.3,16.8,18.6],[78,13.1,14.1,15.4,16.9,18.7],[81,13.1,14.2,15.4,17.0,18.9],[84,13.1,14.2,15.5,17.0,19.0],[87,13.2,14.2,15.5,17.1,19.2],[90,13.2,14.3,15.6,17.2,19.3],[93,13.3,14.3,15.7,17.3,19.5],[96,13.3,14.4,15.7,17.4,19.7],[99,13.3,14.4,15.8,17.5,19.9],[102,13.4,14.5,15.9,17.7,20.1],[105,13.4,14.6,16.0,17.8,20.3],[108,13.5,14.6,16.0,17.9,20.5],[111,13.5,14.7,16.1,18.0,20.7],[114,13.6,14.8,16.2,18.2,20.9],[117,13.7,14.8,16.3,18.3,21.2],[120,13.7,14.9,16.4,18.5,21.4],[123,13.8,15.0,16.6,18.6,21.7],[126,13.9,15.1,16.7,18.8,21.9],[129,14.0,15.2,16.8,19.0,22.2],[132,14.1,15.3,16.9,19.2,22.5],[135,14.1,15.4,17.1,19.3,22.7],[138,14.2,15.5,17.2,19.5,23.0],[141,14.3,15.7,17.4,19.7,23.3],[144,14.5,15.8,17.5,19.9,23.6],[147,14.6,15.9,17.7,20.2,23.9],[150,14.7,16.1,17.9,20.4,24.2],[153,14.8,16.2,18.0,20.6,24.5],[156,14.9,16.4,18.2,20.8,24.8],[159,15.1,16.5,18.4,21.1,25.1],[162,15.2,16.7,18.6,21.3,25.3],[165,15.3,16.8,18.8,21.5,25.6],[168,15.5,17.0,19.0,21.8,25.9],[171,15.6,17.2,19.2,22.0,26.2],[174,15.7,17.3,19.4,22.2,26.5],[177,15.9,17.5,19.6,22.5,26.7],[180,16.0,17.6,19.8,22.7,27.0],[183,16.1,17.8,20.0,22.9,27.2],[186,16.3,18.0,20.1,23.1,27.4],[189,16.4,18.1,20.3,23.3,27.7],[192,16.5,18.2,20.5,23.5,27.9],[195,16.6,18.4,20.7,23.7,28.1],[198,16.7,18.5,20.8,23.9,28.3],[201,16.8,18.7,21.0,24.1,28.5],[204,16.9,18.8,21.1,24.3,28.6],[207,17.0,18.9,21.3,24.4,28.8],[210,17.1,19.0,21.4,24.6,29.0],[213,17.2,19.1,21.6,24.8,29.1],[216,17.3,19.2,21.7,24.9,29.2],[219,17.4,19.3,21.8,25.1,29.4],[222,17.4,19.4,22.0,25.2,29.5],[225,17.5,19.5,22.1,25.3,29.6],[228,17.6,19.6,22.2,25.4,29.7]],
            girls: [[61,12.7,13.9,15.2,16.9,18.9],[63,12.7,13.9,15.2,16.9,18.9],[66,12.7,13.9,15.2,16.9,19.0],[69,12.7,13.9,15.3,17.0,19.1],[72,12.7,13.9,15.3,17.0,19.2],[75,12.7,13.9,15.3,17.1,19.3],[78,12.7,13.9,15.3,17.1,19.5],[81,12.7,13.9,15.4,17.2,19.6],[84,12.7,13.9,15.4,17.3,19.8],[87,12.8,14.0,15.5,17.4,20.0],[90,12.8,14.0,15.5,17.5,20.1],[93,12.8,14.1,15.6,17.6,20.3],[96,12.9,14.1,15.7,17.7,20.6],[99,12.9,14.2,15.8,17.9,20.8],[102,13.0,14.3,15.9,18.0,21.0],[105,13.1,14.3,16.0,18.2,21.3],[108,13.1,14.4,16.1,18.3,21.5],[111,13.2,14.5,16.2,18.5,21.8],[114,13.3,14.6,16.3,18.7,22.0],[117,13.4,14.7,16.5,18.8,22.3],[120,13.5,14.8,16.6,19.0,22.6],[123,13.6,15.0,16.8,19.2,22.8],[126,13.7,15.1,16.9,19.4,23.1],[129,13.8,15.2,17.1,19.6,23.4],[132,13.9,15.3,17.2,19.9,23.7],[135,14.0,15.5,17.4,20.1,24.0],[138,14.1,15.6,17.6,20.3,24.3],[141,14.3,15.8,17.8,20.6,24.7],[144,14.4,16.0,18.0,20.8,25.0],[147,14.5,16.1,18.2,21.1,25.3],[150,14.7,16.3,18.4,21.3,25.6],[153,14.8,16.4,18.6,21.6,25.9],[156,14.9,16.6,18.8,21.8,26.2],[159,15.1,16.8,19.0,22.0,26.5],[162,15.2,16.9,19.2,22.3,26.8],[165,15.3,17.1,19.4,22.5,27.1],[168,15.4,17.2,19.6,22.7,27.3],[171,15.6,17.4,19.7,22.9,27.6],[174,15.7,17.5,19.9,23.1,27.8],[177,15.8,17.6,20.1,23.3,28.0],[180,15.9,17.8,20.2,23.5,28.2],[183,16.0,17.9,20.4,23.7,28.4],[186,16.0,18.0,20.5,23.8,28.6],[189,16.1,18.1,20.6,24.0,28.7],[192,16.2,18.2,20.7,24.1,28.9],[195,16.2,18.2,20.8,24.2,29.0],[198,16.3,18.3,20.9,24.3,29.1],[201,16.3,18.4,21.0,24.4,29.2],[204,16.4,18.4,21.0,24.5,29.3],[207,16.4,18.5,21.1,24.6,29.4],[210,16.4,18.5,21.2,24.6,29.4],[213,16.4,18.5,21.2,24.7,29.5],[216,16.4,18.6,21.3,24.8,29.5],[219,16.5,18.6,21.3,24.8,29.6],[222,16.5,18.6,21.3,24.9,29.6],[225,16.5,18.7,21.4,24.9,29.6],[228,16.5,18.7,21.4,25.0,29.7]]
          }
        };

        var sdLabels = ['SD \u22122', 'SD \u22121', 'Median', 'SD +1', 'SD +2'];
        var sdColors = ['#f87171','#fbbf24','#22c55e','#fbbf24','#f87171'];
        var sdDash   = ['4,3', '4,3', '0', '4,3', '4,3'];

        function drawChart(containerId, refData, studentPoints, yLabel) {
          var container = document.getElementById(containerId);
          if (!container || !refData || refData.length === 0) return;

          var width = container.clientWidth || 300;
          var isModal = containerId === 'chart-modal';
          var height = isModal ? 420 : 260;
          var margin = isModal
            ? { top: 16, right: 56, bottom: 40, left: 48 }
            : { top: 12, right: 44, bottom: 32, left: 36 };
          var w = width - margin.left - margin.right;
          var h = height - margin.top - margin.bottom;

          var allAges = refData.map(d => d[0]);
          var studentAges = studentPoints.map(d => d.age);
          var minAge = d3.min(allAges);
          var maxAge = d3.max(allAges);

          if (studentAges.length > 0) {
            var sMin = d3.min(studentAges);
            var sMax = d3.max(studentAges);
            minAge = Math.max(minAge, Math.floor((sMin - 12) / 12) * 12);
            maxAge = Math.min(maxAge, Math.ceil((sMax + 12) / 12) * 12);
          } else if (anchorAge) {
            minAge = Math.max(minAge, Math.floor((anchorAge - 12) / 12) * 12);
            maxAge = Math.min(maxAge, Math.ceil((anchorAge + 48) / 12) * 12);
          }

          var visRef = refData.filter(d => d[0] >= minAge && d[0] <= maxAge);
          if (visRef.length < 2) visRef = refData;

          var allVals = [];
          visRef.forEach(d => { for (var i = 1; i <= 5; i++) allVals.push(d[i]); });
          studentPoints.forEach(d => { if (d.val != null) allVals.push(d.val); });

          var x = d3.scaleLinear().domain([d3.min(visRef, d => d[0]), d3.max(visRef, d => d[0])]).range([0, w]);
          var y = d3.scaleLinear().domain([d3.min(allVals) * 0.95, d3.max(allVals) * 1.05]).range([h, 0]).nice();

          d3.select(container).selectAll('svg').remove();
          var svg = d3.select(container).append('svg')
            .attr('width', width).attr('height', height)
            .append('g').attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

          var fs = isModal ? '11px' : '9px';
          var fsLabel = isModal ? '12px' : '9px';
          var fsSd = isModal ? '9px' : '7px';

          svg.append('g').attr('transform', 'translate(0,' + h + ')')
            .call(d3.axisBottom(x).tickValues(
              d3.range(Math.ceil(d3.min(visRef, d => d[0]) / 12) * 12, d3.max(visRef, d => d[0]) + 1, 12)
            ).tickFormat(d => (d / 12) + 'y'))
            .selectAll('text').style('font-size', fs).style('fill', '#6b7280');
          svg.append('text').attr('x', w / 2).attr('y', h + (isModal ? 34 : 28))
            .attr('text-anchor', 'middle').style('font-size', fs).style('fill', '#9ca3af').text('Age');

          svg.append('g')
            .call(d3.axisLeft(y).ticks(isModal ? 8 : 5))
            .selectAll('text').style('font-size', fs).style('fill', '#6b7280');
          svg.append('text').attr('transform', 'rotate(-90)').attr('y', isModal ? -38 : -30).attr('x', -h / 2)
            .attr('text-anchor', 'middle').style('font-size', fs).style('fill', '#9ca3af').text(yLabel);

          svg.append('g').attr('class', 'grid')
            .call(d3.axisLeft(y).ticks(5).tickSize(-w).tickFormat(''))
            .selectAll('line').style('stroke', '#f3f4f6').style('stroke-width', 1);
          svg.selectAll('.grid .domain').remove();

          for (var si = 0; si < 5; si++) {
            (function(idx) {
              svg.append('path').datum(visRef)
                .attr('fill', 'none').attr('stroke', sdColors[idx])
                .attr('stroke-width', idx === 2 ? 1.5 : 1)
                .attr('stroke-dasharray', sdDash[idx])
                .attr('opacity', idx === 2 ? 1 : 0.6)
                .attr('d', d3.line().x(d => x(d[0])).y(d => y(d[idx + 1])).curve(d3.curveMonotoneX));
              var last = visRef[visRef.length - 1];
              svg.append('text').attr('x', x(last[0]) + 2).attr('y', y(last[idx + 1]))
                .attr('dy', '0.35em').style('font-size', fsSd).style('fill', sdColors[idx]).text(sdLabels[idx]);
            })(si);
          }

          var validPts = studentPoints.filter(d => d.val != null);
          if (validPts.length > 1) {
            svg.append('path').datum(validPts)
              .attr('fill', 'none').attr('stroke', '#4f46e5').attr('stroke-width', 2)
              .attr('d', d3.line().x(d => x(d.age)).y(d => y(d.val)).curve(d3.curveMonotoneX));
          }

          var tooltip = d3.select(container).append('div')
            .style('position', 'absolute').style('display', 'none').style('z-index', '10')
            .style('background', '#1f2937').style('color', '#fff')
            .style('padding', '4px 8px').style('border-radius', '4px')
            .style('font-size', '10px').style('pointer-events', 'none').style('white-space', 'nowrap');

          svg.selectAll('.dot').data(validPts).enter().append('circle')
            .attr('cx', d => x(d.age)).attr('cy', d => y(d.val))
            .attr('r', isModal ? 6 : 4).attr('fill', '#4f46e5').attr('stroke', '#fff').attr('stroke-width', isModal ? 2 : 1.5)
            .style('cursor', 'pointer')
            .on('mouseover', (event, d) => {
              var yrs = Math.floor(d.age / 12), mos = Math.round(d.age % 12);
              tooltip.html(d.date + '<br>' + yrs + 'y ' + mos + 'm \u2014 ' + d.val + ' ' + yLabel).style('display', 'block');
            })
            .on('mousemove', (event) => {
              var rect = container.getBoundingClientRect();
              tooltip.style('left', (event.clientX - rect.left + 10) + 'px').style('top', (event.clientY - rect.top - 10) + 'px');
            })
            .on('mouseout', () => tooltip.style('display', 'none'));
        }

        var m = measurements;
        var chartConfigs = {
          height: { ref: WHO.height[sex], pts: m.map(r => ({age:r.age, val:r.height, date:r.date})), unit: 'cm' },
          weight: { ref: WHO.weight[sex], pts: m.map(r => ({age:r.age, val:r.weight, date:r.date})), unit: 'kg' },
          bmi:    { ref: WHO.bmi[sex],    pts: m.map(r => ({age:r.age, val:r.bmi,    date:r.date})), unit: 'kg/m\u00B2' },
        };

        function redrawAll() {
          drawChart('chart-height', chartConfigs.height.ref, chartConfigs.height.pts, chartConfigs.height.unit);
          drawChart('chart-weight', chartConfigs.weight.ref, chartConfigs.weight.pts, chartConfigs.weight.unit);
          drawChart('chart-bmi',    chartConfigs.bmi.ref,    chartConfigs.bmi.pts,    chartConfigs.bmi.unit);
        }
        redrawAll();

        var resizeTimer;
        window.addEventListener('resize', function () {
          clearTimeout(resizeTimer);
          resizeTimer = setTimeout(redrawAll, 120);
        });

        window._drawGrowthChart = function(containerId, chartKey) {
          var cfg = chartConfigs[chartKey];
          if (cfg) drawChart(containerId, cfg.ref, cfg.pts, cfg.unit);
        };
      });
    });
  "
>
  <h3 class="text-sm font-medium text-gray-700 mb-4">WHO Growth Curves</h3>

  <div class="relative">
  @unless ($hasData)
    {{-- The notice sits on the charts, not under them: the dimmed curves are the
         empty state, and this says why they are empty. --}}
    <div class="absolute inset-0 z-10 flex items-center justify-center px-4 pointer-events-none">
      <p class="max-w-md px-4 py-3 text-sm text-center text-gray-700 bg-white border border-gray-200 rounded-lg shadow-sm">
        <span class="font-semibold">No measurements yet.</span>
        Record a routine checkup with a height and weight, and {{ $user->first_name }} appears on these curves.
      </p>
    </div>
  @endunless
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 {{ $hasData ? '' : 'opacity-40 saturate-50' }}">
    <div class="border border-gray-200 rounded-lg bg-white p-4 cursor-pointer hover:border-indigo-300 hover:shadow-sm transition-colors" style="position:relative"
         @click="$dispatch('open-growth-modal', { chart: 'height', title: 'Height-for-age ({{ $sex }})' })">
      <div class="flex items-center justify-between mb-1">
        <span class="text-xs font-medium text-gray-500">Height-for-age ({{ $sex }})</span>
        <svg style="width:14px;height:14px;flex-shrink:0" fill="none" stroke="#9ca3af" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"/></svg>
      </div>
      <div id="chart-height"></div>
    </div>
    <div class="border border-gray-200 rounded-lg bg-white p-4 cursor-pointer hover:border-indigo-300 hover:shadow-sm transition-colors" style="position:relative"
         @click="$dispatch('open-growth-modal', { chart: 'weight', title: 'Weight-for-age ({{ $sex }})' })">
      <div class="flex items-center justify-between mb-1">
        <span class="text-xs font-medium text-gray-500">Weight-for-age ({{ $sex }})</span>
        <svg style="width:14px;height:14px;flex-shrink:0" fill="none" stroke="#9ca3af" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"/></svg>
      </div>
      <div id="chart-weight"></div>
    </div>
    <div class="border border-gray-200 rounded-lg bg-white p-4 cursor-pointer hover:border-indigo-300 hover:shadow-sm transition-colors" style="position:relative"
         @click="$dispatch('open-growth-modal', { chart: 'bmi', title: 'BMI-for-age ({{ $sex }})' })">
      <div class="flex items-center justify-between mb-1">
        <span class="text-xs font-medium text-gray-500">BMI-for-age ({{ $sex }})</span>
        <svg style="width:14px;height:14px;flex-shrink:0" fill="none" stroke="#9ca3af" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"/></svg>
      </div>
      <div id="chart-bmi"></div>
    </div>
  </div>
  </div>

  <p class="mt-2 text-xs text-gray-500">
    Source: WHO Growth Reference 2007 (5-19 years). Lines show SD -2, -1, median, +1, +2.
    @if ($hasData) Click to enlarge. @endif
  </p>

  {{-- Modal (teleported to body to escape overflow clipping) --}}
  <div x-data="{ open: false, title: '', chart: '' }"
       @open-growth-modal.window="open = true; title = $event.detail.title; chart = $event.detail.chart; $nextTick(() => { if (window._drawGrowthChart) window._drawGrowthChart('chart-modal', chart); })"
       @keydown.escape.window="open = false">
    <template x-teleport="body">
      <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
           x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
           class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="display:none;">
        <div class="fixed inset-0" style="background:rgba(0,0,0,0.5)" @click="open = false"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6" @click.stop>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-800" x-text="title"></h3>
            <button type="button" @click="open = false" aria-label="Close" class="p-1 text-gray-500 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div id="chart-modal" style="position:relative; min-height:420px;"></div>
          <p class="mt-3 text-xs text-gray-500">Source: WHO Growth Reference 2007 (5-19 years). Lines show SD -2, -1, median, +1, +2.</p>
        </div>
      </div>
    </template>
  </div>
</div>
@elseif(!$birthDate)
<div class="p-4 mt-6 text-sm text-center text-gray-500 border border-gray-200 rounded-md">
  Set a date of birth in the profile to see WHO growth curves.
</div>
@endif
