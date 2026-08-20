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
 * Deletion history page.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/storage360/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/storage360:deletefiles', $context);

$page = optional_param('page', 0, PARAM_INT);
$perpage = min(optional_param('perpage', 50, PARAM_INT), 500);
$sort = optional_param('sort', 'timedeleted', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$source = optional_param('source', '', PARAM_ALPHANUMEXT);
$exportcsv = optional_param('exportcsv', 0, PARAM_INT);
$verify = optional_param('verify', 0, PARAM_INT);
$emptytrash = optional_param('emptytrash', 0, PARAM_INT);
$showcleanresult = optional_param('showcleanresult', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/deletelog.php'));
$PAGE->set_title(get_string('deletelog:title', 'local_storage360'));
$PAGE->set_heading(get_string('deletelog:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

// Clean trash files tracked in our deletion log.
if ($emptytrash) {
    require_sesskey();
    $cleanresult = local_storage360_cleanup_trash_files();

    // Store results in session so we can display them after redirect.
    $SESSION->storage360_cleanresult = $cleanresult;

    $redirecturl = new moodle_url('/local/storage360/pages/deletelog.php', [
        'verify' => 1, 'search' => $search, 'source' => $source,
        'sort' => $sort, 'dir' => $dir, 'page' => $page,
        'showcleanresult' => 1,
    ]);
    redirect($redirecturl);
}

// Build query.
$allowedsorts = ['timedeleted', 'filename', 'filesize', 'component', 'deletedby'];
if (!in_array($sort, $allowedsorts)) {
    $sort = 'timedeleted';
}
$dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

$where = '1 = 1';
$params = [];

if ($search !== '') {
    if (ctype_digit($search)) {
        $where .= ' AND (d.fileid = :searchid OR d.courseid = :searchcid)';
        $params['searchid'] = (int) $search;
        $params['searchcid'] = (int) $search;
    } else {
        $searchlike = '%' . $DB->sql_like_escape($search) . '%';
        $where .= ' AND (d.filename LIKE :searchname OR d.coursename LIKE :searchcourse
                      OR d.ownerfullname LIKE :searchowner)';
        $params['searchname'] = $searchlike;
        $params['searchcourse'] = $searchlike;
        $params['searchowner'] = $searchlike;
    }
}

if ($source !== '') {
    $where .= ' AND d.source = :source';
    $params['source'] = $source;
}

$sql = "SELECT d.*,
               del.firstname AS del_firstname,
               del.lastname AS del_lastname
          FROM {local_storage360_deletelog} d
     LEFT JOIN {user} del ON del.id = d.deletedby
         WHERE {$where}
         ORDER BY d.{$sort} {$dir}";

$countsql = "SELECT COUNT(*) FROM {local_storage360_deletelog} d WHERE {$where}";
$totalcount = $DB->count_records_sql($countsql, $params);

// CSV export.
if ($exportcsv) {
    require_sesskey();
    // Stream CSV using recordset to avoid loading all rows into memory.
    $records = $DB->get_recordset_sql($sql, $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="storage360_deletelog_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Filename', 'Size (bytes)', 'Component', 'File area',
                    'Course', 'File owner', 'Deleted by', 'Source']);
    foreach ($records as $record) {
        $deletedbyname = trim(($record->del_firstname ?? '') . ' ' . ($record->del_lastname ?? ''));
        fputcsv($out, [
            date('Y-m-d H:i', $record->timedeleted),
            $record->filename,
            $record->filesize,
            $record->component,
            $record->filearea,
            $record->coursename ?: '-',
            $record->ownerfullname ?: '-',
            $deletedbyname ?: $record->deletedby,
            $record->source,
        ]);
    }
    $records->close();
    fclose($out);
    exit;
}

$records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

echo $OUTPUT->header();

$tabs = local_storage360_get_tabs('deletelog');
print_tabs([$tabs], 'deletelog');

// Display cleanup feedback if available.
if ($showcleanresult && !empty($SESSION->storage360_cleanresult)) {
    $cr = $SESSION->storage360_cleanresult;
    unset($SESSION->storage360_cleanresult);

    $cleanedcount = count($cr->cleaned);
    $skippedcount = count($cr->skipped_referenced);
    $alreadycount = count($cr->already_removed);
    $failedcount = count($cr->failed);

    echo html_writer::start_div('card mb-4 border-info');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', get_string('deletelog:cleanresult_title', 'local_storage360'),
        ['class' => 'card-title']);

    // Summary line.
    echo html_writer::tag('p', get_string('deletelog:cleanresult_summary', 'local_storage360', (object) [
        'cleaned' => $cleanedcount,
        'freed' => local_storage360_format_size((int) $cr->total_bytes_freed),
        'skipped' => $skippedcount,
        'already' => $alreadycount,
        'failed' => $failedcount,
    ]));

    // Details per category.
    if ($cleanedcount > 0) {
        echo html_writer::tag('span',
            get_string('deletelog:cleanresult_cleaned', 'local_storage360', $cleanedcount),
            ['class' => 'badge badge-success mr-2 mb-1']);
    }
    if ($skippedcount > 0) {
        echo html_writer::tag('span',
            get_string('deletelog:cleanresult_skipped', 'local_storage360', $skippedcount),
            ['class' => 'badge badge-info mr-2 mb-1']);
    }
    if ($alreadycount > 0) {
        echo html_writer::tag('span',
            get_string('deletelog:cleanresult_already', 'local_storage360', $alreadycount),
            ['class' => 'badge badge-secondary mr-2 mb-1']);
    }
    if ($failedcount > 0) {
        echo html_writer::tag('span',
            get_string('deletelog:cleanresult_failed', 'local_storage360', $failedcount),
            ['class' => 'badge badge-danger mr-2 mb-1']);
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Summary stats.
$totaldeleted = $DB->count_records('local_storage360_deletelog');
$totalsizeraw = $DB->get_field_sql(
    "SELECT COALESCE(SUM(filesize), 0) FROM {local_storage360_deletelog}"
);
echo html_writer::tag('p',
    get_string('deletelog:summary', 'local_storage360', (object) [
        'count' => number_format($totaldeleted),
        'size' => local_storage360_format_size((int) $totalsizeraw),
    ]),
    ['class' => 'text-muted mb-2']);

// Filters form.
$baseurl = new moodle_url('/local/storage360/pages/deletelog.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-4']);
echo html_writer::start_div('row align-items-end');

// Search.
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', get_string('deletelog:search', 'local_storage360'), ['for' => 'search']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'id' => 'search',
    'value' => $search, 'class' => 'form-control',
    'placeholder' => get_string('deletelog:searchplaceholder', 'local_storage360')]);
