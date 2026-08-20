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
 * Storage by course page.
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

$page = optional_param('page', 0, PARAM_INT);
$perpage = min(optional_param('perpage', 50, PARAM_INT), 500);
$sort = optional_param('sort', 'total_size', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$minsize = optional_param('minsize', 0, PARAM_INT);
$visible = optional_param('visible', -1, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$exportcsv = optional_param('exportcsv', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/courses.php'));
$PAGE->set_title(get_string('courses:title', 'local_storage360'));
$PAGE->set_heading(get_string('courses:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

// Check if snapshots exist before running expensive queries.
$hassnapshots = $DB->get_field_sql('SELECT MAX(timecreated) FROM {local_storage360_history}');

if (!$hassnapshots) {
    echo $OUTPUT->header();
    $tabs = local_storage360_get_tabs('courses');
    print_tabs([$tabs], 'courses');
    echo $OUTPUT->notification(get_string('nosnapshots', 'local_storage360'), 'info');
    $collecturl = new moodle_url('/local/storage360/pages/collect.php', ['sesskey' => sesskey()]);
    echo html_writer::link($collecturl, get_string('collectnow', 'local_storage360'),
        ['class' => 'btn btn-primary']);
    echo $OUTPUT->footer();
    die();
}

$calculator = new \local_storage360\analytics\storage_calculator();
$result = $calculator->get_courses_storage($page, $perpage, $sort, $dir, $categoryid, $minsize, $visible, $search);

// CSV export.
if ($exportcsv) {
    require_sesskey();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="storage360_courses_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Course', 'Total Size (bytes)', 'Files', 'Backups (bytes)', 'Assignments (bytes)', 'Students']);
    foreach ($result->records as $record) {
        fputcsv($out, [
            $record->fullname,
            $record->total_size,
            $record->file_count,
            $record->backup_size,
            $record->assignment_size,
            $record->student_count,
        ]);
    }
    fclose($out);
    exit;
}

echo $OUTPUT->header();

$tabs = local_storage360_get_tabs('courses');
print_tabs([$tabs], 'courses');
echo local_storage360_render_snapshot_indicator();

// Filters form.
$baseurl = new moodle_url('/local/storage360/pages/courses.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-4']);
echo html_writer::start_div('row align-items-end');

// Search by ID or name.
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', get_string('courses:search', 'local_storage360'), ['for' => 'search']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'id' => 'search',
    'value' => $search, 'class' => 'form-control',
    'placeholder' => get_string('courses:searchplaceholder', 'local_storage360')]);
echo html_writer::end_div();

// Category filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('courses:category', 'local_storage360'), ['for' => 'categoryid']);
$categories = core_course_category::get_all();
$catoptions = [0 => get_string('all', 'local_storage360')];
foreach ($categories as $cat) {
    $catoptions[$cat->id] = $cat->get_formatted_name();
}
echo html_writer::select($catoptions, 'categoryid', $categoryid, null, ['class' => 'form-control', 'id' => 'categoryid']);
echo html_writer::end_div();

// Minimum size filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('courses:minsize', 'local_storage360'), ['for' => 'minsize']);
$sizeoptions = [
    0 => get_string('all', 'local_storage360'),
    10485760 => get_string('minsize:10mb', 'local_storage360'),
    104857600 => get_string('minsize:100mb', 'local_storage360'),
    1073741824 => get_string('minsize:1gb', 'local_storage360'),
];
echo html_writer::select($sizeoptions, 'minsize', $minsize, null, ['class' => 'form-control', 'id' => 'minsize']);
echo html_writer::end_div();

// Visible filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('courses:status', 'local_storage360'), ['for' => 'visible']);
$visoptions = [
    -1 => get_string('all', 'local_storage360'),
    1 => get_string('courses:visible', 'local_storage360'),
    0 => get_string('courses:hidden', 'local_storage360'),
];
echo html_writer::select($visoptions, 'visible', $visible, null, ['class' => 'form-control', 'id' => 'visible']);
echo html_writer::end_div();

// Buttons.
echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter', 'local_storage360'),
    'class' => 'btn btn-primary me-2']);
