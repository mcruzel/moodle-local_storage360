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
 * Storage by component page.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/storage360/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/storage360:viewdetails', $context);

$exportcsv = optional_param('exportcsv', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/components.php'));
$PAGE->set_title(get_string('components:title', 'local_storage360'));
$PAGE->set_heading(get_string('components:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

// Check if snapshots exist before running expensive queries.
$hassnapshots = $DB->get_field_sql('SELECT MAX(timecreated) FROM {local_storage360_history}');

if (!$hassnapshots) {
    echo $OUTPUT->header();
    $tabs = local_storage360_get_tabs('components');
    print_tabs([$tabs], 'components');
    echo $OUTPUT->notification(get_string('nosnapshots', 'local_storage360'), 'info');
    $collecturl = new moodle_url('/local/storage360/pages/collect.php', ['sesskey' => sesskey()]);
    echo html_writer::link($collecturl, get_string('collectnow', 'local_storage360'),
        ['class' => 'btn btn-primary']);
    echo $OUTPUT->footer();
    die();
}

$calculator = new \local_storage360\analytics\storage_calculator();
$components = $calculator->get_by_component();

// CSV export.
if ($exportcsv) {
    require_sesskey();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="storage360_components_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Component', 'File area', 'Files', 'Total size (bytes)', 'Avg size (bytes)',
                    'Max size (bytes)', 'Oldest', 'Newest']);
    foreach ($components as $comp) {
        fputcsv($out, [
            $comp->component, $comp->filearea, $comp->file_count, $comp->total_size,
            round($comp->avg_size), $comp->max_size,
            $comp->oldest_file ? date('Y-m-d', $comp->oldest_file) : '',
            $comp->newest_file ? date('Y-m-d', $comp->newest_file) : '',
        ]);
    }
    fclose($out);
    exit;
}

echo $OUTPUT->header();

$tabs = local_storage360_get_tabs('components');
print_tabs([$tabs], 'components');
echo local_storage360_render_snapshot_indicator();

// Export button.
$csvurl = new moodle_url('/local/storage360/pages/components.php', ['exportcsv' => 1, 'sesskey' => sesskey()]);
echo html_writer::tag('div',
    html_writer::link($csvurl, get_string('exportcsv', 'local_storage360'), ['class' => 'btn btn-outline-secondary']),
    ['class' => 'mb-3']
);

// Table.
if (empty($components)) {
    echo html_writer::tag('p', get_string('nodata', 'local_storage360'), ['class' => 'text-muted']);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover';
    $table->head = [
        get_string('components:component', 'local_storage360'),
        get_string('components:filearea', 'local_storage360'),
        get_string('components:filecount', 'local_storage360'),
        get_string('components:totalsize', 'local_storage360'),
        get_string('components:avgsize', 'local_storage360'),
        get_string('components:maxsize', 'local_storage360'),
        get_string('components:oldest', 'local_storage360'),
        get_string('components:newest', 'local_storage360'),
        '',
    ];

    foreach ($components as $comp) {
        $filesurl = new moodle_url('/local/storage360/pages/files.php', [
            'component' => $comp->component,
            'filearea' => $comp->filearea,
        ]);
        $table->data[] = [
            s($comp->component),
            s($comp->filearea),
            number_format((int) $comp->file_count),
            local_storage360_format_size((int) $comp->total_size),
            local_storage360_format_size((int) round($comp->avg_size)),
            local_storage360_format_size((int) $comp->max_size),
            $comp->oldest_file ? userdate($comp->oldest_file, '%Y-%m-%d') : '-',
            $comp->newest_file ? userdate($comp->newest_file, '%Y-%m-%d') : '-',
            html_writer::link($filesurl, get_string('files:viewfiles', 'local_storage360'),
                ['class' => 'btn btn-sm btn-outline-info']),
        ];
    }
    echo html_writer::table($table);
}

// Bar chart (after table, reduced height).
echo html_writer::start_div('card mt-4 mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('dashboard:bycomponent', 'local_storage360'), ['class' => 'card-title']);
echo html_writer::start_div('', ['style' => 'height: 300px;']);
echo html_writer::tag('canvas', '', ['id' => 'componentBarChart']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Chart JS.
$chartlabels = [];
$chartsizes = [];
$top15 = array_slice($components, 0, 15);
foreach ($top15 as $comp) {
    $chartlabels[] = $comp->component . '/' . $comp->filearea;
    $chartsizes[] = (int) $comp->total_size;
}

$PAGE->requires->js_amd_inline("
require(['core/chartjs'], function(ChartModule) {
    var Chart = ChartModule.default || ChartModule;
    var ctx = document.getElementById('componentBarChart');
    if (!ctx) return;

    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: " . json_encode($chartlabels) . ",
            datasets: [{
                label: 'Size (bytes)',
                data: " . json_encode($chartsizes) . ",
                backgroundColor: '#007bff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var bytes = context.raw;
                            if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
                            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
                            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
                            return bytes + ' B';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        callback: function(value) {
                            if (value >= 1073741824) return (value / 1073741824).toFixed(1) + ' GB';
                            if (value >= 1048576) return (value / 1048576).toFixed(0) + ' MB';
                            return value;
                        }
                    }
                }
            }
        }
    });
});
");

echo $OUTPUT->footer();
