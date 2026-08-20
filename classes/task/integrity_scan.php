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
 * Scheduled task for incremental file integrity scanning.
 *
 * Scans files in a "pincer" pattern: N most-recent unscanned + N oldest
 * unscanned contenthashes per run. Results are stored in
 * local_storage360_scanresult (one row per unique contenthash).
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class integrity_scan extends \core\task\scheduled_task {

    /** @var int Status: file present and size matches. */
    const STATUS_OK = 0;
    /** @var int Status: file missing from both filedir and trashdir. */
    const STATUS_MISSING = 1;
    /** @var int Status: file exists but size does not match DB. */
    const STATUS_SIZE_MISMATCH = 2;
    /** @var int Status: file not in filedir but found in trashdir. */
    const STATUS_IN_TRASH = 3;
    /** @var int Status: file on disk with no database reference (orphan). */
    const STATUS_ORPHAN = 4;

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:integrityscan', 'local_storage360');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB, $CFG;

        $batchsize = (int) get_config('local_storage360', 'scanbatchsize');
        if ($batchsize <= 0) {
            $batchsize = 10;
        }

        // SHA1 of empty string — used by Moodle for directory entries and zero-byte files.
        $emptyhash = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

        // Count total unique contenthashes in mdl_files (excluding directories and empty hash).
        $totalunique = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT contenthash)
               FROM {files}
              WHERE filename != '.'
                AND contenthash != :emptyhash",
            ['emptyhash' => $emptyhash]
        );

        $scannedcount = (int) $DB->count_records('local_storage360_scanresult');

        mtrace("Storage 360 integrity scan: {$scannedcount}/{$totalunique} contenthashes already scanned.");

        // Phase 1: DB→Disk scan (skip if already complete).
        if ($scannedcount >= $totalunique) {
            mtrace('Storage 360 integrity scan: all contenthashes have been scanned. Scan cycle complete.');
        } else {
            // Get N newest unscanned contenthashes (pincer: recent end).
            $sql_recent = "SELECT sub.contenthash, sub.filesize_db, sub.max_timemodified
                             FROM (
                                 SELECT f.contenthash,
                                        MAX(f.filesize) AS filesize_db,
                                        MAX(f.timemodified) AS max_timemodified
                                   FROM {files} f
                                  WHERE f.filename != '.'
                                    AND f.contenthash != :emptyhash
                                    AND NOT EXISTS (
                                        SELECT 1 FROM {local_storage360_scanresult} sr
                                         WHERE sr.contenthash = f.contenthash
                                    )
                                  GROUP BY f.contenthash
                             ) sub
                            ORDER BY sub.max_timemodified DESC";

            $recentbatch = $DB->get_records_sql($sql_recent, ['emptyhash' => $emptyhash], 0, $batchsize);

            // Get N oldest unscanned contenthashes (pincer: oldest end).
            $sql_oldest = "SELECT sub.contenthash, sub.filesize_db, sub.max_timemodified
                             FROM (
                                 SELECT f.contenthash,
                                        MAX(f.filesize) AS filesize_db,
                                        MAX(f.timemodified) AS max_timemodified
                                   FROM {files} f
                                  WHERE f.filename != '.'
                                    AND f.contenthash != :emptyhash
                                    AND NOT EXISTS (
                                        SELECT 1 FROM {local_storage360_scanresult} sr
                                         WHERE sr.contenthash = f.contenthash
                                    )
                                  GROUP BY f.contenthash
                             ) sub
                            ORDER BY sub.max_timemodified ASC";

            $oldestbatch = $DB->get_records_sql($sql_oldest, ['emptyhash' => $emptyhash], 0, $batchsize);

            // Merge and deduplicate (overlap possible near end of scan cycle).
            $batch = [];
            foreach ($recentbatch as $row) {
                $batch[$row->contenthash] = $row;
            }
            foreach ($oldestbatch as $row) {
                if (!isset($batch[$row->contenthash])) {
                    $batch[$row->contenthash] = $row;
                }
            }

            if (!empty($batch)) {
                mtrace('Storage 360 integrity scan: processing ' . count($batch) . ' contenthash(es) this run...');

                // Scan each contenthash.
                $now = time();
                $counts = [
                    self::STATUS_OK => 0,
                    self::STATUS_MISSING => 0,
                    self::STATUS_SIZE_MISMATCH => 0,
                    self::STATUS_IN_TRASH => 0,
                ];

                foreach ($batch as $hash => $row) {
                    $hashpath = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
                    $filedir_path = $CFG->dataroot . '/filedir/' . $hashpath;
                    $trashdir_path = $CFG->dataroot . '/trashdir/' . $hashpath;

                    $on_disk = file_exists($filedir_path);
                    $in_trash = file_exists($trashdir_path);

                    $disksize = null;
                    if ($on_disk) {
                        $disksize = filesize($filedir_path);
                    } else if ($in_trash) {
                        $disksize = filesize($trashdir_path);
                    }

                    // Count DB references for this contenthash.
                    $refcount = (int) $DB->count_records('files', ['contenthash' => $hash]);

                    // Determine status.
                    if ($on_disk) {
                        if ($disksize !== false && (int) $disksize === (int) $row->filesize_db) {
                            $status = self::STATUS_OK;
                        } else {
                            $status = self::STATUS_SIZE_MISMATCH;
                        }
                    } else if ($in_trash) {
                        $status = self::STATUS_IN_TRASH;
                    } else {
                        $status = self::STATUS_MISSING;
                    }

                    // Insert result row.
                    $record = new \stdClass();
                    $record->contenthash = $hash;
                    $record->filesize_db = (int) $row->filesize_db;
                    $record->filesize_disk = ($disksize !== null && $disksize !== false) ? (int) $disksize : null;
                    $record->ref_count = $refcount;
                    $record->status = $status;
                    $record->timescanned = $now;

                    try {
                        $DB->insert_record('local_storage360_scanresult', $record);
                        $counts[$status]++;
                    } catch (\dml_exception $e) {
                        // Unique constraint violation: contenthash was scanned by a concurrent run.
                        mtrace("  Skipped duplicate: {$hash}");
                    }
                }

                mtrace(sprintf(
                    'Storage 360 integrity scan done: %d OK, %d missing, %d size mismatch, %d in trash.',
                    $counts[self::STATUS_OK],
                    $counts[self::STATUS_MISSING],
                    $counts[self::STATUS_SIZE_MISMATCH],
                    $counts[self::STATUS_IN_TRASH]
                ));
            } else {
                mtrace('Storage 360 integrity scan: no unscanned contenthashes found.');
            }
        }

        // Phase 2: Disk→DB orphan scan (always runs, regardless of Phase 1 status).
        $this->scan_orphans_from_disk();
    }

    /**
     * Scan filedir/ incrementally to detect orphan files not referenced in mdl_files.
     *
     * Uses a cursor stored in config to walk through filedir/XX/YY/ directories
     * sequentially across cron runs.
     */
    private function scan_orphans_from_disk(): void {
        global $DB, $CFG;

        $cursor = get_config('local_storage360', 'orphan_scan_cursor');
        if ($cursor === 'complete') {
            mtrace('Storage 360 disk scan: complete (all directories scanned).');
            return;
        }
        if (empty($cursor)) {
            $cursor = '00/00';
        }

        $batchsize = (int) get_config('local_storage360', 'scanbatchsize');
        if ($batchsize <= 0) {
            $batchsize = 10;
        }

        $filedir = $CFG->dataroot . '/filedir';
        if (!is_dir($filedir)) {
            mtrace('Storage 360 disk scan: filedir not found.');
            return;
        }

        $processed = 0;
        $orphans = 0;
        $known = 0;
        $skippeddirs = 0;
        $startcursor = $cursor;

        // Walk directories until we process at least one file or exhaust all dirs.
        while ($cursor !== 'complete') {
            $dirpath = $filedir . '/' . $cursor;

            if (!is_dir($dirpath)) {
                $cursor = $this->increment_cursor($cursor);
                $skippeddirs++;
                // Safety: avoid infinite loop if filedir is nearly empty.
                if ($skippeddirs > 65536) {
                    $cursor = 'complete';
                    break;
                }
                continue;
            }

            $entries = @scandir($dirpath);
            if ($entries === false) {
                $cursor = $this->increment_cursor($cursor);
                continue;
            }

            $now = time();
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                // Validate contenthash format (40 hex chars).
                if (!preg_match('/^[0-9a-f]{40}$/', $entry)) {
                    continue;
                }

                if ($processed >= $batchsize) {
                    break;
                }

                $hash = $entry;

                // Skip if already in scanresult.
                if ($DB->record_exists('local_storage360_scanresult', ['contenthash' => $hash])) {
                    continue;
                }

                $processed++;
                $filepath = $dirpath . '/' . $hash;
                $disksize = @filesize($filepath);
                if ($disksize === false) {
                    $disksize = 0;
                }

                // Check DB references.
                $refcount = (int) $DB->count_records('files', ['contenthash' => $hash]);

                if ($refcount > 0) {
                    // File is known to Moodle but not yet scanned by DB→disk phase.
                    // Scan it now: determine proper status.
                    $dbsize = (int) $DB->get_field_sql(
                        "SELECT MAX(filesize) FROM {files} WHERE contenthash = :hash",
                        ['hash' => $hash]
                    );
                    if ((int) $disksize === $dbsize) {
                        $status = self::STATUS_OK;
                    } else {
                        $status = self::STATUS_SIZE_MISMATCH;
                    }
                    $record = new \stdClass();
                    $record->contenthash = $hash;
                    $record->filesize_db = $dbsize;
                    $record->filesize_disk = (int) $disksize;
                    $record->ref_count = $refcount;
                    $record->status = $status;
                    $record->timescanned = $now;
                    $known++;
                } else {
                    // Orphan: file on disk with no DB reference.
                    $record = new \stdClass();
                    $record->contenthash = $hash;
                    $record->filesize_db = 0;
                    $record->filesize_disk = (int) $disksize;
                    $record->ref_count = 0;
                    $record->status = self::STATUS_ORPHAN;
                    $record->timescanned = $now;
                    $orphans++;
                }

                try {
                    $DB->insert_record('local_storage360_scanresult', $record);
                } catch (\dml_exception $e) {
                    // Already scanned by concurrent run.
                }
            }

            // Advance cursor after processing this directory.
            $cursor = $this->increment_cursor($cursor);

            // If we processed files, stop for this run.
            if ($processed > 0) {
                break;
            }
        }

        // Save cursor for next run.
        set_config('orphan_scan_cursor', $cursor, 'local_storage360');

        mtrace(sprintf(
            'Storage 360 disk scan [%s→%s]: %d files checked, %d known, %d orphans.',
            $startcursor, $cursor, $processed, $known, $orphans
        ));
    }

    /**
     * Increment the filedir cursor to the next XX/YY directory.
     *
     * @param string $cursor Current cursor in "XX/YY" format (hex).
     * @return string Next cursor or "complete" if all directories have been scanned.
     */
    private function increment_cursor(string $cursor): string {
        $parts = explode('/', $cursor);
        if (count($parts) !== 2) {
            return 'complete';
        }

        $hi = hexdec($parts[0]);
        $lo = hexdec($parts[1]);

        $lo++;
        if ($lo > 0xff) {
            $lo = 0;
            $hi++;
        }
        if ($hi > 0xff) {
            return 'complete';
        }

        return sprintf('%02x/%02x', $hi, $lo);
    }
}
