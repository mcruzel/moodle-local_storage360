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
 * Individual files browser page.
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
$sort = optional_param('sort', 'filesize', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$component = optional_param('component', '', PARAM_COMPONENT);
$filearea = optional_param('filearea', '', PARAM_AREA);
$search = optional_param('search', '', PARAM_TEXT);
$mimetype = optional_param('mimetype', '', PARAM_TEXT);
$exportcsv = optional_param('exportcsv', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Check delete capability once (used for showing buttons and handling action).
$candelete = has_capability('local/storage360:deletefiles', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/files.php'));
$PAGE->set_title(get_string('files:title', 'local_storage360'));
$PAGE->set_heading(get_string('files:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

// Handle single-file delete action.
if ($action === 'delete' && $candelete && confirm_sesskey()) {
    $fileids = optional_param_array('fileids', [], PARAM_INT);
    if (!empty($fileids)) {
        $fs = get_file_storage();
        $deletedcount = 0;
        foreach ($fileids as $fileid) {
            $file = $fs->get_file_by_id($fileid);
            if (!$file || $file->get_filename() === '.') {
                continue;
            }
            $filename = $file->get_filename();
            $filesize = $file->get_filesize();
            $filecomponent = $file->get_component();

            // Log to audit table before deletion.
            local_storage360_log_deletion($file, 'files_browser');

            if ($file->delete()) {
                $deletedcount++;
                $event = \local_storage360\event\file_deleted::create([
                    'objectid' => $fileid,
                    'context' => $context,
                    'other' => [
                        'filename' => $filename,
                        'filesize' => $filesize,
                        'component' => $filecomponent,
                    ],
                ]);
                $event->trigger();
            }
        }
        \cache::make('local_storage360', 'analysis')->purge();
        set_config('last_deletion_time', time(), 'local_storage360');

        // Redirect back preserving current filters.
        $redirectparams = [
            'courseid' => $courseid, 'userid' => $userid,
            'component' => $component, 'filearea' => $filearea,
            'search' => $search, 'mimetype' => $mimetype,
            'sort' => $sort, 'dir' => $dir, 'page' => $page,
        ];
        redirect(
            new moodle_url('/local/storage360/pages/files.php', $redirectparams),
            get_string('cleanup:deleted', 'local_storage360', $deletedcount),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$calculator = new \local_storage360\analytics\storage_calculator();
$result = $calculator->get_files($page, $perpage, $sort, $dir, $courseid, $userid, $component, $filearea, $search, $mimetype);

// Batch-load integrity scan status for all contenthashes in current page.
$pagehashes = array_unique(array_filter(array_column($result->records, 'contenthash')));
$scanstatuses = $calculator->get_scan_status_for_hashes($pagehashes);
$hasscandata = !empty($scanstatuses);

// CSV export.
if ($exportcsv) {
    require_sesskey();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="storage360_files_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Filename', 'Size (bytes)', 'MIME type', 'Component', 'File area',
                    'Course', 'User', 'Created']);
    foreach ($result->records as $record) {
        fputcsv($out, [
            $record->filename,
            $record->filesize,
            $record->mimetype,
            $record->component,
            $record->filearea,
            $record->coursename,
            $record->userfullname,
            date('Y-m-d H:i', $record->timecreated),
        ]);
    }
    fclose($out);
    exit;
}

echo $OUTPUT->header();

$tabs = local_storage360_get_tabs('files');
print_tabs([$tabs], 'files');

// Context info banner when filtering.
$filterdesc = [];
if ($courseid > 0) {
    $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
    if ($coursename) {
        $filterdesc[] = get_string('files:filtercourse', 'local_storage360', s($coursename));
    }
}
if ($userid > 0) {
    $namefields = implode(',', array_merge(['id'], \core_user\fields::for_name()->get_required_fields()));
    $user = $DB->get_record('user', ['id' => $userid], $namefields, IGNORE_MISSING);
    if ($user) {
        $filterdesc[] = get_string('files:filteruser', 'local_storage360', fullname($user));
    }
}
if ($component !== '') {
    $label = $component . ($filearea !== '' ? '/' . $filearea : '');
    $filterdesc[] = get_string('files:filtercomponent', 'local_storage360', s($label));
}
if (!empty($filterdesc)) {
    echo $OUTPUT->notification(implode(' &mdash; ', $filterdesc), 'info');
}

// Filters form.
$baseurl = new moodle_url('/local/storage360/pages/files.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-4']);

// Preserve context filters as hidden fields.
if ($courseid > 0) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
}
if ($userid > 0) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
}

echo html_writer::start_div('row align-items-end');

// Filename search.
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', get_string('files:search', 'local_storage360'), ['for' => 'search']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'id' => 'search',
    'value' => $search, 'class' => 'form-control', 'placeholder' => get_string('files:searchplaceholder', 'local_storage360')]);
echo html_writer::end_div();

// Component filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('components:component', 'local_storage360'), ['for' => 'component']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'component', 'id' => 'component',
    'value' => $component, 'class' => 'form-control', 'placeholder' => 'mod_resource...']);