echo html_writer::end_div();

// Source filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('deletelog:source', 'local_storage360'), ['for' => 'source']);
$sourceoptions = [
    '' => get_string('all', 'local_storage360'),
    'cleanup_backups' => get_string('deletelog:source_backups', 'local_storage360'),
    'cleanup_drafts' => get_string('deletelog:source_drafts', 'local_storage360'),
    'files_browser' => get_string('deletelog:source_files', 'local_storage360'),
];
echo html_writer::select($sourceoptions, 'source', $source, null, ['class' => 'form-control', 'id' => 'source']);
echo html_writer::end_div();

// Buttons.
echo html_writer::start_div('col-md-4');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter', 'local_storage360'),
    'class' => 'btn btn-primary mr-2']);
$reseturl = new moodle_url('/local/storage360/pages/deletelog.php');
echo html_writer::link($reseturl, get_string('reset', 'local_storage360'), ['class' => 'btn btn-secondary mr-2']);
$csvurl = new moodle_url('/local/storage360/pages/deletelog.php', [
    'exportcsv' => 1, 'sesskey' => sesskey(), 'search' => $search, 'source' => $source, 'sort' => $sort, 'dir' => $dir,
]);
echo html_writer::link($csvurl, get_string('exportcsv', 'local_storage360'), ['class' => 'btn btn-outline-secondary mr-2']);
$verifyurl = new moodle_url('/local/storage360/pages/deletelog.php', [
    'verify' => 1, 'search' => $search, 'source' => $source, 'sort' => $sort, 'dir' => $dir, 'page' => $page,
]);
echo html_writer::link($verifyurl, get_string('deletelog:verifydisk', 'local_storage360'),
    ['class' => 'btn btn-outline-warning mr-2']);
$trashurl = new moodle_url('/local/storage360/pages/deletelog.php', [
    'emptytrash' => 1, 'sesskey' => sesskey(),
    'search' => $search, 'source' => $source, 'sort' => $sort, 'dir' => $dir, 'page' => $page,
]);
echo html_writer::link($trashurl, get_string('deletelog:emptytrash', 'local_storage360'),
    ['class' => 'btn btn-outline-danger',
     'onclick' => 'return confirm(' . json_encode(get_string('deletelog:emptytrashconfirm', 'local_storage360')) . ');']);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Results.
echo html_writer::tag('p',
    get_string('files:totalresults', 'local_storage360', number_format($totalcount)),
    ['class' => 'text-muted mb-2']);

