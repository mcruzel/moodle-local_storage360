<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Dashboard page for Storage 360.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/storage360/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/storage360:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/dashboard.php'));
$PAGE->set_title(get_string('dashboard:title', 'local_storage360'));
$PAGE->set_heading(get_string('dashboard:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

// Check if snapshots exist before running expensive queries.
$hassnapshots = $DB->get_field_sql('SELECT MAX(timecreated) FROM {local_storage360_history}');

if (!$hassnapshots) {
    echo $OUTPUT->header();
    $tabs = local_storage360_get_tabs('dashboard');
    print_tabs([$tabs], 'dashboard');
    echo $OUTPUT->notification(get_string('nosnapshots', 'local_storage360'), 'info');
    $collecturl = new moodle_url('/local/storage360/pages/collect.php', ['sesskey' => sesskey()]);
    echo html_writer::link($collecturl, get_string('collectnow', 'local_storage360'),
        ['class' => 'btn btn-primary']);
    echo $OUTPUT->footer();
    die();
}

$calculator = new \local_storage360\analytics\storage_calculator();

// Gather all data.
$globalstats = $calculator->get_global_stats();
$diskspace = $calculator->get_disk_space();
$growthrate = $calculator->get_growth_rate();
$topcourses = $calculator->get_top_courses(5);
$components = $calculator->get_by_component();

echo $OUTPUT->header();

// Tabs navigation.
$tabs = local_storage360_get_tabs('dashboard');
print_tabs([$tabs], 'dashboard');
echo local_storage360_render_snapshot_indicator();

// KPI Cards row.
echo html_writer::start_div('row mb-4');

// Total storage.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('dashboard:totalstorage', 'local_storage360'), ['class' => 'card-title text-muted']);
echo html_writer::tag('h2', local_storage360_format_size($globalstats->total_size), ['class' => 'card-text text-primary']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Total files.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('dashboard:totalfiles', 'local_storage360'), ['class' => 'card-title text-muted']);
echo html_writer::tag('h2', number_format($globalstats->total_files), ['class' => 'card-text text-primary']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Average file size.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('dashboard:avgfilesize', 'local_storage360'), ['class' => 'card-title text-muted']);
echo html_writer::tag('h2', local_storage360_format_size((int) $globalstats->avg_size), ['class' => 'card-text text-primary']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Growth rate.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center' . (!empty($growthrate->data_pending) ? ' bg-light' : ''));
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('dashboard:growthrate', 'local_storage360'), ['class' => 'card-title text-muted']);
if (!empty($growthrate->data_pending)) {
    echo html_writer::tag('h2', get_string('dashboard:growthrate_pending', 'local_storage360'),
        ['class' => 'card-text text-muted']);
    echo html_writer::tag('small',
        get_string('dashboard:growthrate_available', 'local_storage360',
            userdate($growthrate->data_available_from, get_string('strftimedate', 'langconfig'))),
        ['class' => 'text-muted d-block mt-1']);
} else {
    $growthclass = $growthrate->growth_bytes > 0 ? 'text-warning' : 'text-success';
    $growthtext = local_storage360_format_size(abs($growthrate->current_month_size));
    if ($growthrate->growth_percent != 0) {
        $growthtext .= ' (' . ($growthrate->growth_percent > 0 ? '+' : '') . $growthrate->growth_percent . '%)';
    }
    echo html_writer::tag('h2', $growthtext, ['class' => 'card-text ' . $growthclass]);
}
echo html_writer::tag('small',
    get_string('dashboard:nextupdate', 'local_storage360',
        userdate($growthrate->next_update, get_string('strftimetime', 'langconfig'))),
    ['class' => 'text-muted d-block text-right mt-2']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.

// Integrity scan progress card.
$scanprogress = $calculator->get_scan_progress();
if ($scanprogress !== null) {
    echo html_writer::start_div('row mb-4');
    echo html_writer::start_div('col-12');
    echo html_writer::start_div('card');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', get_string('scan:progress_title', 'local_storage360'), ['class' => 'card-title']);

    // Progress bar.
    $pctclass = $scanprogress->percent >= 100 ? 'bg-success' : 'bg-info';
    echo html_writer::start_div('progress mb-2', ['style' => 'height: 25px;']);
    echo html_writer::tag('div',
        $scanprogress->percent . '%',
        ['class' => 'progress-bar ' . $pctclass, 'role' => 'progressbar',
         'style' => 'width: ' . $scanprogress->percent . '%;',
         'aria-valuenow' => $scanprogress->percent,
         'aria-valuemin' => '0', 'aria-valuemax' => '100']);
    echo html_writer::end_div();

    // Stats line.
    $a = new stdClass();
    $a->scanned = number_format($scanprogress->scanned);
    $a->total = number_format($scanprogress->total_unique);
    $a->percent = $scanprogress->percent;
    echo html_writer::tag('p', get_string('scan:progress_detail', 'local_storage360', $a),
        ['class' => 'mb-1']);

    // Anomaly summary.
    if ($scanprogress->anomalies > 0) {
        $anomalyparts = [];
        if ($scanprogress->missing > 0) {
            $anomalyparts[] = get_string('scan:missing_count', 'local_storage360', $scanprogress->missing);
        }
        if ($scanprogress->size_mismatch > 0) {
            $anomalyparts[] = get_string('scan:mismatch_count', 'local_storage360', $scanprogress->size_mismatch);
        }
        if ($scanprogress->in_trash > 0) {
            $anomalyparts[] = get_string('scan:intrash_count', 'local_storage360', $scanprogress->in_trash);
        }
        echo html_writer::tag('p',
            html_writer::tag('span',
                get_string('scan:anomalies_found', 'local_storage360', $scanprogress->anomalies),
                ['class' => 'badge badge-warning']
            ) . ' ' . implode(' | ', $anomalyparts),
            ['class' => 'mb-0']
        );
    } else if ($scanprogress->percent >= 100) {
        echo html_writer::tag('p',
            html_writer::tag('span',
                get_string('scan:all_ok', 'local_storage360'),
                ['class' => 'badge badge-success']
            ),
            ['class' => 'mb-0']
        );
    }

    // Orphan files summary.
    if ($scanprogress->orphans > 0) {
        $orphantext = get_string('scan:orphan_count', 'local_storage360', $scanprogress->orphans)
            . ' (' . local_storage360_format_size($scanprogress->orphan_size) . ')';
        $orphansurl = new moodle_url('/local/storage360/pages/orphans.php');
        echo html_writer::tag('p',
            html_writer::tag('span', $orphantext, ['class' => 'badge badge-warning'])
            . ' ' . html_writer::link($orphansurl, get_string('nav:orphans', 'local_storage360'),
                ['class' => 'small']),
            ['class' => 'mb-0 mt-1']
        );
    }

    // Disk scan progress indicator.
    $cursor = $scanprogress->orphan_cursor ?? null;
    if ($cursor === 'complete') {
        $disktext = get_string('orphans:disk_scan_complete', 'local_storage360');
    } else if (!empty($cursor)) {
        $disktext = get_string('orphans:disk_scan_progress', 'local_storage360', $cursor);
    } else {
        $disktext = get_string('orphans:disk_scan_notstarted', 'local_storage360');
    }
    echo html_writer::tag('p', html_writer::tag('small', $disktext, ['class' => 'text-muted']),
        ['class' => 'mb-0 mt-1']);

    echo html_writer::end_div(); // card-body.
    echo html_writer::end_div(); // card.
    echo html_writer::end_div(); // col-12.
    echo html_writer::end_div(); // row.
}

// Disk usage gauge + component chart row.
echo html_writer::start_div('row mb-4');

// Disk gauge.
echo html_writer::start_div('col-md-6');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('dashboard:diskusage', 'local_storage360'), ['class' => 'card-title']);
if ($diskspace) {
    echo html_writer::tag('canvas', '', ['id' => 'diskGauge', 'height' => '300']);
    echo html_writer::start_div('text-center mt-2');
    echo html_writer::tag('p', get_string('dashboard:disktotal', 'local_storage360') . ': ' .
        local_storage360_format_size($diskspace->total));
    echo html_writer::tag('p', get_string('dashboard:diskfree', 'local_storage360') . ': ' .
        local_storage360_format_size($diskspace->free));
    echo html_writer::tag('p', get_string('dashboard:diskpercent', 'local_storage360') . ': ' .
        $diskspace->percent . '%', ['class' => 'font-weight-bold']);
    echo html_writer::end_div();
} else {
    echo html_writer::tag('p', get_string('dashboard:nodiskinfo', 'local_storage360'), ['class' => 'text-muted']);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Component pie chart.
echo html_writer::start_div('col-md-6');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('dashboard:bycomponent', 'local_storage360'), ['class' => 'card-title']);
echo html_writer::tag('canvas', '', ['id' => 'componentChart', 'height' => '300']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.

// Top 5 courses table.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('dashboard:top5courses', 'local_storage360'), ['class' => 'card-title']);

if (!empty($topcourses)) {
    $table = new html_table();
    $table->head = ['#', get_string('courses:coursename', 'local_storage360'),
                    get_string('courses:totalsize', 'local_storage360'),
                    get_string('courses:filecount', 'local_storage360')];
    $table->attributes['class'] = 'table table-striped';

    $rank = 1;
    foreach ($topcourses as $course) {
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        $table->data[] = [
            $rank++,
            html_writer::link($courseurl, format_string($course->fullname)),
            local_storage360_format_size((int) $course->total_size),
            number_format((int) $course->file_count),
        ];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('nodata', 'local_storage360'), ['class' => 'text-muted']);
}

echo html_writer::end_div();
echo html_writer::end_div();

// JavaScript for charts.
$chartdata = new stdClass();
$chartdata->diskspace = $diskspace;
$chartdata->components = [];

// Aggregate small components into "Other".
$totalcompsize = array_sum(array_column($components, 'total_size'));
$threshold = $totalcompsize * 0.02; // 2% threshold.
$othersize = 0;
foreach ($components as $comp) {
    if ((int) $comp->total_size >= $threshold) {
        $chartdata->components[] = [
            'label' => $comp->component . '/' . $comp->filearea,
            'size' => (int) $comp->total_size,
        ];
    } else {
        $othersize += (int) $comp->total_size;
    }
}
if ($othersize > 0) {
    $chartdata->components[] = ['label' => 'other', 'size' => $othersize];
}

$PAGE->requires->js_amd_inline("
require(['core/chartjs'], function(ChartModule) {
    var Chart = ChartModule.default || ChartModule;
    var data = " . json_encode($chartdata) . ";

    // Disk gauge (doughnut chart).
    if (data.diskspace) {
        var gaugeCtx = document.getElementById('diskGauge');
        if (gaugeCtx) {
            var pct = data.diskspace.percent;
            var color = pct < 70 ? '#28a745' : (pct < 85 ? '#ffc107' : '#dc3545');
            new Chart(gaugeCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: [" . json_encode(get_string('dashboard:diskusage', 'local_storage360')) . ",
                             " . json_encode(get_string('dashboard:diskfree', 'local_storage360')) . "],
                    datasets: [{
                        data: [data.diskspace.used, data.diskspace.free],
                        backgroundColor: [color, '#e9ecef'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 2,
                    cutout: '70%',
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    }

    // Component pie chart.
    var compCtx = document.getElementById('componentChart');
    if (compCtx && data.components.length > 0) {
        var labels = data.components.map(function(c) { return c.label; });
        var sizes = data.components.map(function(c) { return c.size; });
        var colors = [
            '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8',
            '#6610f2', '#e83e8c', '#fd7e14', '#20c997', '#6f42c1',
            '#6c757d', '#343a40', '#adb5bd'
        ];

        new Chart(compCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: sizes,
                    backgroundColor: colors.slice(0, labels.length)
                }]
            },
            options: {
                responsive: true,
                aspectRatio: 2,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
});
");

echo $OUTPUT->footer();
