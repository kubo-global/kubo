<div id="skill-graph-container" style="position:relative;">
  <style>
    #skill-graph-container {
      font-family: 'Nunito', ui-sans-serif, system-ui, sans-serif;
    }

    .sg-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
      overflow: hidden;
    }

    .sg-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
      border-bottom: 1px solid #e5e7eb;
    }

    .sg-title { font-size: 0.875rem; font-weight: 700; color: #374151; }

    .sg-legend { display: flex; gap: 14px; align-items: center; }
    .sg-legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.6875rem; color: #6b7280; }
    .sg-legend-dot { width: 10px; height: 10px; border-radius: 3px; }

    .sg-stats {
      display: flex;
      border-bottom: 1px solid #e5e7eb;
    }
    .sg-stat {
      flex: 1; padding: 10px 12px; text-align: center;
      border-right: 1px solid #e5e7eb;
    }
    .sg-stat:last-child { border-right: none; }
    .sg-stat-val { font-size: 1.125rem; font-weight: 800; color: #374151; }
    .sg-stat-label { font-size: 0.625rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 1px; }

    .sg-canvas { position: relative; background: #fafafa; overflow-x: auto; }

    .sg-canvas svg text.node-label {
      font-family: 'Nunito', sans-serif;
      font-size: 9px; font-weight: 700;
      pointer-events: none; text-anchor: middle;
    }
    .sg-canvas svg text.grade-label {
      font-family: 'Nunito', sans-serif;
      font-size: 10px; font-weight: 800; fill: #9ca3af;
      text-anchor: start; text-transform: uppercase; letter-spacing: 0.05em;
    }

    .sg-tooltip {
      position: fixed; pointer-events: none; opacity: 0;
      transition: opacity 0.15s; z-index: 100;
    }
    .sg-tooltip.visible { opacity: 1; }
    .sg-tooltip-inner {
      background: #fff; border: 1px solid #e5e7eb;
      border-radius: 0.5rem; padding: 10px 14px; min-width: 180px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .sg-tooltip-name { font-weight: 800; font-size: 0.8125rem; color: #374151; margin-bottom: 6px; }
    .sg-tooltip-row { display: flex; justify-content: space-between; font-size: 0.75rem; color: #9ca3af; line-height: 1.8; }
    .sg-tooltip-val { color: #374151; font-weight: 700; }
    .sg-tooltip-bar { height: 3px; border-radius: 2px; background: #e5e7eb; margin-top: 8px; overflow: hidden; }
    .sg-tooltip-fill { height: 100%; border-radius: 2px; }

    .sg-loading { display: flex; align-items: center; justify-content: center; height: 80px; gap: 6px; }
    .sg-loading-dot { width: 8px; height: 8px; border-radius: 50%; background: #d1d5db; animation: sg-pulse 1.2s ease-in-out infinite; }
    .sg-loading-dot:nth-child(2) { animation-delay: 0.15s; }
    .sg-loading-dot:nth-child(3) { animation-delay: 0.3s; }
    @keyframes sg-pulse { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }
  </style>

  <div class="sg-card">
    <div style="display:flex; align-items:center; justify-content:flex-end; gap:14px; padding:8px 16px;">
      <div class="sg-legend-item"><div class="sg-legend-dot" style="background:#22c55e;"></div>Mastered</div>
      <div class="sg-legend-item"><div class="sg-legend-dot" style="background:#f59e0b;"></div>In progress</div>
      <div class="sg-legend-item"><div class="sg-legend-dot" style="background:#ef4444;"></div>Struggling</div>
      <div class="sg-legend-item"><div class="sg-legend-dot" style="background:#d1d5db;"></div>Not started</div>
    </div>

    <div class="sg-canvas" id="sg-canvas">
      <div class="sg-loading" id="sg-loading">
        <span class="sg-loading-dot"></span><span class="sg-loading-dot"></span><span class="sg-loading-dot"></span>
      </div>
    </div>

    <div class="sg-tooltip" id="sg-tooltip">
      <div class="sg-tooltip-inner">
        <div class="sg-tooltip-name" id="sg-tip-name"></div>
        <div class="sg-tooltip-row"><span>Mastery</span><span class="sg-tooltip-val" id="sg-tip-mastery"></span></div>
        <div class="sg-tooltip-row"><span>Attempts</span><span class="sg-tooltip-val" id="sg-tip-attempts"></span></div>
        <div class="sg-tooltip-row"><span>Status</span><span class="sg-tooltip-val" id="sg-tip-status"></span></div>
        <div class="sg-tooltip-bar"><div class="sg-tooltip-fill" id="sg-tip-bar"></div></div>
      </div>
    </div>
  </div>

  <script src="/vendor/d3.v7.min.js"></script>
  <script>
  (function() {
    var studentId = @json($user->id);
    var container = document.getElementById('sg-canvas');
    var tooltip = document.getElementById('sg-tooltip');
    var initialized = false;

    var colors = {
      mastered:    { fill: '#22c55e', stroke: '#16a34a' },
      in_progress: { fill: '#f59e0b', stroke: '#d97706' },
      struggling:  { fill: '#ef4444', stroke: '#dc2626' },
      not_started: { fill: '#d1d5db', stroke: '#9ca3af' }
    };
    // Never let an unmapped status (a new one from the adaptive engine) throw
    // mid-render and blank out every node after it.
    function colorFor(status) { return colors[status] || colors.not_started; }

    var observer = new IntersectionObserver(function(entries) {
      if (entries[0].isIntersecting && !initialized) {
        initialized = true;
        observer.disconnect();
        fetch('/students/' + studentId + '/skill-graph')
          .then(function(r) { return r.json(); })
          .then(function(data) { render(data); })
          .catch(function() {
            document.getElementById('sg-loading').textContent = 'Could not load skill data.';
          });
      }
    });
    observer.observe(container);

    function render(data) {
      document.getElementById('sg-loading').style.display = 'none';

      // ── Build adjacency ──
      var nodeMap = {};
      data.nodes.forEach(function(n) { nodeMap[n.id] = n; n.parents = []; n.children = []; });
      data.links.forEach(function(l) {
        var s = l.source;
        var t = l.target;
        if (nodeMap[s] && nodeMap[t]) {
          nodeMap[s].children.push(t);
          nodeMap[t].parents.push(s);
        }
      });

      // ── Grid layout: grade sets min row, then push down by prereqs ──
      var blockSize = 36;
      var cellW = 105;
      var cellH = 85;
      var labelMargin = 80;
      var padX = 15;
      var padY = 20;

      // Collect unique grades in order
      var grades = [];
      var gradeSet = {};
      data.nodes.forEach(function(n) {
        if (!gradeSet[n.grade_index]) {
          gradeSet[n.grade_index] = n.grade_name;
          grades.push({ index: n.grade_index, name: n.grade_name });
        }
      });
      grades.sort(function(a, b) { return a.index - b.index; });

      // Assign row: max(grade_min_row, max(parent.row) + 1)
      // First pass: assign grade_min_row
      var gradeMinRow = {};
      var currentRow = 0;
      grades.forEach(function(g) {
        gradeMinRow[g.index] = currentRow;
        // Count how many rows this grade needs (at least 1)
        var gradeNodes = data.nodes.filter(function(n) { return n.grade_index === g.index; });
        // We'll figure out the actual rows after placing
        currentRow += Math.max(1, Math.ceil(gradeNodes.length / 10)); // rough estimate, will refine
      });

      // Topological sort + row assignment
      data.nodes.forEach(function(n) { n.row = gradeMinRow[n.grade_index]; });

      // Iterate until stable: each node's row = max(grade_min, max(parent.row)+1)
      var changed = true;
      var safety = 0;
      while (changed && safety < 50) {
        changed = false;
        safety++;
        data.nodes.forEach(function(n) {
          var minRow = gradeMinRow[n.grade_index];
          n.parents.forEach(function(pid) {
            var pRow = nodeMap[pid].row + 1;
            if (pRow > minRow) minRow = pRow;
          });
          if (minRow > n.row) {
            n.row = minRow;
            changed = true;
          }
        });
      }

      // Assign columns using barycentric method (Sugiyama-style)
      var rowNodes = {};
      data.nodes.forEach(function(n) {
        if (!rowNodes[n.row]) rowNodes[n.row] = [];
        rowNodes[n.row].push(n);
      });

      var sortedRows = Object.keys(rowNodes).sort(function(a,b){return a-b;});

      // Initial column assignment
      sortedRows.forEach(function(r) {
        rowNodes[r].forEach(function(n, i) { n.col = i; });
      });

      // Barycenter: average position of all connected nodes (parents + children)
      function barycenter(n) {
        var neighbors = [];
        n.parents.forEach(function(pid) { neighbors.push(nodeMap[pid]); });
        n.children.forEach(function(cid) { neighbors.push(nodeMap[cid]); });
        if (neighbors.length === 0) return n.col;
        var sum = 0;
        neighbors.forEach(function(nb) { sum += nb.col; });
        return sum / neighbors.length;
      }

      // Iterate: sweep down then up, multiple passes
      for (var pass = 0; pass < 12; pass++) {
        // Down sweep
        sortedRows.forEach(function(r) {
          rowNodes[r].sort(function(a, b) { return barycenter(a) - barycenter(b); });
          rowNodes[r].forEach(function(n, i) { n.col = i; });
        });
        // Up sweep
        for (var ri = sortedRows.length - 1; ri >= 0; ri--) {
          var r = sortedRows[ri];
          rowNodes[r].sort(function(a, b) { return barycenter(a) - barycenter(b); });
          rowNodes[r].forEach(function(n, i) { n.col = i; });
        }
      }

      // Compute positions — spread nodes across the full width
      // Center nodes in available space
      var maxNodesInRow = 0;
      Object.keys(rowNodes).forEach(function(r) {
        if (rowNodes[r].length > maxNodesInRow) maxNodesInRow = rowNodes[r].length;
      });
      var availableWidth = container.clientWidth - labelMargin - padX * 2;
      data.nodes.forEach(function(n) {
        var rowCount = rowNodes[n.row].length;
        var rowWidth = rowCount * cellW;
        var offsetX = labelMargin + padX + (availableWidth - rowWidth) / 2;
        n.x = offsetX + n.col * cellW + cellW / 2;
        n.y = padY + n.row * cellH + cellH / 2;
      });

      var maxRow = 0;
      data.nodes.forEach(function(n) { if (n.row > maxRow) maxRow = n.row; });

      var svgWidth = Math.max(container.clientWidth, maxNodesInRow * cellW + labelMargin + padX * 2);
      var svgHeight = (maxRow + 1) * cellH + padY * 2 + 10;

      var svg = d3.select(container).append('svg')
        .attr('width', svgWidth)
        .attr('height', svgHeight);

      // Grade labels + separator lines
      grades.forEach(function(g) {
        var minY = padY + gradeMinRow[g.index] * cellH;
        // Draw a subtle separator line
        svg.append('line')
          .attr('x1', labelMargin - 5).attr('x2', svgWidth)
          .attr('y1', minY).attr('y2', minY)
          .attr('stroke', '#e5e7eb').attr('stroke-width', 1)
          .attr('stroke-dasharray', '3,3');
        // Grade name
        svg.append('text')
          .attr('class', 'grade-label')
          .attr('x', 8).attr('y', minY + 14)
          .text(g.name);
      });

      // Defs: arrows
      var defs = svg.append('defs');
      defs.append('marker').attr('id', 'sg-arrow')
        .attr('viewBox', '0 -3 6 6').attr('refX', 6).attr('refY', 0)
        .attr('markerWidth', 4).attr('markerHeight', 4).attr('orient', 'auto')
        .append('path').attr('d', 'M0,-2.5L5,0L0,2.5').attr('fill', '#d1d5db');
      defs.append('marker').attr('id', 'sg-arrow-lit')
        .attr('viewBox', '0 -3 6 6').attr('refX', 6).attr('refY', 0)
        .attr('markerWidth', 4).attr('markerHeight', 4).attr('orient', 'auto')
        .append('path').attr('d', 'M0,-2.5L5,0L0,2.5').attr('fill', '#86efac');

      // ── Edges: straight diagonal lines ──
      var half = blockSize / 2;
      data.links.forEach(function(l) {
        var src = nodeMap[l.source];
        var tgt = nodeMap[l.target];
        if (!src || !tgt) return;

        var lit = src.status === 'mastered';
        svg.append('line')
          .attr('x1', src.x).attr('y1', src.y + half + 1)
          .attr('x2', tgt.x).attr('y2', tgt.y - half - 1)
          .attr('stroke', lit ? '#4ade80' : '#d1d5db')
          .attr('stroke-width', 1.5)
          .attr('stroke-opacity', lit ? 0.5 : 0.3)
          .attr('data-source', l.source)
          .attr('data-target', l.target);
      });

      // ── Nodes as squares with text below ──
      data.nodes.forEach(function(d) {
        var g = svg.append('g')
          .attr('transform', 'translate(' + d.x + ',' + d.y + ')')
          .attr('cursor', 'pointer');

        g.append('rect')
          .attr('x', -half).attr('y', -half)
          .attr('width', blockSize).attr('height', blockSize)
          .attr('rx', 8).attr('ry', 8)
          .attr('fill', colorFor(d.status).fill)
          .attr('stroke', colorFor(d.status).stroke)
          .attr('stroke-width', 1.5);

        // Checkmark for mastered, dot for in-progress
        if (d.status === 'mastered') {
          g.append('text').attr('text-anchor', 'middle').attr('dy', 5)
            .attr('fill', 'white').attr('font-size', '16px').text('\u2713');
        } else if (d.status === 'in_progress') {
          g.append('text').attr('text-anchor', 'middle').attr('dy', 4)
            .attr('fill', 'white').attr('font-size', '13px').attr('font-weight', 'bold')
            .text(Math.round(d.mastery) + '%');
        }

        // Label below — wrap into lines of ~12 chars
        var words = d.name.split(' ');
        var lines = [];
        var line = '';
        words.forEach(function(w) {
          if ((line + ' ' + w).trim().length > 13 && line) {
            lines.push(line);
            line = w;
          } else {
            line = line ? line + ' ' + w : w;
          }
        });
        if (line) lines.push(line);
        if (lines.length > 2) lines = [lines[0], lines[1] + '...'];

        lines.forEach(function(ln, i) {
          g.append('text').attr('class', 'node-label')
            .attr('dy', half + 12 + i * 11).text(ln).attr('fill', '#374151');
        });

        // Tooltip + path highlight
        g.on('mouseenter', function(event) {
          document.getElementById('sg-tip-name').textContent = d.name;
          document.getElementById('sg-tip-mastery').textContent = Math.round(d.mastery) + '%';
          document.getElementById('sg-tip-attempts').textContent = d.attempts;
          document.getElementById('sg-tip-status').textContent = d.status.replace('_', ' ');
          var bar = document.getElementById('sg-tip-bar');
          bar.style.width = d.mastery + '%';
          bar.style.background = colorFor(d.status).fill;
          tooltip.classList.add('visible');

          // Highlight connected edges
          svg.selectAll('line[data-source="' + d.id + '"], line[data-target="' + d.id + '"]')
            .attr('stroke', '#9ca3af').attr('stroke-width', 2).attr('stroke-opacity', 0.7);
          // Dim unrelated edges
          svg.selectAll('line[data-source]:not([data-source="' + d.id + '"]):not([data-target="' + d.id + '"])')
            .attr('stroke-opacity', 0.1);
        })
        .on('mousemove', function(event) {
          tooltip.style.left = (event.clientX + 12) + 'px';
          tooltip.style.top = (event.clientY - 10) + 'px';
        })
        .on('mouseleave', function() {
          tooltip.classList.remove('visible');
          // Reset all edges
          svg.selectAll('line[data-source]').each(function() {
            var el = d3.select(this);
            var srcId = el.attr('data-source');
            var lit = nodeMap[srcId] && nodeMap[srcId].status === 'mastered';
            el.attr('stroke', lit ? '#4ade80' : '#d1d5db')
              .attr('stroke-width', 1.5).attr('stroke-opacity', lit ? 0.5 : 0.3);
          });
        });
      });
    }
  })();
  </script>
</div>
