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

namespace local_storage360\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to collect daily storage statistics.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect_storage_stats extends \core\task\scheduled_task {

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:collectstoragestats', 'local_storage360');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/storage360/lib.php');

        $calculator = new \local_storage360\analytics\storage_calculator();

        // Collect global stats.
        $stats = $calculator->get_global_stats(false);
        $diskspace = $calculator->get_disk_space();
        $components = $calculator->get_by_component(false);

        // Build enriched component breakdown JSON.
        $componentdata = [];
        foreach ($components as $comp) {
            $key = $comp->component . '/' . $comp->filearea;
            $componentdata[$key] = [
                'total_size'  => (int) $comp->total_size,
                'file_count'  => (int) $comp->file_count,
                'avg_size'    => (float) $comp->avg_size,
                'max_size'    => (int) $comp->max_size,
                'oldest_file' => (int) $comp->oldest_file,
                'newest_file' => (int) $comp->newest_file,
            ];
        }

        $record = new \stdClass();
        $record->timecreated = time();
        $record->total_files = $stats->total_files;
        $record->total_size = $stats->total_size;
        $record->disk_total = $diskspace ? $diskspace->total : null;
        $record->disk_free = $diskspace ? $diskspace->free : null;
        $record->by_component = json_encode($componentdata);

        $DB->insert_record('local_storage360_history', $record);

        // Collect per-course snapshots.
        $this->collect_course_snapshots($calculator);

        // Collect per-user snapshots.
        $this->collect_user_snapshots();

        // Cleanup old snapshots to prevent table bloat.
        $this->cleanup_old_snapshots();

        // Purge analysis cache so next page load uses fresh snapshot data.
        \cache::make('local_storage360', 'analysis')->purge();

        mtrace('Storage 360: statistics collected successfully.');

        // Check storage threshold and send alert if needed.
        $this->check_storage_threshold($diskspace);
    }

    /**
     * Collect per-course snapshots.
     *
     * @param \local_storage360\analytics\storage_calculator $calculator
     */
    private function collect_course_snapshots(\local_storage360\analytics\storage_calculator $calculator) {
        global $DB;

        $now = time();

        // Use context path to capture files from all child contexts (modules, blocks, etc.).
        $pathconcat = $DB->sql_concat('coursectx.path', "'/%'");

        $sql = "SELECT c.id as courseid,
                       COALESCE(SUM(f.filesize), 0) as total_size,
                       COUNT(DISTINCT f.id) as file_count,
                       COALESCE(SUM(CASE WHEN f.component = 'backup' THEN f.filesize ELSE 0 END), 0) as backup_size,
                       COALESCE(SUM(CASE WHEN f.component LIKE 'assign%' THEN f.filesize ELSE 0 END), 0) as assignment_size
                  FROM {course} c
                  JOIN {context} coursectx ON coursectx.instanceid = c.id AND coursectx.contextlevel = :ctxlevel
             LEFT JOIN {context} ctx ON (ctx.id = coursectx.id OR ctx.path LIKE {$pathconcat})
             LEFT JOIN {files} f ON f.contextid = ctx.id AND f.filename != '.'
                 WHERE c.id != 1
                 GROUP BY c.id";

        $courses = $DB->get_recordset_sql($sql, ['ctxlevel' => CONTEXT_COURSE]);

        foreach ($courses as $course) {
            $record = new \stdClass();
            $record->courseid = $course->courseid;
            $record->timecreated = $now;
            $record->total_size = (int) $course->total_size;
            $record->file_count = (int) $course->file_count;
            $record->backup_size = (int) $course->backup_size;
            $record->assignment_size = (int) $course->assignment_size;
            $DB->insert_record('local_storage360_course_snap', $record);
        }

        $courses->close();
    }

    /**
     * Collect per-user storage snapshots.
     */
    private function collect_user_snapshots(): void {
        global $DB;

        $now = time();

        $sql = "SELECT u.id as userid,
                       COALESCE(SUM(f.filesize), 0) as total_size,
                       COUNT(f.id) as file_count,
                       COALESCE(SUM(CASE WHEN f.component = 'user' AND f.filearea = 'private'
                                    THEN f.filesize ELSE 0 END), 0) as private_files,
                       COALESCE(SUM(CASE WHEN f.component = 'user' AND f.filearea = 'draft'
                                    THEN f.filesize ELSE 0 END), 0) as draft_files,
                       COALESCE(SUM(CASE WHEN f.component = 'assignsubmission_file'
                                    THEN f.filesize ELSE 0 END), 0) as assignment_files,
                       MAX(f.timecreated) as last_upload
                  FROM {user} u
                  JOIN {files} f ON f.userid = u.id AND f.filename != '.'
                 WHERE u.deleted = 0
                 GROUP BY u.id";

        $users = $DB->get_recordset_sql($sql);

        foreach ($users as $user) {
            $record = new \stdClass();
            $record->userid = $user->userid;
            $record->timecreated = $now;
            $record->total_size = (int) $user->total_size;
            $record->file_count = (int) $user->file_count;
            $record->private_files = (int) $user->private_files;
            $record->draft_files = (int) $user->draft_files;
            $record->assignment_files = (int) $user->assignment_files;
            $record->last_upload = $user->last_upload ? (int) $user->last_upload : null;
            $DB->insert_record('local_storage360_user_snap', $record);
        }

        $users->close();
        mtrace('Storage 360: user snapshots collected.');
    }

    /**
     * Delete old course and user snapshots to prevent table bloat.
     * Keeps the last 90 days of snapshot data.
     */
    private function cleanup_old_snapshots(): void {
        global $DB;

        $cutoff = time() - (90 * 86400);

        $DB->delete_records_select('local_storage360_course_snap', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('local_storage360_user_snap', 'timecreated < :cutoff', ['cutoff' => $cutoff]);

        mtrace('Storage 360: old snapshots cleaned up (kept last 90 days).');
    }

    /**
     * Check if storage usage exceeds threshold and send notification if needed.
     *
     * @param object|null $diskspace Disk space info from storage_calculator.
     */
    private function check_storage_threshold(?object $diskspace): void {
        if (!get_config('local_storage360', 'enablestoragealert')) {
            return;
        }

        if ($diskspace === null) {
            mtrace('Storage 360: disk space unavailable, skipping threshold check.');
            return;
        }

        $threshold = (int) get_config('local_storage360', 'storagethreshold');
        if ($threshold <= 0 || $threshold > 100) {
            $threshold = 90;
        }

        $currentpercent = $diskspace->percent;
        $alertsent = get_config('local_storage360', 'alert_threshold_sent');

        if ($currentpercent >= $threshold) {
            if (!$alertsent) {
                $this->send_storage_alert($diskspace);
                set_config('alert_threshold_sent', 1, 'local_storage360');
                mtrace("Storage 360: threshold alert sent (usage {$currentpercent}% >= {$threshold}%).");
            } else {
                mtrace("Storage 360: usage {$currentpercent}% still above threshold, alert already sent.");
            }
        } else {
            if ($alertsent) {
                set_config('alert_threshold_sent', 0, 'local_storage360');
                mtrace("Storage 360: usage {$currentpercent}% dropped below threshold {$threshold}%, alert reset.");
            }
        }
    }

    /**
     * Send the storage threshold alert to all site administrators.
     *
     * @param object $diskspace Disk space info.
     */
    private function send_storage_alert(object $diskspace): void {
        $admins = get_admins();
        if (empty($admins)) {
            mtrace('Storage 360: no site administrators found for storage alert.');
            return;
        }

        $a = new \stdClass();
        $a->percent = $diskspace->percent;
        $a->used = local_storage360_format_size((int) $diskspace->used);
        $a->total = local_storage360_format_size((int) $diskspace->total);
        $a->dashboardurl = (new \moodle_url('/local/storage360/pages/dashboard.php'))->out(false);
        $a->settingsurl = (new \moodle_url('/admin/settings.php', ['section' => 'local_storage360']))->out(false);

        $subject = get_string('alert:subject', 'local_storage360', $diskspace->percent);
        $body = get_string('alert:body', 'local_storage360', $a);
        $bodyhtml = get_string('alert:bodyhtml', 'local_storage360', $a);

        $noreplyuser = \core_user::get_noreply_user();

        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component = 'local_storage360';
            $message->name = 'storagealert';
            $message->userfrom = $noreplyuser;
            $message->userto = $admin;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $bodyhtml;
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = new \moodle_url('/local/storage360/pages/dashboard.php');
            $message->contexturlname = get_string('nav:dashboard', 'local_storage360');

            message_send($message);
        }

        mtrace('Storage 360: alert sent to ' . count($admins) . ' administrator(s).');
    }
}
