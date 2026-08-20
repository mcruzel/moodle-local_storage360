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
 * Library functions for local_storage360.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation nodes to the site administration menu.
 *
 * @param navigation_node $nav The navigation node to extend.
 */
function local_storage360_extend_navigation(global_navigation $nav) {
    // Navigation is handled via settings.php admin tree.
}

/**
 * Extend the site administration navigation with Storage 360 links.
 *
 * @param settings_navigation $nav
 * @param context $context
 */
function local_storage360_extend_settings_navigation(settings_navigation $nav, context $context) {
    // Only show for system context.
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return;
    }

    if (!has_capability('local/storage360:view', $context)) {
        return;
    }

    $servernode = $nav->find('server', navigation_node::TYPE_SETTING);
    if (!$servernode) {
        return;
    }

    $storagenode = $servernode->add(
        get_string('pluginname', 'local_storage360'),
        new moodle_url('/local/storage360/index.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_storage360',
        new pix_icon('i/report', '')
    );

    $storagenode->add(
        get_string('nav:dashboard', 'local_storage360'),
        new moodle_url('/local/storage360/pages/dashboard.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:courses', 'local_storage360'),
        new moodle_url('/local/storage360/pages/courses.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:users', 'local_storage360'),
        new moodle_url('/local/storage360/pages/users.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:components', 'local_storage360'),
        new moodle_url('/local/storage360/pages/components.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:timeline', 'local_storage360'),
        new moodle_url('/local/storage360/pages/timeline.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:files', 'local_storage360'),
        new moodle_url('/local/storage360/pages/files.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:cleanup', 'local_storage360'),
        new moodle_url('/local/storage360/pages/cleanup.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:backups', 'local_storage360'),
        new moodle_url('/local/storage360/pages/backups.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:deletelog', 'local_storage360'),
        new moodle_url('/local/storage360/pages/deletelog.php'),
        navigation_node::TYPE_SETTING
    );

    $storagenode->add(
        get_string('nav:orphans', 'local_storage360'),
        new moodle_url('/local/storage360/pages/orphans.php'),
        navigation_node::TYPE_SETTING
    );
}

/**
 * Format file size in human-readable form.
 *
 * @param int $bytes Size in bytes.
 * @return string Formatted size string.
 */
function local_storage360_format_size(int $bytes): string {
    if ($bytes >= 1099511627776) {
        return round($bytes / 1099511627776, 2) . ' ' . get_string('terabytes', 'local_storage360');
    } else if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' ' . get_string('gigabytes', 'local_storage360');
    } else if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' ' . get_string('megabytes', 'local_storage360');
    } else if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' ' . get_string('kilobytes', 'local_storage360');
    }
    return $bytes . ' ' . get_string('bytes', 'local_storage360');
}

/**
 * Log a file deletion to the audit table.
 *
 * Must be called BEFORE $file->delete() since we need file metadata.
 *
 * @param \stored_file $file The file about to be deleted.
 * @param string $source Origin of the deletion (cleanup_backups, cleanup_drafts, files_browser).
 */
function local_storage360_log_deletion(\stored_file $file, string $source): void {
    global $DB, $USER;

    // Resolve course from context.
    $courseid = null;
    $coursename = null;
    $ctx = \context::instance_by_id($file->get_contextid(), IGNORE_MISSING);
    if ($ctx) {
        $coursecontext = $ctx->get_course_context(false);
        if ($coursecontext) {
            $courseid = $coursecontext->instanceid;
            $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        }
    }

    // Resolve file owner name.
    $owneruserid = $file->get_userid();
    $ownerfullname = null;
    if ($owneruserid) {
        $owner = $DB->get_record('user', ['id' => $owneruserid], 'id,firstname,lastname', IGNORE_MISSING);
        if ($owner) {
            $ownerfullname = fullname($owner);
        }
    }

    $record = new \stdClass();
    $record->fileid = $file->get_id();
    $record->filename = $file->get_filename();
    $record->filepath = $file->get_filepath();
    $record->filesize = $file->get_filesize();
    $record->contenthash = $file->get_contenthash();
    $record->mimetype = $file->get_mimetype();
    $record->component = $file->get_component();
    $record->filearea = $file->get_filearea();
    $record->courseid = $courseid;
    $record->coursename = $coursename;
    $record->owneruserid = $owneruserid;
    $record->ownerfullname = $ownerfullname;
    $record->deletedby = $USER->id;
    $record->source = $source;
    $record->timedeleted = time();

    $DB->insert_record('local_storage360_deletelog', $record);
}

