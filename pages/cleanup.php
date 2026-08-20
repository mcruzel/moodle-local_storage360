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
 * Cleanup page - manage and delete files.
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

$tab = optional_param('tab', 'backups', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = min(optional_param('perpage', 50, PARAM_INT), 500);
$olderthan = optional_param('olderthan', 0, PARAM_INT);
$largerthan = optional_param('largerthan', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/cleanup.php'));
$PAGE->set_title(get_string('cleanup:title', 'local_storage360'));
$PAGE->set_heading(get_string('cleanup:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

$calculator = new \local_storage360\analytics\storage_calculator();

// Define which files are eligible for deletion per tab.
// This prevents arbitrary file deletion via forged POST requests.
$allowedcomponents = [
    'backups' => ['component' => 'backup', 'fileareas' => ['course', 'automated', 'activity']],
    'drafts'  => ['component' => 'user',   'fileareas' => ['draft']],
];

/**
 * Validate that a stored_file is eligible for deletion under the current tab.
 *
 * @param stored_file $file The file to validate.
 * @param string $tab The current cleanup tab.
 * @param array $allowedcomponents Allowed component/filearea mappings.
 * @return bool True if the file may be deleted.
 */
function local_storage360_is_deletable(\stored_file $file, string $tab, array $allowedcomponents): bool {
    if ($file->get_filename() === '.') {
        return false; // Never delete directory entries.
    }
    if (!isset($allowedcomponents[$tab])) {
        return false;
    }
    $rule = $allowedcomponents[$tab];
    if ($file->get_component() !== $rule['component']) {
        return false;
    }
    if (!in_array($file->get_filearea(), $rule['fileareas'], true)) {
        return false;
    }
    return true;
}

// Handle delete action.
if ($action === 'delete' && confirm_sesskey()) {
    $fileids = optional_param_array('fileids', [], PARAM_INT);

    if (!empty($fileids) && $confirm) {
        $fs = get_file_storage();
        $deletedcount = 0;

        foreach ($fileids as $fileid) {
            $file = $fs->get_file_by_id($fileid);
            if (!$file || !local_storage360_is_deletable($file, $tab, $allowedcomponents)) {
                continue;
            }

            $filename = $file->get_filename();
            $filesize = $file->get_filesize();
            $component = $file->get_component();

            // Log to audit table before deletion (file metadata needed).
            $deletesource = ($tab === 'backups') ? 'cleanup_backups' : 'cleanup_drafts';
            local_storage360_log_deletion($file, $deletesource);

            if ($file->delete()) {
                $deletedcount++;

                // Log event.
                $eventclass = ($component === 'backup')
                    ? '\\local_storage360\\event\\backup_deleted'
                    : '\\local_storage360\\event\\file_deleted';

                $event = $eventclass::create([
                    'objectid' => $fileid,
                    'context' => $context,
                    'other' => [
                        'filename' => $filename,
                        'filesize' => $filesize,
                        'component' => $component,
                    ],
                ]);
                $event->trigger();
            }
        }

        // Invalidate analysis caches after deletion.
        \cache::make('local_storage360', 'analysis')->purge();
        set_config('last_deletion_time', time(), 'local_storage360');

        redirect(
            new moodle_url('/local/storage360/pages/cleanup.php', ['tab' => $tab]),
            get_string('cleanup:deleted', 'local_storage360', $deletedcount),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Confirmation step: show what will be deleted.
    if (!empty($fileids) && !$confirm) {
        echo $OUTPUT->header();
        $tabs = local_storage360_get_tabs('cleanup');
        print_tabs([$tabs], 'cleanup');

        $fs = get_file_storage();
        $totalsize = 0;
        $filenames = [];
        $validfileids = [];
        foreach ($fileids as $fileid) {
            $file = $fs->get_file_by_id($fileid);
            if ($file && local_storage360_is_deletable($file, $tab, $allowedcomponents)) {
                $totalsize += $file->get_filesize();
                $filenames[] = $file->get_filename() . ' (' . local_storage360_format_size($file->get_filesize()) . ')';
                $validfileids[] = $fileid;
            }
        }
        $fileids = $validfileids;

        $confirmstr = get_string('cleanup:confirmmassdelete', 'local_storage360', (object) [
            'count' => count($fileids),
            'size' => local_storage360_format_size($totalsize),
        ]);

        echo $OUTPUT->heading($confirmstr, 4);
        echo html_writer::alist($filenames, ['class' => 'mb-3']);

        $confirmurl = new moodle_url('/local/storage360/pages/cleanup.php', [
            'action' => 'delete', 'confirm' => 1, 'tab' => $tab, 'sesskey' => sesskey(),
        ]);
        $cancelurl = new moodle_url('/local/storage360/pages/cleanup.php', ['tab' => $tab]);

        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $confirmurl->out(false)]);
        foreach ($fileids as $fid) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'fileids[]', 'value' => $fid]);
        }
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'submit',
            'value' => get_string('cleanup:deleteselected', 'local_storage360'),
            'class' => 'btn btn-danger mr-2']);
        echo html_writer::link($cancelurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');

        echo $OUTPUT->footer();
        exit;
    }
}