if (empty($records)) {
    echo html_writer::tag('p', get_string('nodata', 'local_storage360'), ['class' => 'text-muted']);
} else {
    $sorturl = function ($field) use ($baseurl, $sort, $dir, $search, $source) {
        $newdir = ($sort === $field && $dir === 'DESC') ? 'ASC' : 'DESC';
        return new moodle_url($baseurl, [
            'sort' => $field, 'dir' => $newdir, 'search' => $search, 'source' => $source,
        ]);
    };
    $sorticon = function ($field) use ($sort, $dir) {
        return $sort === $field ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    };

    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover table-sm';
    $table->head = [
        html_writer::link($sorturl('timedeleted'),
            get_string('deletelog:date', 'local_storage360') . $sorticon('timedeleted')),
        html_writer::link($sorturl('filename'),
            get_string('files:filename', 'local_storage360') . $sorticon('filename')),
        html_writer::link($sorturl('filesize'),
            get_string('files:size', 'local_storage360') . $sorticon('filesize')),
        html_writer::link($sorturl('component'),
            get_string('components:component', 'local_storage360') . $sorticon('component')),
        get_string('files:course', 'local_storage360'),
        get_string('deletelog:owner', 'local_storage360'),
        html_writer::link($sorturl('deletedby'),
            get_string('deletelog:deletedby', 'local_storage360') . $sorticon('deletedby')),
        get_string('deletelog:source', 'local_storage360'),
    ];
    if ($verify) {
        $table->head[] = get_string('deletelog:diskstatus', 'local_storage360');
    }

    foreach ($records as $record) {
        // Deleted-by user.
        $deletedbyname = trim(($record->del_firstname ?? '') . ' ' . ($record->del_lastname ?? ''));
        $deletedbytd = $deletedbyname
            ? html_writer::link(new moodle_url('/user/profile.php', ['id' => $record->deletedby]),
                s($deletedbyname))
            : $record->deletedby;

        // Owner.
        $ownertd = '-';
        if (!empty($record->owneruserid)) {
            $ownertd = html_writer::link(
                new moodle_url('/user/profile.php', ['id' => $record->owneruserid]),
                s($record->ownerfullname ?: $record->owneruserid)
            );
        }

        // Course.
        $coursetd = '-';
        if (!empty($record->courseid)) {
            $coursetd = html_writer::link(
                new moodle_url('/course/view.php', ['id' => $record->courseid]),
                s($record->coursename ?: $record->courseid)
            );
        }

        // Source badge.
        $sourcelabel = $sourceoptions[$record->source] ?? $record->source;
        $badgeclass = 'badge badge-secondary';
        if ($record->source === 'cleanup_backups') {
            $badgeclass = 'badge badge-warning';
        } else if ($record->source === 'cleanup_drafts') {
            $badgeclass = 'badge badge-info';
        } else if ($record->source === 'files_browser') {
            $badgeclass = 'badge badge-danger';
        }

        $row = new html_table_row();

        $cells = [
            userdate($record->timedeleted, '%Y-%m-%d %H:%M'),
            html_writer::tag('span', s($record->filename),
                ['title' => s($record->filepath . $record->filename)]),
            local_storage360_format_size((int) $record->filesize),
            s($record->component) . '/' . s($record->filearea),
            $coursetd,
            $ownertd,
            $deletedbytd,
            html_writer::tag('span', $sourcelabel, ['class' => $badgeclass]),
        ];

        $isremoved = false;
        if ($verify) {
            if (!empty($record->contenthash)) {
                $diskcheck = local_storage360_check_file_on_disk($record->contenthash);
                if ($diskcheck->status === 'removed') {
                    $isremoved = true;
                    $cells[] = html_writer::tag('span',
                        get_string('deletelog:status_removed', 'local_storage360'),
                        ['class' => 'badge badge-success']);
                } else if ($diskcheck->status === 'trash') {
                    $cells[] = html_writer::tag('span',
                        get_string('deletelog:status_trash', 'local_storage360'),
                        ['class' => 'badge badge-warning']);
                } else if ($diskcheck->status === 'referenced') {
                    $cells[] = html_writer::tag('span',
                        get_string('deletelog:status_referenced', 'local_storage360', $diskcheck->db_references),
                        ['class' => 'badge badge-info']);
                } else {
                    $cells[] = html_writer::tag('span',
                        get_string('deletelog:status_orphan', 'local_storage360'),
                        ['class' => 'badge badge-danger']);
                }
            } else {
                $cells[] = html_writer::tag('span', 'N/A', ['class' => 'badge badge-secondary']);
            }
        }

        $row->cells = $cells;
        if ($isremoved) {
            $row->style = 'opacity: 0.45;';
        }

        $table->data[] = $row;
    }
    echo html_writer::table($table);

    // Pagination.
    $pagingurl = new moodle_url($baseurl, [
        'sort' => $sort, 'dir' => $dir, 'search' => $search, 'source' => $source,
    ]);
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $pagingurl);
}

echo $OUTPUT->footer();
