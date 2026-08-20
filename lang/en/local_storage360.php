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
 * English language strings for local_storage360.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Storage 360';

// Navigation.
$string['nav:dashboard'] = 'Dashboard';
$string['nav:courses'] = 'By course';
$string['nav:users'] = 'By user';
$string['nav:components'] = 'By component';
$string['nav:timeline'] = 'Timeline';
$string['nav:cleanup'] = 'Cleanup';
$string['nav:backups'] = 'Backups';
$string['nav:deletelog'] = 'Deletion log';

// Dashboard.
$string['dashboard:title'] = 'Storage 360 - Dashboard';
$string['dashboard:totalstorage'] = 'Total storage used';
$string['dashboard:totalfiles'] = 'Total files';
$string['dashboard:avgfilesize'] = 'Average file size';
$string['dashboard:diskusage'] = 'Disk usage';
$string['dashboard:diskfree'] = 'Free space';
$string['dashboard:disktotal'] = 'Total disk space';
$string['dashboard:diskpercent'] = 'Disk usage percentage';
$string['dashboard:growthrate'] = 'Monthly growth rate';
$string['dashboard:growthrate_pending'] = 'Awaiting data';
$string['dashboard:growthrate_available'] = 'Available from {$a}';
$string['dashboard:nextupdate'] = 'Next update: {$a}';
$string['dashboard:top5courses'] = 'Top 5 courses by storage';
$string['dashboard:bycomponent'] = 'Storage by component';
$string['dashboard:nodiskinfo'] = 'Disk space information is not available. Configure manual disk size in settings.';

// Courses view.
$string['courses:title'] = 'Storage 360 - Storage by course';
$string['courses:coursename'] = 'Course';
$string['courses:totalsize'] = 'Total size';
$string['courses:filecount'] = 'Files';
$string['courses:backupsize'] = 'Backups';
$string['courses:assignmentsize'] = 'Assignments';
$string['courses:studentcount'] = 'Students';
$string['courses:perpupil'] = 'Per student';
$string['courses:category'] = 'Category';
$string['courses:visible'] = 'Visible';
$string['courses:hidden'] = 'Hidden';
$string['courses:minsize'] = 'Min size';
$string['courses:status'] = 'Status';
$string['courses:search'] = 'Search course';
$string['courses:searchplaceholder'] = 'ID or name...';

// Users view.
$string['users:title'] = 'Storage 360 - Storage by user';
$string['users:username'] = 'User';
$string['users:email'] = 'Email';
$string['users:privatefiles'] = 'Private files';
$string['users:draftfiles'] = 'Drafts';
$string['users:assignmentfiles'] = 'Assignments';
$string['users:totalsize'] = 'Total size';
$string['users:lastupload'] = 'Last upload';
$string['users:search'] = 'Search user';
$string['users:searchplaceholder'] = 'ID, username, name or email...';

// Components view.
$string['components:title'] = 'Storage 360 - Storage by component';
$string['components:component'] = 'Component';
$string['components:filearea'] = 'File area';
$string['components:filecount'] = 'Files';
$string['components:totalsize'] = 'Total size';
$string['components:avgsize'] = 'Avg size';
$string['components:maxsize'] = 'Max size';
$string['components:oldest'] = 'Oldest';
$string['components:newest'] = 'Newest';

// Timeline view.
$string['timeline:title'] = 'Storage 360 - Timeline';
$string['timeline:totalovertime'] = 'Total storage over time';
$string['timeline:monthlyadditions'] = 'Monthly additions';
$string['timeline:nohistory'] = 'No historical data available yet. The scheduled task will collect data daily.';
$string['timeline:retroanalysis'] = 'Retrospective analysis (based on file creation dates)';
$string['timeline:bycomponent'] = 'Evolution by content type';
$string['timeline:resources'] = 'Resources';
$string['timeline:folders'] = 'Folders';
$string['timeline:coursefiles'] = 'Course files';
$string['timeline:otherfiles'] = 'Other';

// Files view.
$string['nav:files'] = 'Files';
$string['files:title'] = 'Storage 360 - File browser';
$string['files:filename'] = 'Filename';
$string['files:size'] = 'Size';
$string['files:mimetype'] = 'MIME type';
$string['files:course'] = 'Course';
$string['files:user'] = 'User';
$string['files:created'] = 'Created';
$string['files:search'] = 'Search filename';
$string['files:searchplaceholder'] = 'e.g. report.pdf';
$string['files:totalresults'] = '{$a} file(s) found';
$string['files:filtercourse'] = 'Course: {$a}';
$string['files:filteruser'] = 'User: {$a}';
$string['files:filtercomponent'] = 'Component: {$a}';
$string['files:images'] = 'Images';
$string['files:videos'] = 'Videos';
$string['files:audio'] = 'Audio';
$string['files:viewfiles'] = 'View files';

