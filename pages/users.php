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
 * Storage by user page.
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
$minsize = optional_param('minsize', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$exportcsv = optional_param('exportcsv', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/users.php'));
$PAGE->set_title(get_string('users:title', 'local_storage360'));
$PAGE->set_heading(get_string('users:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

// Check if snapshots exist before running expensive queries.
$hassnapshots = $DB->get_field_sql('SELECT MAX(timecreated) FROM {local_storage360_history}');

if (!$hassnapshots) {
    echo $OUTPUT->header();
    $tabs = local_storage360_get_tabs('users');
    print_tabs([$tabs], 'users');
    echo $OUTPUT->notification(get_string('nosnapshots', 'local_storage360'), 'info');
    $collecturl = new moodle_url('/local/storage360/pages/collect.php', ['sesskey' => sesskey()]);
    echo html_writer::link($collecturl, get_string('collectnow', 'local_storage360'),
        ['class' => 'btn btn-primary']);
    echo $OUTPUT->footer();
    die();
}

$calculator = new \local_storage360\analytics\storage_calculator();
$result = $calculator->get_users_storage($page, $perpage, $sort, $dir, $minsize, $search);

// CSV export.
if ($exportcsv) {
    require_sesskey();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="storage360_users_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Firstname', 'Lastname', 'Email', 'Private (bytes)', 'Drafts (bytes)',
                    'Assignments (bytes)', 'Total (bytes)', 'Last upload']);
    foreach ($result->records as $record) {
        fputcsv($out, [
            $record->firstname, $record->lastname, $record->email,
            $record->private_files, $record->draft_files, $record->assignment_files,
            $record->total_size,
            $record->last_upload ? date('Y-m-d H:i', $record->last_upload) : '',
        ]);
    }
    fclose($out);
    exit;
}

echo $OUTPUT->header();

$tabs = local_storage360_get_tabs('users');
print_tabs([$tabs], 'users');
echo local_storage360_render_snapshot_indicator();

// Filters.
$baseurl = new moodle_url('/local/storage360/pages/users.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-4']);
echo html_writer::start_div('row align-items-end');

// Search.
echo html_writer::start_div('col-md-4');
echo html_writer::tag('label', get_string('users:search', 'local_storage360'), ['for' => 'search']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'id' => 'search',
    'value' => $search, 'class' => 'form-control',
    'placeholder' => get_string('users:searchplaceholder', 'local_storage360')]);
echo html_writer::end_div();

// Min size.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', 'Min size', ['for' => 'minsize']);
$sizeoptions = [
    0 => get_string('all', 'local_storage360'),
    10485760 => get_string('minsize:10mb', 'local_storage360'),
    104857600 => get_string('minsize:100mb', 'local_storage360'),
    1073741824 => get_string('minsize:1gb', 'local_storage360'),
];
echo html_writer::select($sizeoptions, 'minsize', $minsize, null, ['class' => 'form-control', 'id' => 'minsize']);
echo html_writer::end_div();

// Buttons.
echo html_writer::start_div('col-md-4');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter', 'local_storage360'),
    'class' => 'btn btn-primary mr-2']);
$reseturl = new moodle_url('/local/storage360/pages/users.php');
echo html_writer::link($reseturl, get_string('reset', 'local_storage360'), ['class' => 'btn btn-secondary mr-2']);
$csvurl = new moodle_url('/local/storage360/pages/users.php', [
    'exportcsv' => 1, 'sesskey' => sesskey(), 'search' => $search, 'minsize' => $minsize, 'sort' => $sort, 'dir' => $dir,
]);
echo html_writer::link($csvurl, get_string('exportcsv', 'local_storage360'), ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Results table.
if (empty($result->records)) {
    echo html_writer::tag('p', get_string('nodata', 'local_storage360'), ['class' => 'text-muted']);
} else {
    $sorturl = function ($field) use ($baseurl, $sort, $dir, $minsize, $search) {
        $newdir = ($sort === $field && $dir === 'DESC') ? 'ASC' : 'DESC';
        return new moodle_url($baseurl, [
            'sort' => $field, 'dir' => $newdir, 'minsize' => $minsize, 'search' => $search,
        ]);
    };
    $sorticon = function ($field) use ($sort, $dir) {
        return $sort === $field ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    };

    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover';
    $table->head = [
        html_writer::link($sorturl('lastname'),
            get_string('users:username', 'local_storage360') . $sorticon('lastname')),
        get_string('users:email', 'local_storage360'),
        html_writer::link($sorturl('private_files'),
            get_string('users:privatefiles', 'local_storage360') . $sorticon('private_files')),
        html_writer::link($sorturl('draft_files'),
            get_string('users:draftfiles', 'local_storage360') . $sorticon('draft_files')),
        html_writer::link($sorturl('assignment_files'),
            get_string('users:assignmentfiles', 'local_storage360') . $sorticon('assignment_files')),
        html_writer::link($sorturl('total_size'),
            get_string('users:totalsize', 'local_storage360') . $sorticon('total_size')),
        html_writer::link($sorturl('last_upload'),
            get_string('users:lastupload', 'local_storage360') . $sorticon('last_upload')),
        '',
    ];

    foreach ($result->records as $record) {
        $profileurl = new moodle_url('/user/profile.php', ['id' => $record->id]);
        $moodlefilesurl = new moodle_url('/user/files.php', ['userid' => $record->id]);
        $storagefilesurl = new moodle_url('/local/storage360/pages/files.php', ['userid' => $record->id]);
        $lastupload = $record->last_upload ? userdate($record->last_upload) : '-';

        $actions = html_writer::link($storagefilesurl, get_string('files:viewfiles', 'local_storage360'),
                ['class' => 'btn btn-sm btn-outline-info mr-1']) .
            html_writer::link($moodlefilesurl, get_string('users:privatefiles', 'local_storage360'),
                ['class' => 'btn btn-sm btn-outline-primary']);

        $table->data[] = [
            html_writer::link($profileurl, fullname($record)),
            s($record->email),
            local_storage360_format_size((int) $record->private_files),
            local_storage360_format_size((int) $record->draft_files),
            local_storage360_format_size((int) $record->assignment_files),
            local_storage360_format_size((int) $record->total_size),
            $lastupload,
            $actions,
        ];
    }
    echo html_writer::table($table);

    $pagingurl = new moodle_url($baseurl, [
        'sort' => $sort, 'dir' => $dir, 'minsize' => $minsize, 'search' => $search,
    ]);
    echo $OUTPUT->paging_bar($result->totalcount, $page, $perpage, $pagingurl);
}

echo $OUTPUT->footer();
