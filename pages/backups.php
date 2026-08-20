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
 * Backup management page - view and control scheduled backups per course.
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
$sort = optional_param('sort', 'backup_totalsize', PARAM_ALPHANUMEXT);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$action = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$exportcsv = optional_param('exportcsv', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/backups.php'));
$PAGE->set_title(get_string('backups:title', 'local_storage360'));
$PAGE->set_heading(get_string('backups:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

$calculator = new \local_storage360\analytics\storage_calculator();

// Handle AJAX request for backup files list (modal content).
if ($action === 'getfiles' && $courseid > 0) {
    require_sesskey();
    $files = $calculator->get_course_backup_files($courseid);
    $response = [];
    foreach ($files as $file) {
        $arealabel = '';
        switch ($file->filearea) {
            case 'automated':
                $arealabel = get_string('backups:filearea_automated', 'local_storage360');
                break;
            case 'course':
                $arealabel = get_string('backups:filearea_course', 'local_storage360');
                break;
            case 'activity':
                $arealabel = get_string('backups:filearea_activity', 'local_storage360');
                break;
            default:
                $arealabel = s($file->filearea);
        }
        $response[] = [
            'id' => (int) $file->id,
            'filename' => $file->filename,
            'filesize' => local_storage360_format_size((int) $file->filesize),
            'filesize_raw' => (int) $file->filesize,
            'filearea' => $arealabel,
            'timecreated' => userdate($file->timecreated),
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

// Handle POST actions (disable/enable/setdate).
if ($action !== '' && $courseid > 0 && confirm_sesskey()) {
    require_capability('local/storage360:deletefiles', $context);

    if ($action === 'disable') {
        if ($DB->record_exists('backup_courses', ['courseid' => $courseid])) {
            $DB->set_field('backup_courses', 'nextstarttime', 0, ['courseid' => $courseid]);
        }
        redirect(
            new moodle_url('/local/storage360/pages/backups.php', [
                'sort' => $sort, 'dir' => $dir, 'search' => $search, 'page' => $page,
            ]),
            get_string('backups:disabled_success', 'local_storage360'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($action === 'enable') {
        $nextstarttime = time() + 86400;
        if ($DB->record_exists('backup_courses', ['courseid' => $courseid])) {
            $DB->set_field('backup_courses', 'nextstarttime', $nextstarttime, ['courseid' => $courseid]);
        } else {
            $DB->insert_record('backup_courses', (object) [
                'courseid' => $courseid,
                'laststarttime' => 0,
                'lastendtime' => 0,
                'laststatus' => 0,
                'nextstarttime' => $nextstarttime,
            ]);
        }
        redirect(
            new moodle_url('/local/storage360/pages/backups.php', [
                'sort' => $sort, 'dir' => $dir, 'search' => $search, 'page' => $page,
            ]),
            get_string('backups:enabled_success', 'local_storage360'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($action === 'setdate') {
        $newdate = optional_param('newdate', '', PARAM_TEXT);
        if (!empty($newdate)) {
            $timestamp = strtotime($newdate);
            if ($timestamp && $timestamp > time()) {
                if ($DB->record_exists('backup_courses', ['courseid' => $courseid])) {
                    $DB->set_field('backup_courses', 'nextstarttime', $timestamp, ['courseid' => $courseid]);
                } else {
                    $DB->insert_record('backup_courses', (object) [
                        'courseid' => $courseid,
                        'laststarttime' => 0,
                        'lastendtime' => 0,
                        'laststatus' => 0,
                        'nextstarttime' => $timestamp,
                    ]);
                }
                redirect(
                    new moodle_url('/local/storage360/pages/backups.php', [
                        'sort' => $sort, 'dir' => $dir, 'search' => $search, 'page' => $page,
                    ]),
                    get_string('backups:datechanged', 'local_storage360'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
        }
        // Invalid date — fall through to normal display.
    }
}

// CSV export.
if ($exportcsv) {
    require_sesskey();
    $allresult = $calculator->get_courses_backups(0, 0, $sort, $dir, $search);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="storage360_backups_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        get_string('backups:course', 'local_storage360'),
        'ID',
        get_string('backups:count', 'local_storage360'),
        get_string('backups:totalsize', 'local_storage360') . ' (bytes)',
        get_string('backups:lastbackup', 'local_storage360'),
        get_string('backups:status', 'local_storage360'),
        get_string('backups:nextbackup', 'local_storage360'),
    ]);
    foreach ($allresult->records as $record) {
        $statuslabel = local_storage360_backup_status_label((int) ($record->laststatus ?? -1));
        $nextbackup = !empty($record->nextstarttime)
            ? userdate((int) $record->nextstarttime)
            : get_string('backups:disabled', 'local_storage360');
        fputcsv($out, [
            $record->fullname,
            $record->id,
            (int) $record->backup_count,
            (int) $record->backup_totalsize,
            !empty($record->laststarttime) ? userdate((int) $record->laststarttime) : '-',
            $statuslabel,
            $nextbackup,
        ]);
    }
    fclose($out);
    exit;
}

// Normal page display.
echo $OUTPUT->header();

$tabs = local_storage360_get_tabs('backups');
print_tabs([$tabs], 'backups');
echo local_storage360_render_snapshot_indicator();

// Warning banner.
echo $OUTPUT->notification(
    get_string('backups:warning', 'local_storage360'),
    'info'
);

// Refresh data button.
echo html_writer::start_div('mb-3');
echo html_writer::link(
    new moodle_url('/local/storage360/pages/collect.php', ['sesskey' => sesskey()]),
    get_string('collectnow', 'local_storage360'),
    ['class' => 'btn btn-outline-primary btn-sm']
);
echo html_writer::end_div();

// Search & export form.
$baseurl = new moodle_url('/local/storage360/pages/backups.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-4']);
echo html_writer::start_div('row align-items-end');

echo html_writer::start_div('col-md-4');
echo html_writer::tag('label', get_string('backups:search', 'local_storage360'), ['for' => 'search']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'name' => 'search', 'id' => 'search',
    'value' => $search, 'class' => 'form-control',
    'placeholder' => get_string('backups:searchplaceholder', 'local_storage360'),
]);
echo html_writer::end_div();

echo html_writer::start_div('col-md-4');
echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('filter', 'local_storage360'),
    'class' => 'btn btn-primary mr-2',
]);
echo html_writer::link(
    new moodle_url('/local/storage360/pages/backups.php'),
    get_string('reset', 'local_storage360'),
    ['class' => 'btn btn-secondary mr-2']
);
echo html_writer::link(
    new moodle_url('/local/storage360/pages/backups.php', [
        'exportcsv' => 1, 'sesskey' => sesskey(), 'sort' => $sort, 'dir' => $dir, 'search' => $search,
    ]),
    get_string('exportcsv', 'local_storage360'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Fetch data.
$result = $calculator->get_courses_backups($page, $perpage, $sort, $dir, $search);

if (empty($result->records)) {
    echo html_writer::tag('p', get_string('backups:nobackups', 'local_storage360'), ['class' => 'text-muted']);
} else {
    // Sortable column helpers.
    $sorturl = function ($field) use ($baseurl, $sort, $dir, $search, $page) {
        $newdir = ($sort === $field && $dir === 'DESC') ? 'ASC' : 'DESC';
        return new moodle_url($baseurl, [
            'sort' => $field, 'dir' => $newdir, 'search' => $search, 'page' => $page,
        ]);
    };
    $sorticon = function ($field) use ($sort, $dir) {
        return $sort !== $field ? '' : ($dir === 'ASC' ? ' ▲' : ' ▼');
    };

    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover';
    $table->head = [
        html_writer::link($sorturl('fullname'),
            get_string('backups:course', 'local_storage360') . $sorticon('fullname')),
        html_writer::link($sorturl('backup_count'),
            get_string('backups:count', 'local_storage360') . $sorticon('backup_count')),
        html_writer::link($sorturl('backup_totalsize'),
            get_string('backups:totalsize', 'local_storage360') . $sorticon('backup_totalsize')),
        html_writer::link($sorturl('laststarttime'),
            get_string('backups:lastbackup', 'local_storage360') . $sorticon('laststarttime')),
        get_string('backups:status', 'local_storage360'),
        html_writer::link($sorturl('nextstarttime'),
            get_string('backups:nextbackup', 'local_storage360') . $sorticon('nextstarttime')),
        'Actions',
    ];

    foreach ($result->records as $record) {
        $courselink = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $record->id]),
            s($record->fullname)
        );

        // Clickable badge for backup count — opens modal.
        $countbadge = html_writer::tag('a', (int) $record->backup_count, [
            'href' => '#',
            'class' => 'badge badge-info storage360-backup-count',
            'data-courseid' => $record->id,
            'data-coursename' => s($record->fullname),
            'title' => get_string('backups:viewfiles', 'local_storage360'),
        ]);

        $sizeformatted = local_storage360_format_size((int) $record->backup_totalsize);

        // Last backup date.
        $lastbackup = !empty($record->laststarttime)
            ? userdate((int) $record->laststarttime) : '-';

        // Status badge.
        $statushtml = local_storage360_backup_status_badge((int) ($record->laststatus ?? -1));

        // Next backup.
        if (!empty($record->nextstarttime) && (int) $record->nextstarttime > 0) {
            $nextbackup = userdate((int) $record->nextstarttime);
        } else {
            $nextbackup = html_writer::tag('span',
                get_string('backups:disabled', 'local_storage360'),
                ['class' => 'text-muted']);
        }

        // Action buttons.
        $actions = '';

        // View files link.
        $actions .= html_writer::link(
            new moodle_url('/local/storage360/pages/files.php', [
                'component' => 'backup', 'courseid' => $record->id,
            ]),
            get_string('backups:viewfiles', 'local_storage360'),
            ['class' => 'btn btn-sm btn-outline-info mr-1']
        );

        // Disable/Enable button (only for users with deletefiles capability).
        if (has_capability('local/storage360:deletefiles', $context)) {
            if (!empty($record->nextstarttime) && (int) $record->nextstarttime > 0) {
                $actions .= html_writer::link(
                    new moodle_url('/local/storage360/pages/backups.php', [
                        'action' => 'disable', 'courseid' => $record->id,
                        'sesskey' => sesskey(),
                        'sort' => $sort, 'dir' => $dir, 'search' => $search, 'page' => $page,
                    ]),
                    get_string('backups:disable', 'local_storage360'),
                    [
                        'class' => 'btn btn-sm btn-outline-warning mr-1',
                        'onclick' => "return confirm('" .
                            addslashes_js(get_string('backups:confirmdisable', 'local_storage360')) . "');",
                    ]
                );
            } else {
                $actions .= html_writer::link(
                    new moodle_url('/local/storage360/pages/backups.php', [
                        'action' => 'enable', 'courseid' => $record->id,
                        'sesskey' => sesskey(),
                        'sort' => $sort, 'dir' => $dir, 'search' => $search, 'page' => $page,
                    ]),
                    get_string('backups:enable', 'local_storage360'),
                    ['class' => 'btn btn-sm btn-outline-success mr-1']
                );
            }

            // Set date button — opens inline form.
            $actions .= html_writer::tag('button',
                get_string('backups:setdate', 'local_storage360'),
                [
                    'type' => 'button',
                    'class' => 'btn btn-sm btn-outline-secondary storage360-setdate-btn',
                    'data-courseid' => $record->id,
                ]);
        }

        $table->data[] = [$courselink, $countbadge, $sizeformatted, $lastbackup, $statushtml, $nextbackup, $actions];
    }

    echo html_writer::table($table);

    // Pagination.
    $pagingurl = new moodle_url($baseurl, ['sort' => $sort, 'dir' => $dir, 'search' => $search]);
    echo $OUTPUT->paging_bar($result->totalcount, $page, $perpage, $pagingurl);
}

// Modal for viewing backup files.
echo '
<div class="modal fade" id="storage360-backups-modal" tabindex="-1" role="dialog" aria-labelledby="storage360-backups-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="storage360-backups-modal-label"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="' . get_string('cancel') . '">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="storage360-backups-modal-body">
        <div class="text-center"><div class="spinner-border" role="status"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">' . get_string('cancel') . '</button>
      </div>
    </div>
  </div>
</div>';

// Modal for setting date.
echo '
<div class="modal fade" id="storage360-setdate-modal" tabindex="-1" role="dialog" aria-labelledby="storage360-setdate-modal-label" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="storage360-setdate-modal-label">' .
            get_string('backups:setdate', 'local_storage360') . '</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="' . get_string('cancel') . '">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="post" action="' . (new moodle_url('/local/storage360/pages/backups.php'))->out(false) . '">
        <div class="modal-body">
          <input type="hidden" name="action" value="setdate">
          <input type="hidden" name="sesskey" value="' . sesskey() . '">
          <input type="hidden" name="sort" value="' . s($sort) . '">
          <input type="hidden" name="dir" value="' . s($dir) . '">
          <input type="hidden" name="search" value="' . s($search) . '">
          <input type="hidden" name="page" value="' . $page . '">
          <input type="hidden" name="courseid" id="storage360-setdate-courseid" value="">
          <div class="form-group">
            <label for="storage360-setdate-input">' . get_string('backups:nextbackup', 'local_storage360') . '</label>
            <input type="datetime-local" name="newdate" id="storage360-setdate-input"
                   class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">' . get_string('cancel') . '</button>
          <button type="submit" class="btn btn-primary">' . get_string('backups:setdate', 'local_storage360') . '</button>
        </div>
      </form>
    </div>
  </div>
</div>';

$PAGE->requires->js_call_amd('local_storage360/init', 'init');

echo $OUTPUT->footer();