// Cleanup.
$string['cleanup:title'] = 'Storage 360 - Cleanup';
$string['cleanup:backups'] = 'Backups';
$string['cleanup:drafts'] = 'User drafts';
$string['cleanup:selectall'] = 'Select all';
$string['cleanup:deleteselected'] = 'Delete selected';
$string['cleanup:confirmdelete'] = 'Are you sure you want to delete the selected files? This action cannot be undone.';
$string['cleanup:confirmmassdelete'] = 'You are about to delete {$a->count} file(s) totalling {$a->size}. This action cannot be undone. Continue?';
$string['cleanup:deleted'] = '{$a} file(s) successfully deleted.';
$string['cleanup:nofiles'] = 'No files match the selected criteria.';
$string['cleanup:olderthan'] = 'Older than (days)';
$string['cleanup:largerthan'] = 'Larger than (MB)';
$string['cleanup:preview'] = 'Preview';
$string['cleanup:managecourse'] = 'Manage course';
$string['cleanup:viewassignment'] = 'View assignment';
$string['cleanup:deleteone'] = 'Delete';
$string['cleanup:confirmdeletetitle'] = 'Confirm deletion';
$string['cleanup:confirmdeletebody'] = 'Are you sure you want to delete this file? This action cannot be undone.';

// Backups management.
$string['backups:title'] = 'Storage 360 - Backup management';
$string['backups:warning'] = 'Automated backups are recreated by the Moodle cron. To free space permanently, disable the next scheduled backup before deleting the files.';
$string['backups:course'] = 'Course';
$string['backups:count'] = 'Backups';
$string['backups:totalsize'] = 'Total size';
$string['backups:lastbackup'] = 'Last backup';
$string['backups:status'] = 'Status';
$string['backups:nextbackup'] = 'Next backup';
$string['backups:disabled'] = 'Disabled';
$string['backups:status_ok'] = 'OK';
$string['backups:status_error'] = 'Error';
$string['backups:status_unfinished'] = 'In progress';
$string['backups:status_skipped'] = 'Skipped';
$string['backups:status_never'] = 'Never';
$string['backups:disable'] = 'Disable';
$string['backups:enable'] = 'Re-enable';
$string['backups:setdate'] = 'Set date';
$string['backups:viewfiles'] = 'View files';
$string['backups:modalfiles'] = 'Backup files for course {$a}';
$string['backups:filearea_automated'] = 'Automated';
$string['backups:filearea_course'] = 'Manual';
$string['backups:filearea_activity'] = 'Activity';
$string['backups:disabled_success'] = 'Automated backup disabled for this course.';
$string['backups:enabled_success'] = 'Automated backup re-enabled for this course.';
$string['backups:datechanged'] = 'Next backup date updated.';
$string['backups:nobackups'] = 'No courses with backup files.';
$string['backups:search'] = 'Search course';
$string['backups:searchplaceholder'] = 'Course ID or name...';
$string['backups:confirmdisable'] = 'Are you sure you want to disable the next automated backup for this course?';

// Deletion log.
$string['deletelog:title'] = 'Storage 360 - Deletion log';
$string['deletelog:date'] = 'Date';
$string['deletelog:owner'] = 'File owner';
$string['deletelog:deletedby'] = 'Deleted by';
$string['deletelog:source'] = 'Source';
$string['deletelog:source_backups'] = 'Cleanup (backups)';
$string['deletelog:source_drafts'] = 'Cleanup (drafts)';
$string['deletelog:source_files'] = 'File browser';
$string['deletelog:search'] = 'Search';
$string['deletelog:searchplaceholder'] = 'Filename, course, owner or ID...';
$string['deletelog:summary'] = '{$a->count} file(s) deleted totalling {$a->size}';
$string['deletelog:verifydisk'] = 'Verify disk';
$string['deletelog:diskstatus'] = 'Disk status';
$string['deletelog:status_removed'] = 'Removed from disk';
$string['deletelog:status_referenced'] = 'Content still referenced ({$a} copy/copies)';
$string['deletelog:status_trash'] = 'In trash (pending cron cleanup)';
$string['deletelog:status_orphan'] = 'Orphan on disk';
$string['deletelog:emptytrash'] = 'Purge trash';
$string['deletelog:emptytrashconfirm'] = 'This will permanently delete all unreferenced files from the Moodle trash directory. This action cannot be undone. Continue?';
$string['deletelog:trashcleaned'] = 'Trash directory cleaned successfully.';
$string['deletelog:cleanresult_title'] = 'Trash purge results';
$string['deletelog:cleanresult_summary'] = '{$a->cleaned} file(s) permanently deleted ({$a->freed} freed). {$a->skipped} still referenced, {$a->already} already removed, {$a->failed} failed.';
$string['deletelog:cleanresult_cleaned'] = '{$a} deleted from disk';
$string['deletelog:cleanresult_skipped'] = '{$a} still referenced';
$string['deletelog:cleanresult_already'] = '{$a} already removed';
$string['deletelog:cleanresult_failed'] = '{$a} failed';

