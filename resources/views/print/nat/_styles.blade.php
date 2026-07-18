<style>
    /* Grayscale chrome (headers/rule/charts) to cut colour-printing cost; the
       fail (red) / mastery (green) highlight stays, as it carries the meaning. */
    * { font-family: DejaVu Sans, sans-serif; }
    /* big top margin reserves room for the fixed header that repeats every page */
    @page { margin: 2.75cm 1.1cm 1.7cm 1.1cm; }
    body { font-size: 11px; color: #1a1a1a; }

    /* header, fixed so it repeats on every page */
    .page-header { position: fixed; left: 0; right: 0; top: -96px; background: #fff; }
    .report-head { width: 100%; border: none; margin: 0; }
    .report-head td { border: none; vertical-align: middle; }
    .report-head .org { font-size: 18px; font-weight: bold; color: #111; }
    .report-head .sub { font-size: 13px; font-weight: bold; color: #333; margin-top: 4px; }
    .rule { height: 2px; background: #333; margin: 8px 0 0; font-size: 0; line-height: 0; }

    /* data tables */
    table.data { border-collapse: collapse; width: 100%; }
    table.data th, table.data td { border: 1px solid #c4c4c4; padding: 6px 10px; font-size: 10.5px; }
    /* light header: bold text on white + a rule underneath, to save printer ink */
    table.data thead th { background: #fff; color: #14223b; text-align: left; font-weight: bold; border-bottom: 2px solid #333; }
    table.data thead th .max { font-weight: normal; font-size: 8.5px; color: #888; }
    table.data td.num { text-align: right; }
    table.data tr.zebra td { background: #f4f4f4; }
    /* totals row: bold + a rule above instead of a grey fill (saves ink) */
    table.data tr.total td { font-weight: bold; background: #fff; border-top: 2px solid #333; }
    /* qualified so the fail/mastery fill out-specifies the zebra-row background */
    table.data tr td.fail { background: #fbe0e0; color: #8b2222; font-weight: bold; }
    table.data tr td.mastery { background: #ddf0e5; color: #296643; font-weight: bold; }
    td.absent { color: #8a8a8a; text-align: center; }

    /* analysis blocks */
    /* one subject heading owns both the chart and the table; subjects split by a rule */
    .block { page-break-inside: avoid; }
    .block-sep { border: none; border-top: 1px solid #b4b4b4; margin: 26px 0; }
    /* left accent bar (not an underline) so it doesn't echo the header rule */
    .subject-title { font-size: 14px; font-weight: bold; color: #14223b; border-left: 4px solid #3d5494; padding-left: 9px; margin-bottom: 8px; }
    .chart-box { border: 1px solid #d0d0d0; padding: 8px 14px 6px; margin-bottom: 6px; }
    .chart-sub { text-align: center; font-size: 11px; color: #555; margin-bottom: 4px; }
    .legend { margin-top: 11px; font-size: 9px; color: #555; }
    .legend .sw { display: inline-block; width: 11px; height: 11px; vertical-align: -1px; border: 1px solid #999; margin-right: 5px; }
    .legend .item { margin-right: 18px; }

    /* footer on every page */
    footer { position: fixed; left: 0; right: 0; bottom: -20px; height: 26px; border-top: 1px solid #d2d2d2; }
    footer table { width: 100%; border: none; margin-top: 4px; }
    footer td { border: none; color: #8a8a8a; font-size: 8px; vertical-align: middle; }
    footer .pagenum:after { content: "Page " counter(page); }
</style>