/**
 * Build secondary navigation tabs for the plugin pages.
 *
 * @param string $activetab The currently active tab key.
 * @return array Array of tabobject instances.
 */
function local_storage360_get_tabs(string $activetab): array {
    $tabs = [];
    $tabs[] = new tabobject(
        'dashboard',
        new moodle_url('/local/storage360/pages/dashboard.php'),
        get_string('nav:dashboard', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'courses',
        new moodle_url('/local/storage360/pages/courses.php'),
        get_string('nav:courses', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'users',
        new moodle_url('/local/storage360/pages/users.php'),
        get_string('nav:users', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'components',
        new moodle_url('/local/storage360/pages/components.php'),
        get_string('nav:components', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'timeline',
        new moodle_url('/local/storage360/pages/timeline.php'),
        get_string('nav:timeline', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'files',
        new moodle_url('/local/storage360/pages/files.php'),
        get_string('nav:files', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'cleanup',
        new moodle_url('/local/storage360/pages/cleanup.php'),
        get_string('nav:cleanup', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'backups',
        new moodle_url('/local/storage360/pages/backups.php'),
        get_string('nav:backups', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'deletelog',
        new moodle_url('/local/storage360/pages/deletelog.php'),
        get_string('nav:deletelog', 'local_storage360')
    );
    $tabs[] = new tabobject(
        'orphans',
        new moodle_url('/local/storage360/pages/orphans.php'),
        get_string('nav:orphans', 'local_storage360')
    );
    return $tabs;
}

/**
 * Render a discreet snapshot indicator showing when analytics data was last collected.
 *
 * @return string HTML string (empty if no snapshot exists yet).
 */
function local_storage360_render_snapshot_indicator(): string {
    global $DB;

    $snaptime = $DB->get_field_sql('SELECT MAX(timecreated) FROM {local_storage360_history}');
    if (empty($snaptime)) {
        return '';
    }

    $datestr = userdate((int) $snaptime, get_string('strftimedatetimeshort', 'core_langconfig'));
    $text = get_string('snapshot:lastupdated', 'local_storage360', $datestr);

    return html_writer::tag('small', $text, ['class' => 'text-muted d-block mb-3']);
}

/**
 * Check whether a deleted file's content still exists on disk.
 *
 * Moodle does not delete physical files immediately. When the last DB reference
 * is removed, the file is moved from filedir/ to trashdir/ by the cron.
 * The trash cleanup task then permanently deletes it after ~24h-4 days.
 *
 * @param string $contenthash The SHA1 content hash of the file.
 * @return object Object with on_disk (bool), in_trash (bool), db_references (int), status (string).
 */
function local_storage360_check_file_on_disk(string $contenthash): object {
    global $CFG, $DB;

    $result = new \stdClass();
    $result->on_disk = false;
    $result->in_trash = false;
    $result->db_references = 0;
    $result->status = 'removed';

    if (empty($contenthash) || !preg_match('/^[0-9a-f]{40}$/', $contenthash)) {
        return $result;
    }

    $hashpath = substr($contenthash, 0, 2) . '/' . substr($contenthash, 2, 2) . '/' . $contenthash;

    // Check physical file in filedir.
    $result->on_disk = file_exists($CFG->dataroot . '/filedir/' . $hashpath);

    // Check physical file in trashdir (pending permanent deletion by cron).
    $result->in_trash = file_exists($CFG->dataroot . '/trashdir/' . $hashpath);

    // Check how many DB records still reference this content hash.
    $result->db_references = (int) $DB->count_records('files', ['contenthash' => $contenthash]);

    // Determine status.
    if ($result->db_references > 0) {
        $result->status = 'referenced';
    } else if ($result->in_trash) {
        $result->status = 'trash';
    } else if ($result->on_disk) {
        $result->status = 'orphan';
    } else {
        $result->status = 'removed';
    }

    return $result;
}

/**
 * Get a human-readable status label for a backup status code.
 *
 * @param int $status The backup status code from backup_courses.laststatus.
 * @return string The translated status label.
 */
function local_storage360_backup_status_label(int $status): string {
    switch ($status) {
        case 1:
            return get_string('backups:status_ok', 'local_storage360');
        case 0:
            return get_string('backups:status_error', 'local_storage360');
        case 2:
            return get_string('backups:status_unfinished', 'local_storage360');
        case 3:
            return get_string('backups:status_skipped', 'local_storage360');
        default:
            return get_string('backups:status_never', 'local_storage360');
    }
}

/**
 * Get an HTML badge for a backup status code.
 *
 * @param int $status The backup status code from backup_courses.laststatus.
 * @return string HTML badge markup.
 */
function local_storage360_backup_status_badge(int $status): string {
    $label = local_storage360_backup_status_label($status);
    switch ($status) {
        case 1:
            $badgeclass = 'badge badge-success';
            break;
        case 0:
            $badgeclass = 'badge badge-danger';
            break;
        case 2:
            $badgeclass = 'badge badge-warning';
            break;
        case 3:
            $badgeclass = 'badge badge-secondary';
            break;
        default:
            $badgeclass = 'badge badge-light';
    }
    return html_writer::tag('span', $label, ['class' => $badgeclass]);
}

/**
 * Delete trashdir files that were deleted via Storage 360.
 *
 * Only targets contenthashes recorded in our deletelog table.
 * This ensures we only purge files that were explicitly deleted
 * through the plugin, not files trashed by other Moodle operations.
 *
 * Safety: only touches trashdir/ (never filedir/).
 *
 * @return object Result with cleaned (array), skipped_referenced (array),
 *                already_removed (array), failed (array), and total_bytes_freed (int).
 */
function local_storage360_cleanup_trash_files(): object {
    global $CFG, $DB;

    $result = new \stdClass();
    $result->cleaned = [];
    $result->skipped_referenced = [];
    $result->already_removed = [];
    $result->failed = [];
    $result->total_bytes_freed = 0;

    // Only target contenthashes from our own deletion log.
    $sql = "SELECT DISTINCT contenthash
              FROM {local_storage360_deletelog}
             WHERE contenthash IS NOT NULL AND contenthash <> ''";
    $hashes = $DB->get_fieldset_sql($sql);

    if (empty($hashes)) {
        return $result;
    }

    foreach ($hashes as $contenthash) {
        if (!preg_match('/^[0-9a-f]{40}$/', $contenthash)) {
            continue;
        }

        $hashpath = substr($contenthash, 0, 2) . '/' . substr($contenthash, 2, 2) . '/' . $contenthash;
        $trashfile = $CFG->dataroot . '/trashdir/' . $hashpath;

        // Check how many DB records still reference this content.
        $dbrefs = (int) $DB->count_records('files', ['contenthash' => $contenthash]);

        if ($dbrefs > 0) {
            $result->skipped_referenced[] = $contenthash;
            continue;
        }

        // Only delete from trashdir (safe).
        if (file_exists($trashfile)) {
            $size = filesize($trashfile);
            if (@unlink($trashfile)) {
                $result->cleaned[] = $contenthash;
                $result->total_bytes_freed += $size;
                @rmdir(dirname($trashfile));
                @rmdir(dirname(dirname($trashfile)));
            } else {
                $result->failed[] = $contenthash;
            }
        } else {
            // Already gone from disk (cleaned by cron or previous purge).
            $result->already_removed[] = $contenthash;
        }
    }

    return $result;
}

/**
 * Reset the integrity scan by truncating the scanresult table.
 *
 * This allows a new scan cycle to begin from scratch.
 *
 * @return void
 */
function local_storage360_reset_scan(): void {
    global $DB;

    $DB->delete_records('local_storage360_scanresult');
    set_config('orphan_scan_cursor', null, 'local_storage360');
}