// Settings.
$string['settings:diskspacemethod'] = 'Disk space calculation method';
$string['settings:diskspacemethod_desc'] = 'How to determine total disk space. "Auto" reads from filesystem, "Manual" uses a configured value, "DB only" only shows storage from database.';
$string['settings:diskspacemethod_auto'] = 'Automatic (filesystem)';
$string['settings:diskspacemethod_manual'] = 'Manual';
$string['settings:diskspacemethod_dbonly'] = 'Database only';
$string['settings:manualdisksize'] = 'Manual disk size (GB)';
$string['settings:manualdisksize_desc'] = 'Total disk partition size in GB, used when method is "Manual".';
$string['settings:cachettl'] = 'Cache duration (seconds)';
$string['settings:cachettl_desc'] = 'How long to cache analysis results before recalculating.';
$string['settings:draftcleanupdays'] = 'Draft cleanup threshold (days)';
$string['settings:draftcleanupdays_desc'] = 'Drafts older than this number of days are candidates for cleanup.';
$string['settings:backupcleanupdays'] = 'Backup cleanup threshold (days)';
$string['settings:backupcleanupdays_desc'] = 'Backups older than this number of days are candidates for cleanup.';
$string['settings:enableautocleanup'] = 'Enable automatic cleanup';
$string['settings:enableautocleanup_desc'] = 'If enabled, the scheduled task will automatically clean up old drafts and backups based on the configured thresholds.';
$string['settings:enablestoragealert'] = 'Enable storage threshold alert';
$string['settings:enablestoragealert_desc'] = 'Send a notification to site administrators when disk usage exceeds the configured threshold. Requires disk space method to be "Automatic" or "Manual".';
$string['settings:storagethreshold'] = 'Storage alert threshold (%)';
$string['settings:storagethreshold_desc'] = 'When disk usage reaches this percentage, an alert notification is sent to all site administrators. Valid range: 1-100.';

// Message provider.
$string['messageprovider:storagealert'] = 'Storage threshold alert';

// Alert notification content.
$string['alert:subject'] = 'Storage 360: Disk usage has reached {$a}%';
$string['alert:body'] = 'Warning: The disk usage on your Moodle instance has reached {$a->percent}% ({$a->used} used out of {$a->total}).

You can view detailed storage information at: {$a->dashboardurl}

This notification was sent automatically by the Storage 360 plugin. You can adjust the threshold or disable alerts in the plugin settings.';
$string['alert:bodyhtml'] = '<p><strong>Warning:</strong> The disk usage on your Moodle instance has reached <strong>{$a->percent}%</strong> ({$a->used} used out of {$a->total}).</p><p>You can view detailed storage information on the <a href="{$a->dashboardurl}">Storage 360 dashboard</a>.</p><p><small>This notification was sent automatically by the Storage 360 plugin. You can adjust the threshold or disable alerts in the <a href="{$a->settingsurl}">plugin settings</a>.</small></p>';

// Task.
$string['task:collectstoragestats'] = 'Collect storage statistics';

// Events.
$string['event:backupdeleted'] = 'Backup file deleted';
$string['event:filedeleted'] = 'File deleted via Storage 360';

// Privacy.
$string['privacy:metadata:history'] = 'Storage statistics history does not contain personal data.';
$string['privacy:metadata:history:timecreated'] = 'Timestamp of the snapshot.';
$string['privacy:metadata:history:total_size'] = 'Aggregate storage size at that time.';
$string['privacy:metadata:deletelog'] = 'Audit log of files deleted via Storage 360. Contains references to the file owner and the user who performed the deletion.';
$string['privacy:metadata:deletelog:owneruserid'] = 'User ID of the file owner.';
$string['privacy:metadata:deletelog:ownerfullname'] = 'Full name of the file owner at time of deletion.';
$string['privacy:metadata:deletelog:deletedby'] = 'User ID of the person who deleted the file.';
$string['privacy:metadata:deletelog:filename'] = 'Name of the deleted file.';
$string['privacy:metadata:deletelog:filesize'] = 'Size of the deleted file in bytes.';
$string['privacy:metadata:deletelog:timedeleted'] = 'Timestamp when the file was deleted.';
$string['privacy:deletelog:asowner'] = 'Files owned by you that were deleted';
$string['privacy:deletelog:asdeleter'] = 'Files you deleted';