$reseturl = new moodle_url('/local/storage360/pages/courses.php');
echo html_writer::link($reseturl, get_string('reset', 'local_storage360'), ['class' => 'btn btn-secondary me-2']);
$csvurl = new moodle_url('/local/storage360/pages/courses.php', [
    'exportcsv' => 1, 'sesskey' => sesskey(), 'categoryid' => $categoryid, 'minsize' => $minsize, 'visible' => $visible,
    'search' => $search, 'sort' => $sort, 'dir' => $dir,
]);
echo html_writer::link($csvurl, get_string('exportcsv', 'local_storage360'), ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();

echo html_writer::end_div(); // End row.
echo html_writer::end_tag('form');

// Results table.
if (empty($result->records)) {
    echo html_writer::tag('p', get_string('nodata', 'local_storage360'), ['class' => 'text-muted']);
} else {
    // Sort header helper.
    $sorturl = function ($field) use ($baseurl, $sort, $dir, $categoryid, $minsize, $visible, $search) {
        $newdir = ($sort === $field && $dir === 'DESC') ? 'ASC' : 'DESC';
        return new moodle_url($baseurl, [
            'sort' => $field, 'dir' => $newdir,
            'categoryid' => $categoryid, 'minsize' => $minsize, 'visible' => $visible,
            'search' => $search,
        ]);
    };
    $sorticon = function ($field) use ($sort, $dir) {
        if ($sort !== $field) {
            return '';
        }
        return $dir === 'ASC' ? ' ▲' : ' ▼';
    };

    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover';
    $table->head = [
        html_writer::link($sorturl('fullname'),
            get_string('courses:coursename', 'local_storage360') . $sorticon('fullname')),
        html_writer::link($sorturl('total_size'),
            get_string('courses:totalsize', 'local_storage360') . $sorticon('total_size')),
        html_writer::link($sorturl('file_count'),
            get_string('courses:filecount', 'local_storage360') . $sorticon('file_count')),
        get_string('courses:backupsize', 'local_storage360'),
        get_string('courses:assignmentsize', 'local_storage360'),
        get_string('courses:studentcount', 'local_storage360'),
        get_string('courses:perpupil', 'local_storage360'),
        '',
    ];

    foreach ($result->records as $record) {
        $courseurl = new moodle_url('/local/storage360/pages/backups.php', ['search' => $record->id]);
        $perpupil = $record->student_count > 0
            ? local_storage360_format_size((int) ($record->total_size / $record->student_count))
            : '-';
        $manageurl = new moodle_url('/course/edit.php', ['id' => $record->id]);
        $filesurl = new moodle_url('/local/storage360/pages/files.php', ['courseid' => $record->id]);

        $actions = html_writer::link($filesurl, get_string('files:viewfiles', 'local_storage360'),
                ['class' => 'btn btn-sm btn-outline-info me-1']) .
            html_writer::link($manageurl, get_string('cleanup:managecourse', 'local_storage360'),
                ['class' => 'btn btn-sm btn-outline-primary']);

        $table->data[] = [
            html_writer::link($courseurl, format_string($record->fullname)) .
                ($record->visible ? '' : ' ' . html_writer::tag('span',
                    get_string('courses:hidden', 'local_storage360'),
                    ['class' => 'badge text-bg-secondary'])),
            local_storage360_format_size((int) $record->total_size),
            number_format((int) $record->file_count),
            local_storage360_format_size((int) $record->backup_size),
            local_storage360_format_size((int) $record->assignment_size),
            number_format($record->student_count),
            $perpupil,
            $actions,
        ];
    }
    echo html_writer::table($table);

    // Pagination.
    $pagingurl = new moodle_url($baseurl, [
        'sort' => $sort, 'dir' => $dir,
        'categoryid' => $categoryid, 'minsize' => $minsize, 'visible' => $visible,
        'search' => $search,
    ]);
    echo $OUTPUT->paging_bar($result->totalcount, $page, $perpage, $pagingurl);
}

echo $OUTPUT->footer();
