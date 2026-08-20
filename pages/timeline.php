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
 * Timeline page - storage evolution over time.
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
$PAGE->set_url(new moodle_url('/local/storage360/pages/timeline.php'));
$PAGE->set_title(get_string('timeline:title', 'local_storage360'));
$PAGE->set_heading(get_string('timeline:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

// Check if snapshots exist before running expensive queries.
$hassnapshots = $DB->get_field_sql('SELECT MAX(timecreated) FROM {local_storage360_history}');

if (!$hassnapshots) {
    echo $OUTPUT->header();
    $tabs = local_storage360_get_tabs('timeline');
    print_tabs([$tabs], 'timeline');
    echo $OUTPUT->notification(get_string('nosnapshots', 'local_storage360'), 'info');
    $collecturl = new moodle_url('/local/storage360/pages/collect.php', ['sesskey' => sesskey()]);
    echo html_writer::link($collecturl, get_string('collectnow', 'local_storage360'),
        ['class' => 'btn btn-primary']);
    echo $OUTPUT->footer();
    die();
}

$calculator = new \local_storage360\analytics\storage_calculator();

// Get historical data from snapshots.
$history = $calculator->get_history();

// Get retrospective analysis from file creation dates.
$monthly = $calculator->get_monthly_additions(24);

echo $OUTPUT->header();

$tabs = local_storage360_get_tabs('timeline');
print_tabs([$tabs], 'timeline');
echo local_storage360_render_snapshot_indicator();

// Refresh data button.
echo html_writer::start_div('mb-3');
echo html_writer::link(
    new moodle_url('/local/storage360/pages/collect.php', ['sesskey' => sesskey()]),
    get_string('collectnow', 'local_storage360'),
    ['class' => 'btn btn-outline-primary btn-sm']
);
echo html_writer::end_div();

// Historical snapshots (2 charts side by side).
if (!empty($history)) {
    echo html_writer::start_div('row mb-3');

    // Total storage over time.
    echo html_writer::start_div('col-md-6');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body p-3');
    echo html_writer::tag('h6', get_string('timeline:totalovertime', 'local_storage360'), ['class' => 'card-title mb-2']);
    echo html_writer::start_div('', ['style' => 'height: 200px;']);
    echo html_writer::tag('canvas', '', ['id' => 'historyChart']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // By-component stacked area chart.
    echo html_writer::start_div('col-md-6');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body p-3');
    echo html_writer::tag('h6', get_string('timeline:bycomponent', 'local_storage360'), ['class' => 'card-title mb-2']);
    echo html_writer::start_div('', ['style' => 'height: 200px;']);
    echo html_writer::tag('canvas', '', ['id' => 'componentHistoryChart']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div(); // End row.
} else {
    echo $OUTPUT->notification(get_string('timeline:nohistory', 'local_storage360'), 'info');
}

// Retrospective analysis (2 charts side by side).
if (!empty($monthly)) {
    echo html_writer::start_div('row mb-3');

    // Monthly additions bar chart.
    echo html_writer::start_div('col-md-6');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body p-3');
    echo html_writer::tag('h6', get_string('timeline:retroanalysis', 'local_storage360'), ['class' => 'card-title mb-2']);
    echo html_writer::start_div('', ['style' => 'height: 200px;']);
    echo html_writer::tag('canvas', '', ['id' => 'monthlyChart']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Cumulative line chart.
    echo html_writer::start_div('col-md-6');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body p-3');
    echo html_writer::tag('h6', get_string('timeline:totalovertime', 'local_storage360') . ' (' .
        get_string('timeline:retroanalysis', 'local_storage360') . ')', ['class' => 'card-title mb-2']);
    echo html_writer::start_div('', ['style' => 'height: 200px;']);
    echo html_writer::tag('canvas', '', ['id' => 'cumulativeChart']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div(); // End row.
}

// Prepare chart data.
$historylabels = [];
$historysizes = [];
foreach ($history as $h) {
    $historylabels[] = userdate($h->timecreated, '%Y-%m-%d');
    $historysizes[] = (int) $h->total_size;
}

$monthlylabels = [];
$monthlysizes = [];
$cumulativesizes = [];
$cumulative = 0;
foreach ($monthly as $m) {
    $monthlylabels[] = $m->month;
    $monthlysizes[] = $m->size_added;
    $cumulative += $m->size_added;
    $cumulativesizes[] = $cumulative;
}

// Build by-component history data from the by_component JSON in each snapshot.
// We group component/filearea into readable categories to keep the chart clear.
$componentcategories = [
    'backup' => get_string('cleanup:backups', 'local_storage360'),
    'assignsubmission_file' => get_string('courses:assignmentsize', 'local_storage360'),
    'mod_resource' => get_string('timeline:resources', 'local_storage360'),
    'mod_folder' => get_string('timeline:folders', 'local_storage360'),
    'user/private' => get_string('users:privatefiles', 'local_storage360'),
    'user/draft' => get_string('users:draftfiles', 'local_storage360'),
    'course' => get_string('timeline:coursefiles', 'local_storage360'),
];

$componenthistorylabels = [];
$componentseries = []; // category => [size per snapshot]

foreach ($history as $idx => $h) {
    $componenthistorylabels[] = userdate($h->timecreated, '%Y-%m-%d');
    $bycomp = !empty($h->by_component) ? json_decode($h->by_component, true) : [];

    // Aggregate by category.
    $snapshot = [];
    foreach ($bycomp as $key => $value) {
        $size = is_array($value) ? (int) ($value['total_size'] ?? 0) : (int) $value;
        $parts = explode('/', $key, 2);
        $comp = $parts[0];
        $area = $parts[1] ?? '';

        // Map to a readable category.
        $matched = false;
        if ($comp === 'user' && ($area === 'private' || $area === 'draft')) {
            $cat = $comp . '/' . $area;
            $matched = true;
        } else {
            foreach (array_keys($componentcategories) as $catkey) {
                if ($comp === $catkey) {
                    $cat = $catkey;
                    $matched = true;
                    break;
                }
            }
        }
        if (!$matched) {
            $cat = '_other';
        }

        if (!isset($snapshot[$cat])) {
            $snapshot[$cat] = 0;
        }
        $snapshot[$cat] += $size;
    }

    foreach ($snapshot as $cat => $size) {
        if (!isset($componentseries[$cat])) {
            // Back-fill with zeros for all previous snapshots.
            $componentseries[$cat] = array_fill(0, $idx, 0);
        }
        $componentseries[$cat][] = $size;
    }

    // Fill zeros for categories not present in this snapshot.
    foreach ($componentseries as $cat => &$arr) {
        if (count($arr) <= $idx) {
            $arr[] = 0;
        }
    }
    unset($arr);
}

// Build Chart.js datasets from the series.
$componentcolors = [
    'backup' => '#dc3545',
    'assignsubmission_file' => '#fd7e14',
    'mod_resource' => '#28a745',
    'mod_folder' => '#20c997',
    'user/private' => '#007bff',
    'user/draft' => '#6610f2',
    'course' => '#17a2b8',
    '_other' => '#6c757d',
];

$componentdatasets = [];
// Sort by total size descending so the biggest category is at the bottom of the stack.
$categorytotals = [];
foreach ($componentseries as $cat => $sizes) {
    $categorytotals[$cat] = array_sum($sizes);
}
arsort($categorytotals);

foreach ($categorytotals as $cat => $total) {
    if ($total <= 0) {
        continue;
    }
    $label = isset($componentcategories[$cat])
        ? $componentcategories[$cat]
        : get_string('timeline:otherfiles', 'local_storage360');
    $color = $componentcolors[$cat] ?? '#' . substr(md5($cat), 0, 6);
    $componentdatasets[] = [
        'label' => $label,
        'data' => $componentseries[$cat],
        'backgroundColor' => $color . '66',
        'borderColor' => $color,
        'fill' => true,
        'tension' => 0.3,
    ];
}

$PAGE->requires->js_amd_inline("
require(['core/chartjs'], function(ChartModule) {
    var Chart = ChartModule.default || ChartModule;

    var formatBytes = function(bytes) {
        var sign = bytes < 0 ? '-' : '';
        var abs = Math.abs(bytes);
        if (abs >= 1073741824) return sign + (abs / 1073741824).toFixed(2) + ' GB';
        if (abs >= 1048576) return sign + (abs / 1048576).toFixed(2) + ' MB';
        if (abs >= 1024) return sign + (abs / 1024).toFixed(2) + ' KB';
        return sign + abs + ' B';
    };

    var tickFormatter = function(value) {
        var sign = value < 0 ? '-' : '';
        var abs = Math.abs(value);
        if (abs >= 1073741824) return sign + (abs / 1073741824).toFixed(1) + ' GB';
        if (abs >= 1048576) return sign + (abs / 1048576).toFixed(0) + ' MB';
        if (abs >= 1024) return sign + (abs / 1024).toFixed(0) + ' KB';
        return value;
    };

    var compactOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: function(ctx) { return formatBytes(ctx.raw); } } }
        },
        scales: {
            x: { ticks: { maxRotation: 45, font: { size: 10 } } },
            y: { beginAtZero: true, ticks: { callback: tickFormatter, font: { size: 10 } } }
        }
    };

    // History chart.
    var histCtx = document.getElementById('historyChart');
    if (histCtx) {
        new Chart(histCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: " . json_encode($historylabels) . ",
                datasets: [{
                    label: 'Total storage',
                    data: " . json_encode($historysizes) . ",
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2
                }]
            },
            options: compactOptions
        });
    }

    // By-component stacked area chart.
    var compCtx = document.getElementById('componentHistoryChart');
    if (compCtx) {
        new Chart(compCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: " . json_encode($componenthistorylabels) . ",
                datasets: " . json_encode($componentdatasets) . "
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.dataset.label + ': ' + formatBytes(ctx.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: { stacked: true, ticks: { maxRotation: 45, font: { size: 10 } } },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { callback: tickFormatter, font: { size: 10 } }
                    }
                }
            }
        });
    }

    // Monthly additions bar chart.
    var monthCtx = document.getElementById('monthlyChart');
    if (monthCtx) {
        var monthlyData = " . json_encode($monthlysizes) . ";
        var barColors = monthlyData.map(function(v) { return v >= 0 ? '#28a745' : '#dc3545'; });
        new Chart(monthCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: " . json_encode($monthlylabels) . ",
                datasets: [{
                    label: " . json_encode(get_string('timeline:monthlyadditions', 'local_storage360')) . ",
                    data: monthlyData,
                    backgroundColor: barColors
                }]
            },
            options: compactOptions
        });
    }

    // Cumulative line chart.
    var cumCtx = document.getElementById('cumulativeChart');
    if (cumCtx) {
        new Chart(cumCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: " . json_encode($monthlylabels) . ",
                datasets: [{
                    label: 'Cumulative storage',
                    data: " . json_encode($cumulativesizes) . ",
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23,162,184,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2
                }]
            },
            options: compactOptions
        });
    }
});
");

echo $OUTPUT->footer();