// Common.
$string['bytes'] = 'B';
$string['kilobytes'] = 'KB';
$string['megabytes'] = 'MB';
$string['gigabytes'] = 'GB';
$string['terabytes'] = 'TB';
$string['nodata'] = 'No data available.';
$string['filter'] = 'Filter';
$string['reset'] = 'Reset';
$string['exportcsv'] = 'Export CSV';
$string['all'] = 'All';
$string['period:7days'] = 'Last 7 days';
$string['period:30days'] = 'Last 30 days';
$string['period:90days'] = 'Last 90 days';
$string['period:1year'] = 'Last year';
$string['period:all'] = 'All time';
$string['minsize:10mb'] = '> 10 MB';
$string['minsize:100mb'] = '> 100 MB';
$string['minsize:1gb'] = '> 1 GB';
$string['snapshot:lastupdated'] = 'Data as of: {$a}';
$string['nosnapshots'] = 'No analytics data is available yet. Please run an initial data collection to populate the dashboard. This may take a few minutes depending on the number of files.';
$string['collectnow'] = 'Collect data now';
$string['collect:title'] = 'Storage 360 - Data collection';
$string['collect:running'] = 'Data collection is running, please wait...';
$string['collect:done'] = 'Data collection completed successfully.';

// Integrity scan.
$string['task:integrityscan'] = 'File integrity scan';
$string['settings:scanbatchsize'] = 'Integrity scan batch size (per direction)';
$string['settings:scanbatchsize_desc'] = 'Number of contenthashes to scan from each end (newest + oldest) per cron run. Total files scanned per run = 2 x this value. Default: 10.';
$string['scan:progress_title'] = 'File integrity scan';
$string['scan:progress_detail'] = '{$a->scanned} / {$a->total} unique files scanned ({$a->percent}%)';
$string['scan:anomalies_found'] = '{$a} anomaly/anomalies found';
$string['scan:all_ok'] = 'All files verified - no anomalies';
$string['scan:missing_count'] = '{$a} missing';
$string['scan:mismatch_count'] = '{$a} size mismatch';
$string['scan:intrash_count'] = '{$a} in trash';
$string['scan:disk_column'] = 'Disk';
$string['scan:status_ok'] = 'File present on disk, size matches';
$string['scan:status_missing'] = 'File missing from disk';
$string['scan:status_mismatch'] = 'File size does not match database';
$string['scan:status_intrash'] = 'File is in trash directory';
$string['scan:status_pending'] = 'Not yet scanned';
$string['scan:reset'] = 'Reset scan';
$string['scan:reset_confirm'] = 'This will delete all scan results and start a new scan cycle. Continue?';
$string['scan:reset_done'] = 'Integrity scan has been reset. A new scan cycle will begin on the next cron run.';

// Orphan files.
$string['nav:orphans'] = 'Orphan files';
$string['orphans:title'] = 'Orphan files on disk';
$string['orphans:noorphans'] = 'No orphan files detected yet.';
$string['orphans:contenthash'] = 'Content hash';
$string['orphans:disksize'] = 'Size on disk';
$string['orphans:scanned'] = 'Detected on';
$string['orphans:filetype'] = 'Type';
$string['orphans:unknown_type'] = 'Unknown';
$string['orphans:download'] = 'Download';
$string['orphans:download_failed'] = 'Could not download file: file not found on disk.';
$string['orphans:delete'] = 'Delete';
$string['orphans:delete_confirm'] = 'Delete this orphan file from disk? This action cannot be undone.';
$string['orphans:deleted'] = 'Orphan file deleted successfully.';
$string['orphans:delete_failed'] = 'Could not delete orphan file.';
$string['orphans:still_referenced'] = 'This file is still referenced in the database and cannot be deleted.';
$string['orphans:whatare_title'] = 'What are orphan files?';
$string['orphans:whatare_body'] = 'These are files physically present on Moodle\'s disk but with no reference in the database. They can result from interrupted uploads, partial migrations, or application bugs. They waste disk space and can be safely deleted after review. The disk scan detects them by browsing Moodle\'s filedir directory.';
$string['orphans:total_count'] = '{$a} orphan file(s)';
$string['orphans:total_size'] = 'Total orphan size: {$a}';
$string['orphans:disk_scan_progress'] = 'Disk scan: directory {$a}';
$string['orphans:disk_scan_complete'] = 'Disk scan: complete';
$string['orphans:disk_scan_notstarted'] = 'Disk scan: not started yet';
$string['scan:orphan_count'] = '{$a} orphan(s)';
$string['scan:status_orphan'] = 'File on disk with no database reference';
