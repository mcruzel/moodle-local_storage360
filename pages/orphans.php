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
 * Orphan files page - list and delete files on disk with no database reference.
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

$page = optional_param('page', 0, PARAM_INT);
$perpage = min(optional_param('perpage', 50, PARAM_INT), 500);
$action = optional_param('action', '', PARAM_ALPHA);
$deleteid = optional_param('deleteid', 0, PARAM_INT);
$downloadid = optional_param('downloadid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/orphans.php'));
$PAGE->set_title(get_string('orphans:title', 'local_storage360'));
$PAGE->set_heading(get_string('orphans:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

$candelete = has_capability('local/storage360:deletefiles', $context);

// Handle download.
if ($action === 'download' && $downloadid > 0) {
    require_sesskey();

    $record = $DB->get_record('local_storage360_scanresult', ['id' => $downloadid, 'status' => 4]);
    if ($record && preg_match('/^[0-9a-f]{40}$/', $record->contenthash)) {
        $hash = $record->contenthash;
        $hashpath = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        $filepath = $CFG->dataroot . '/filedir/' . $hashpath;

        if (file_exists($filepath)) {
            $filesize = filesize($filepath);

            // Force download as binary stream to prevent browser rendering (XSS mitigation).
            header('Content-Type: application/octet-stream');
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment; filename="orphan_' . $hash . '"');
            header('Content-Length: ' . $filesize);
            header('Cache-Control: no-cache, no-store, must-revalidate');
            readfile($filepath);
            exit;
        }
    }

    // File not found or record missing — redirect back.
    redirect(
        new moodle_url('/local/storage360/pages/orphans.php', ['page' => $page]),
        get_string('orphans:download_failed', 'local_storage360'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Handle deletion.
if ($action === 'delete' && $deleteid > 0 && $candelete) {
    require_sesskey();

    $record = $DB->get_record('local_storage360_scanresult', ['id' => $deleteid, 'status' => 4]);
    if ($record && preg_match('/^[0-9a-f]{40}$/', $record->contenthash)) {
        $hash = $record->contenthash;

        // Triple verification: check live DB references.
        $liverefcount = (int) $DB->count_records('files', ['contenthash' => $hash]);

        if ($liverefcount > 0) {
            redirect(
                new moodle_url('/local/storage360/pages/orphans.php', ['page' => $page]),
                get_string('orphans:still_referenced', 'local_storage360'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $hashpath = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        $filepath = $CFG->dataroot . '/filedir/' . $hashpath;

        $deleted = false;
        if (file_exists($filepath)) {
            if (@unlink($filepath)) {
                // Clean up empty parent directories.
                @rmdir(dirname($filepath));
                @rmdir(dirname(dirname($filepath)));
                $deleted = true;
            }
        } else {
            // File already gone from disk.
            $deleted = true;
        }

        // Remove scanresult row.
        $DB->delete_records('local_storage360_scanresult', ['id' => $deleteid]);

        if ($deleted) {
            redirect(
                new moodle_url('/local/storage360/pages/orphans.php', ['page' => $page]),
                get_string('orphans:deleted', 'local_storage360'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            redirect(
                new moodle_url('/local/storage360/pages/orphans.php', ['page' => $page]),
                get_string('orphans:delete_failed', 'local_storage360'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }
}

$calculator = new \local_storage360\analytics\storage_calculator();
$result = $calculator->get_orphan_files($page, $perpage);
$scanprogress = $calculator->get_scan_progress();

echo $OUTPUT->header();

// Tabs navigation.
$tabs = local_storage360_get_tabs('orphans');
print_tabs([$tabs], 'orphans');
echo local_storage360_render_snapshot_indicator();

// Explanation box.
echo html_writer::start_div('alert alert-info mb-3');
echo html_writer::tag('strong', get_string('orphans:whatare_title', 'local_storage360')) . ' ';
echo get_string('orphans:whatare_body', 'local_storage360');
echo html_writer::end_div();

// KPI cards.
echo html_writer::start_div('row mb-4');

// Orphan count.
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('orphans:total_count', 'local_storage360', $result->total),
    ['class' => 'card-title']);
if ($scanprogress && $scanprogress->orphan_size > 0) {
    echo html_writer::tag('h2', local_storage360_format_size($scanprogress->orphan_size),
        ['class' => 'card-text text-warning']);
} else {
    echo html_writer::tag('h2', local_storage360_format_size(0), ['class' => 'card-text text-muted']);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Disk scan progress.
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
$cursor = ($scanprogress !== null) ? ($scanprogress->orphan_cursor ?? null) : null;
if ($cursor === 'complete') {
    $scantext = get_string('orphans:disk_scan_complete', 'local_storage360');
    $scanclass = 'text-success';
} else if (!empty($cursor)) {
    $scantext = get_string('orphans:disk_scan_progress', 'local_storage360', $cursor);
    $scanclass = 'text-info';
} else {
    $scantext = get_string('orphans:disk_scan_notstarted', 'local_storage360');
    $scanclass = 'text-muted';
}
echo html_writer::tag('h5', $scantext, ['class' => 'card-title ' . $scanclass]);
if ($cursor !== 'complete' && !empty($cursor)) {
    // Show approximate progress.
    $parts = explode('/', $cursor);
    $hi = hexdec($parts[0] ?? '0');
    $lo = hexdec($parts[1] ?? '0');
    $dirsdone = $hi * 256 + $lo;
    $pct = round(($dirsdone / 65536) * 100, 1);
    echo html_writer::tag('h2', $pct . '%', ['class' => 'card-text ' . $scanclass]);
} else if ($cursor === 'complete') {
    echo html_writer::tag('h2', '100%', ['class' => 'card-text ' . $scanclass]);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.

// Orphan files table.
if ($result->total === 0) {
    echo $OUTPUT->notification(get_string('orphans:noorphans', 'local_storage360'), 'info');
} else {
    $table = new html_table();
    $tablehead = [
        get_string('orphans:contenthash', 'local_storage360'),
        get_string('orphans:filetype', 'local_storage360'),
        get_string('orphans:disksize', 'local_storage360'),
        get_string('orphans:scanned', 'local_storage360'),
        '', // Actions column (download + delete).
    ];
    $table->head = $tablehead;
    $table->attributes['class'] = 'table table-striped';

    // Open finfo handle once for all rows.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    foreach ($result->records as $record) {
        // Detect MIME type from disk file.
        $hash = $record->contenthash;
        $hashpath = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        $filepath = $CFG->dataroot . '/filedir/' . $hashpath;
        $mimetype = '';
        if (file_exists($filepath) && $finfo) {
            $mimetype = finfo_file($finfo, $filepath);
        }
        if (empty($mimetype)) {
            $mimetype = get_string('orphans:unknown_type', 'local_storage360');
        }

        $row = [
            html_writer::tag('code', s($record->contenthash)),
            html_writer::tag('small', s($mimetype)),
            local_storage360_format_size((int) $record->filesize_disk),
            userdate($record->timescanned, '%Y-%m-%d %H:%M'),
        ];

        $actions = '';

        // Download button.
        $downloadurl = new moodle_url('/local/storage360/pages/orphans.php', [
            'action' => 'download',
            'downloadid' => $record->id,
            'page' => $page,
            'sesskey' => sesskey(),
        ]);
        $actions .= html_writer::link(
            $downloadurl,
            get_string('orphans:download', 'local_storage360'),
            ['class' => 'btn btn-sm btn-outline-secondary me-1']
        );

        // Delete button (POST form to avoid destructive action via GET).
        if ($candelete) {
            $deleteurl = new moodle_url('/local/storage360/pages/orphans.php', [
                'action' => 'delete',
                'page' => $page,
            ]);
            $actions .= html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $deleteurl->out(false),
                'class' => 'd-inline',
            ]);
            $actions .= html_writer::empty_tag('input',
                ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $actions .= html_writer::empty_tag('input',
                ['type' => 'hidden', 'name' => 'deleteid', 'value' => $record->id]);
            $actions .= html_writer::tag('button',
                get_string('orphans:delete', 'local_storage360'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-danger',
                 'onclick' => 'return confirm(' . json_encode(get_string('orphans:delete_confirm', 'local_storage360')) . ');']
            );
            $actions .= html_writer::end_tag('form');
        }

        $row[] = $actions;

        $table->data[] = $row;
    }

    finfo_close($finfo);

    echo html_writer::table($table);

    // Pagination.
    $baseurl = new moodle_url('/local/storage360/pages/orphans.php', ['perpage' => $perpage]);
    echo $OUTPUT->paging_bar($result->total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