echo $OUTPUT->header();

$tabs = local_storage360_get_tabs('cleanup');
print_tabs([$tabs], 'cleanup');

// Sub-tabs for cleanup type.
$subtabs = [];
$subtabs[] = new tabobject('backups',
    new moodle_url('/local/storage360/pages/cleanup.php', ['tab' => 'backups']),
    get_string('cleanup:backups', 'local_storage360'));
$subtabs[] = new tabobject('drafts',
    new moodle_url('/local/storage360/pages/cleanup.php', ['tab' => 'drafts']),
    get_string('cleanup:drafts', 'local_storage360'));
print_tabs([$subtabs], $tab);

// Filters.
$baseurl = new moodle_url('/local/storage360/pages/cleanup.php', ['tab' => $tab]);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-4']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => $tab]);
echo html_writer::start_div('row align-items-end');

echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', get_string('cleanup:olderthan', 'local_storage360'), ['for' => 'olderthan']);
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'olderthan', 'id' => 'olderthan',
    'value' => $olderthan, 'class' => 'form-control', 'min' => 0, 'placeholder' => '0 = all']);
echo html_writer::end_div();

if ($tab === 'backups') {
    echo html_writer::start_div('col-md-3');
    echo html_writer::tag('label', get_string('cleanup:largerthan', 'local_storage360'), ['for' => 'largerthan']);
    echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'largerthan', 'id' => 'largerthan',
        'value' => $largerthan, 'class' => 'form-control', 'min' => 0, 'placeholder' => '0 = all']);
    echo html_writer::end_div();
}

echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('cleanup:preview', 'local_storage360'),
    'class' => 'btn btn-primary']);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Results.
if ($tab === 'backups') {
    $result = $calculator->get_backups($olderthan, $largerthan, $page, $perpage);
} else {
    $draftdays = $olderthan > 0 ? $olderthan : (int) get_config('local_storage360', 'draftcleanupdays');
    $result = $calculator->get_old_drafts($draftdays, $page, $perpage);
}