echo html_writer::end_div();

// MIME type filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('files:mimetype', 'local_storage360'), ['for' => 'mimetype']);
$mimeoptions = [
    '' => get_string('all', 'local_storage360'),
    'application/pdf' => 'PDF',
    'image/' => get_string('files:images', 'local_storage360'),
    'video/' => get_string('files:videos', 'local_storage360'),
    'audio/' => get_string('files:audio', 'local_storage360'),
    'application/zip' => 'ZIP/Archive',
    'application/vnd' => 'Office',
];
echo html_writer::select($mimeoptions, 'mimetype', $mimetype, null, ['class' => 'form-control', 'id' => 'mimetype']);
echo html_writer::end_div();

// Buttons.
echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter', 'local_storage360'),
    'class' => 'btn btn-primary me-2']);

$resetparams = [];
if ($courseid > 0) {
    $resetparams['courseid'] = $courseid;
}
if ($userid > 0) {
    $resetparams['userid'] = $userid;
}
$reseturl = new moodle_url('/local/storage360/pages/files.php', $resetparams);
echo html_writer::link($reseturl, get_string('reset', 'local_storage360'), ['class' => 'btn btn-secondary me-2']);

$csvparams = [
    'exportcsv' => 1, 'sesskey' => sesskey(), 'courseid' => $courseid, 'userid' => $userid,
    'component' => $component, 'filearea' => $filearea, 'search' => $search,
    'mimetype' => $mimetype, 'sort' => $sort, 'dir' => $dir,
];
$csvurl = new moodle_url('/local/storage360/pages/files.php', $csvparams);
echo html_writer::link($csvurl, get_string('exportcsv', 'local_storage360'), ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();

echo html_writer::end_div(); // End row.
echo html_writer::end_tag('form');

// Total info.
echo html_writer::tag('p',
    get_string('files:totalresults', 'local_storage360', number_format($result->totalcount)),
    ['class' => 'text-muted mb-2']);

// Results table.
if (empty($result->records)) {
    echo html_writer::tag('p', get_string('nodata', 'local_storage360'), ['class' => 'text-muted']);
} else {
    $sorturl = function ($field) use ($baseurl, $sort, $dir, $courseid, $userid, $component, $filearea, $search, $mimetype) {
        $newdir = ($sort === $field && $dir === 'DESC') ? 'ASC' : 'DESC';
        return new moodle_url($baseurl, [
            'sort' => $field, 'dir' => $newdir,
            'courseid' => $courseid, 'userid' => $userid,
            'component' => $component, 'filearea' => $filearea,
            'search' => $search, 'mimetype' => $mimetype,
        ]);
    };
    $sorticon = function ($field) use ($sort, $dir) {
        return $sort === $field ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    };

    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover table-sm';
    $tablehead = [
        html_writer::link($sorturl('filename'),
            get_string('files:filename', 'local_storage360') . $sorticon('filename')),
        html_writer::link($sorturl('filesize'),
            get_string('files:size', 'local_storage360') . $sorticon('filesize')),
        html_writer::link($sorturl('mimetype'),
            get_string('files:mimetype', 'local_storage360') . $sorticon('mimetype')),
        html_writer::link($sorturl('component'),
            get_string('components:component', 'local_storage360') . $sorticon('component')),
        get_string('components:filearea', 'local_storage360'),
        get_string('files:course', 'local_storage360'),
        get_string('files:user', 'local_storage360'),
        html_writer::link($sorturl('timecreated'),
            get_string('files:created', 'local_storage360') . $sorticon('timecreated')),
        get_string('scan:disk_column', 'local_storage360'),
    ];
    if ($candelete) {
        $tablehead[] = '';
    }
    $table->head = $tablehead;

    foreach ($result->records as $record) {
        // Course/activity link.
        $coursetd = '-';
        if ($record->component === 'backup' && $record->resolvedcourseid > 0) {
            // Backup: link to Storage 360 backups page for this course.
            $linkurl = new moodle_url('/local/storage360/pages/backups.php', ['search' => $record->resolvedcourseid]);
            $coursetd = html_writer::link($linkurl, s($record->coursename));
        } else if (!empty($record->modtype) && $record->contextinstanceid > 0) {
            // Module-level file: link directly to the activity.
            $linkurl = new moodle_url('/mod/' . $record->modtype . '/view.php', ['id' => $record->contextinstanceid]);
            $coursetd = html_writer::link($linkurl, s($record->coursename), ['title' => s($record->modtype)]);
        } else if ($record->resolvedcourseid > 0) {
            $linkurl = new moodle_url('/course/view.php', ['id' => $record->resolvedcourseid]);
            $coursetd = html_writer::link($linkurl, s($record->coursename));
        }

        // User link.
        $usertd = '-';
        if (!empty($record->userid)) {
            $userlink = new moodle_url('/user/profile.php', ['id' => $record->userid]);
            $usertd = html_writer::link($userlink, s($record->userfullname));
        }

        // MIME type badge.
        $mimetd = s($record->mimetype ?: '-');

        $sizeformatted = local_storage360_format_size((int) $record->filesize);

        // Disk integrity status badge.
        if ($hasscandata && isset($scanstatuses[$record->contenthash])) {
            $scanstatus = $scanstatuses[$record->contenthash];
            switch ($scanstatus) {
                case 0:
                    $diskbadge = html_writer::tag('span', '&#10003;',
                        ['class' => 'badge text-bg-success',
                         'title' => get_string('scan:status_ok', 'local_storage360')]);
                    break;
                case 1:
                    $diskbadge = html_writer::tag('span', '&#10007;',
                        ['class' => 'badge text-bg-danger',
                         'title' => get_string('scan:status_missing', 'local_storage360')]);
                    break;
                case 2:
                    $diskbadge = html_writer::tag('span', '&#9888;',
                        ['class' => 'badge text-bg-warning',
                         'title' => get_string('scan:status_mismatch', 'local_storage360')]);
                    break;
                case 3:
                    $diskbadge = html_writer::tag('span', '&#128465;',
                        ['class' => 'badge text-bg-secondary',
                         'title' => get_string('scan:status_intrash', 'local_storage360')]);
                    break;
                default:
                    $diskbadge = html_writer::tag('span', '?',
                        ['class' => 'badge text-bg-light',
                         'title' => get_string('scan:status_pending', 'local_storage360')]);
            }
        } else {
            $diskbadge = html_writer::tag('span', '?',
                ['class' => 'badge text-bg-light',
                 'title' => get_string('scan:status_pending', 'local_storage360')]);
        }

        $row = [
            html_writer::tag('span', s($record->filename), ['title' => s($record->filepath . $record->filename)]),
            $sizeformatted,
            $mimetd,
            s($record->component),
            s($record->filearea),
            $coursetd,
            $usertd,
            userdate($record->timecreated, '%Y-%m-%d %H:%M'),
            $diskbadge,
        ];
        if ($candelete) {
            $row[] = html_writer::tag('button',
                get_string('cleanup:deleteone', 'local_storage360'),
                ['type' => 'button', 'class' => 'btn btn-sm btn-danger storage360-delete-one',
                 'data-fileid' => $record->id,
                 'data-filename' => s($record->filename),
                 'data-filesize' => $sizeformatted]);
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);

    // Pagination.
    $pagingparams = [
        'sort' => $sort, 'dir' => $dir,
        'courseid' => $courseid, 'userid' => $userid,
        'component' => $component, 'filearea' => $filearea,
        'search' => $search, 'mimetype' => $mimetype,
    ];
    $pagingurl = new moodle_url($baseurl, $pagingparams);
    echo $OUTPUT->paging_bar($result->totalcount, $page, $perpage, $pagingurl);
}

// Bootstrap modal for single-file delete confirmation.
if ($candelete) {
    $deleteaction = new moodle_url('/local/storage360/pages/files.php', [
        'action' => 'delete',
        'courseid' => $courseid, 'userid' => $userid,
        'component' => $component, 'filearea' => $filearea,
        'search' => $search, 'mimetype' => $mimetype,
        'sort' => $sort, 'dir' => $dir, 'page' => $page,
    ]);
    echo '
<div class="modal fade" id="storage360-delete-modal" tabindex="-1" role="dialog" aria-labelledby="storage360-delete-modal-label" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="storage360-delete-modal-label">' .
            get_string('cleanup:confirmdeletetitle', 'local_storage360') . '</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . get_string('cancel') . '"></button>
      </div>
      <div class="modal-body">
        <p>' . get_string('cleanup:confirmdeletebody', 'local_storage360') . '</p>
        <p><strong id="storage360-delete-filename"></strong> (<span id="storage360-delete-filesize"></span>)</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . get_string('cancel') . '</button>
        <form method="post" action="' . $deleteaction->out(true) . '" style="display:inline">
          <input type="hidden" name="sesskey" value="' . sesskey() . '">
          <input type="hidden" name="fileids[]" id="storage360-delete-fileid" value="">
          <button type="submit" class="btn btn-danger">' .
              get_string('cleanup:deleteone', 'local_storage360') . '</button>
        </form>
      </div>
    </div>
  </div>
</div>';
    $PAGE->requires->js_call_amd('local_storage360/init', 'init');
}

echo $OUTPUT->footer();