if (empty($result->records)) {
    echo html_writer::tag('p', get_string('cleanup:nofiles', 'local_storage360'), ['class' => 'text-muted']);
} else {
    echo html_writer::start_tag('form', ['method' => 'post',
        'action' => (new moodle_url('/local/storage360/pages/cleanup.php',
            ['action' => 'delete', 'tab' => $tab]))->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover';

    if ($tab === 'backups') {
        $table->head = [
            html_writer::checkbox('selectall', 1, false, '', ['id' => 'selectall']),
            'Filename', 'Course', 'Size', 'Area', 'Date', '',
        ];
        foreach ($result->records as $record) {
            $courseurl = !empty($record->courseid)
                ? html_writer::link(new moodle_url('/course/view.php', ['id' => $record->courseid]),
                    s($record->coursename))
                : '-';

            $sizeformatted = local_storage360_format_size((int) $record->filesize);
            $deletebtn = html_writer::tag('button',
                get_string('cleanup:deleteone', 'local_storage360'),
                ['type' => 'button', 'class' => 'btn btn-sm btn-danger storage360-delete-one',
                 'data-fileid' => $record->id,
                 'data-filename' => s($record->filename),
                 'data-filesize' => $sizeformatted]);

            $actions = '';
            if (!empty($record->courseid)) {
                $actions .= html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $record->courseid]),
                    get_string('cleanup:managecourse', 'local_storage360'),
                    ['class' => 'btn btn-sm btn-outline-secondary mr-1']
                );
            }
            $actions .= $deletebtn;

            $table->data[] = [
                html_writer::checkbox('fileids[]', $record->id, false),
                s($record->filename),
                $courseurl,
                $sizeformatted,
                s($record->filearea),
                userdate($record->timecreated),
                $actions,
            ];
        }
    } else {
        // Drafts.
        $table->head = [
            html_writer::checkbox('selectall', 1, false, '', ['id' => 'selectall']),
            'Filename', 'User', 'Size', 'Last modified', '',
        ];
        foreach ($result->records as $record) {
            $username = !empty($record->firstname)
                ? html_writer::link(
                    new moodle_url('/user/profile.php', ['id' => $record->userid]),
                    s($record->firstname . ' ' . $record->lastname)
                  )
                : '-';

            $sizeformatted = local_storage360_format_size((int) $record->filesize);
            $deletebtn = html_writer::tag('button',
                get_string('cleanup:deleteone', 'local_storage360'),
                ['type' => 'button', 'class' => 'btn btn-sm btn-danger storage360-delete-one',
                 'data-fileid' => $record->id,
                 'data-filename' => s($record->filename),
                 'data-filesize' => $sizeformatted]);

            $table->data[] = [
                html_writer::checkbox('fileids[]', $record->id, false),
                s($record->filename),
                $username,
                $sizeformatted,
                userdate($record->timemodified),
                html_writer::link(
                    new moodle_url('/user/files.php', ['userid' => $record->userid]),
                    get_string('users:privatefiles', 'local_storage360'),
                    ['class' => 'btn btn-sm btn-outline-secondary mr-1']
                ) . $deletebtn,
            ];
        }
    }

    echo html_writer::table($table);

    // Delete button.
    echo html_writer::start_div('mb-3');
    echo html_writer::empty_tag('input', ['type' => 'submit',
        'value' => get_string('cleanup:deleteselected', 'local_storage360'),
        'class' => 'btn btn-danger',
        'onclick' => "return confirm('" .
            addslashes_js(get_string('cleanup:confirmdelete', 'local_storage360')) . "');"]);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');

    // Pagination.
    $pagingurl = new moodle_url($baseurl, ['olderthan' => $olderthan, 'largerthan' => $largerthan]);
    echo $OUTPUT->paging_bar($result->totalcount, $page, $perpage, $pagingurl);
}

// Bootstrap modal for single-file delete confirmation.
echo '
<div class="modal fade" id="storage360-delete-modal" tabindex="-1" role="dialog" aria-labelledby="storage360-delete-modal-label" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="storage360-delete-modal-label">' .
            get_string('cleanup:confirmdeletetitle', 'local_storage360') . '</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="' . get_string('cancel') . '">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>' . get_string('cleanup:confirmdeletebody', 'local_storage360') . '</p>
        <p><strong id="storage360-delete-filename"></strong> (<span id="storage360-delete-filesize"></span>)</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">' . get_string('cancel') . '</button>
        <form method="post" action="' .
            (new moodle_url('/local/storage360/pages/cleanup.php',
                ['action' => 'delete', 'confirm' => 1, 'tab' => $tab]))->out(false) . '" style="display:inline">
          <input type="hidden" name="sesskey" value="' . sesskey() . '">
          <input type="hidden" name="fileids[]" id="storage360-delete-fileid" value="">
          <button type="submit" class="btn btn-danger">' .
              get_string('cleanup:deleteone', 'local_storage360') . '</button>
        </form>
      </div>
    </div>
  </div>
</div>';

// Select all + modal JS via AMD module.
$PAGE->requires->js_call_amd('local_storage360/init', 'init');

echo $OUTPUT->footer();
