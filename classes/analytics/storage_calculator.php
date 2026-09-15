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

namespace local_storage360\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Core storage calculation engine.
 *
 * Handles all storage-related queries and computations for the plugin.
 * Analytics methods read from pre-computed snapshot tables when available,
 * with automatic fallback to live queries before the first cron run.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class storage_calculator {

    /** @var \cache Cache instance. */
    private $cache;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->cache = \cache::make('local_storage360', 'analysis');
    }

    /**
     * Get the timestamp of the most recent snapshot run.
     *
     * @return int|null Unix timestamp or null if no snapshots exist.
     */
    private function get_latest_snapshot_time(): ?int {
        global $DB;

        static $snaptime = -1;
        if ($snaptime !== -1) {
            return $snaptime;
        }

        $val = $DB->get_field_sql("SELECT MAX(timecreated) FROM {local_storage360_history}");
        $snaptime = $val ? (int) $val : null;

        return $snaptime;
    }

    /**
     * Get global storage statistics.
     *
     * @param bool $usecache Whether to use cached results.
     * @return object {total_size, total_files, avg_size}
     */
    public function get_global_stats(bool $usecache = true): object {
        if ($usecache && ($data = $this->cache->get('global_stats'))) {
            return $data;
        }

        global $DB;

        $snaptime = $this->get_latest_snapshot_time();

        if ($snaptime && $usecache) {
            $row = $DB->get_record_sql(
                "SELECT total_files, total_size
                   FROM {local_storage360_history}
                  WHERE timecreated = :snaptime",
                ['snaptime' => $snaptime]
            );
            $data = (object) [
                'total_files' => (int) $row->total_files,
                'total_size'  => (int) $row->total_size,
                'avg_size'    => $row->total_files > 0
                    ? round($row->total_size / $row->total_files, 2)
                    : 0.0,
            ];
        } else {
            // Live query (first install or cron calling with usecache=false).
            // Deduplicated: each unique contenthash is counted once (actual disk footprint).
            $sql = "SELECT COUNT(*) as total_files,
                           COALESCE(SUM(filesize), 0) as total_size,
                           COALESCE(AVG(filesize), 0) as avg_size
                      FROM (
                          SELECT contenthash, MAX(filesize) as filesize
                            FROM {files}
                           WHERE filename != '.'
                           GROUP BY contenthash
                      ) dedup";

            $data = $DB->get_record_sql($sql);
            $data->total_files = (int) $data->total_files;
            $data->total_size = (int) $data->total_size;
            $data->avg_size = (float) $data->avg_size;
        }

        $this->cache->set('global_stats', $data);
        return $data;
    }

    /**
     * Get disk space information.
     *
     * @return object {total, free, used, percent} or null if unavailable.
     */
    public function get_disk_space(): ?object {
        global $CFG;

        $method = get_config('local_storage360', 'diskspacemethod') ?: 'auto';

        if ($method === 'dbonly') {
            return null;
        }

        if ($method === 'manual') {
            $manualgb = (int) get_config('local_storage360', 'manualdisksize');
            if ($manualgb <= 0) {
                return null;
            }
            $total = $manualgb * 1073741824; // GB to bytes.
            $stats = $this->get_global_stats();
            $used = $stats->total_size;
            return (object) [
                'total' => $total,
                'free' => max(0, $total - $used),
                'used' => $used,
                'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
            ];
        }

        // Auto: read from filesystem.
        $dataroot = $CFG->dataroot;
        $total = @disk_total_space($dataroot);
        $free = @disk_free_space($dataroot);

        if ($total === false || $free === false) {
            return null;
        }

        $used = $total - $free;

        return (object) [
            'total' => (int) $total,
            'free' => (int) $free,
            'used' => (int) $used,
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get monthly growth rate.
     *
     * @return object {current_month_size, previous_month_size, growth_bytes, growth_percent}
     */
    public function get_growth_rate(): object {
        if ($data = $this->cache->get('growth_rate')) {
            return $data;
        }

        global $DB;

        $now = time();
        $startofmonth = mktime(0, 0, 0, (int) date('n', $now), 1, (int) date('Y', $now));
        $startofprevmonth = mktime(0, 0, 0, (int) date('n', $now) - 1, 1, (int) date('Y', $now));

        $snaptime = $this->get_latest_snapshot_time();
        $ispending = false;

        // Next cron run at 3am.
        $next3am = mktime(3, 0, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));
        if ($next3am <= $now) {
            $next3am = mktime(3, 0, 0, (int) date('n', $now), (int) date('j', $now) + 1, (int) date('Y', $now));
        }

        // Data will be comparable from the 1st of next month (need snapshots spanning two calendar months).
        $dataavailablefrom = mktime(0, 0, 0, (int) date('n', $now) + 1, 1, (int) date('Y', $now));

        if ($snaptime) {
            // Current total from latest snapshot.
            $currenttotal = (int) $DB->get_field_sql(
                "SELECT total_size FROM {local_storage360_history}
                  WHERE timecreated = :snaptime",
                ['snaptime' => $snaptime]
            );

            // Total at start of current month.
            $startraw = $DB->get_field_sql(
                "SELECT total_size FROM {local_storage360_history}
                  WHERE timecreated <= :ts
                  ORDER BY timecreated DESC",
                ['ts' => $startofmonth]
            );
            $ispending = ($startraw === false);
            $starttotal = $ispending ? $currenttotal : (int) $startraw;

            // Total at start of previous month.
            $prevstarttotal = $DB->get_field_sql(
                "SELECT total_size FROM {local_storage360_history}
                  WHERE timecreated <= :ts
                  ORDER BY timecreated DESC",
                ['ts' => $startofprevmonth]
            );
            $prevstarttotal = $prevstarttotal !== false ? (int) $prevstarttotal : $starttotal;

            $current = $currenttotal - $starttotal;
            $previous = $starttotal - $prevstarttotal;
        } else {
            // Fallback: live queries (deduplicated).
            $sql = "SELECT COALESCE(SUM(filesize), 0) as month_size
                      FROM (
                          SELECT contenthash, MAX(filesize) as filesize
                            FROM {files}
                           WHERE filename != '.'
                             AND timecreated >= :start
                             AND timecreated < :end
                           GROUP BY contenthash
                      ) dedup";

            $current = (int) $DB->get_field_sql($sql, ['start' => $startofmonth, 'end' => $now]);
            $previous = (int) $DB->get_field_sql($sql, ['start' => $startofprevmonth, 'end' => $startofmonth]);
        }

        $data = (object) [
            'current_month_size'  => $current,
            'previous_month_size' => $previous,
            'growth_bytes'        => $current - $previous,
            'growth_percent'      => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0,
            'data_pending'        => $ispending,
            'next_update'         => $next3am,
            'data_available_from' => $dataavailablefrom,
        ];

        $this->cache->set('growth_rate', $data);
        return $data;
    }

    /**
     * Get top N courses by total storage.
     *
     * @param int $limit Number of results.
     * @return array Array of objects with course info and storage data.
     */
    public function get_top_courses(int $limit = 5): array {
        global $DB;

        $cachekey = 'top_courses_' . $limit;
        if ($data = $this->cache->get($cachekey)) {
            return $data;
        }

        $latestsnap = $DB->get_field_sql(
            "SELECT MAX(timecreated) FROM {local_storage360_course_snap}"
        );

        if ($latestsnap) {
            $sql = "SELECT c.id,
                           c.fullname,
                           c.shortname,
                           s.file_count,
                           s.total_size
                      FROM {local_storage360_course_snap} s
                      JOIN {course} c ON c.id = s.courseid
                     WHERE s.timecreated = :snaptime
                       AND s.total_size > 0
                     ORDER BY s.total_size DESC";

            $data = array_values($DB->get_records_sql($sql, ['snaptime' => $latestsnap], 0, $limit));
        } else {
            // Fallback: live query (first install before cron runs).
            $pathconcat = $DB->sql_concat('coursectx.path', "'/%'");

            $sql = "SELECT c.id,
                           c.fullname,
                           c.shortname,
                           COUNT(DISTINCT f.id) as file_count,
                           COALESCE(SUM(f.filesize), 0) as total_size
                      FROM {course} c
                      JOIN {context} coursectx ON coursectx.instanceid = c.id AND coursectx.contextlevel = :ctxlevel
                 LEFT JOIN {context} ctx ON (ctx.id = coursectx.id OR ctx.path LIKE {$pathconcat})
                      JOIN {files} f ON f.contextid = ctx.id AND f.filename != '.'
                     GROUP BY c.id, c.fullname, c.shortname
                     ORDER BY total_size DESC";

            $data = array_values($DB->get_records_sql($sql, ['ctxlevel' => CONTEXT_COURSE], 0, $limit));
        }

        $this->cache->set($cachekey, $data);
        return $data;
    }

    /**
     * Get storage breakdown by component.
     *
     * @param bool $usecache Whether to use cached results.
     * @return array Array of objects {component, filearea, file_count, total_size, ...}
     */
    public function get_by_component(bool $usecache = true): array {
        if ($usecache && ($data = $this->cache->get('by_component'))) {
            return $data;
        }

        global $DB;

        $snaptime = $this->get_latest_snapshot_time();

        if ($snaptime && $usecache) {
            // Read enriched JSON from latest history row.
            $json = $DB->get_field_sql(
                "SELECT by_component FROM {local_storage360_history}
                  WHERE timecreated = :snaptime",
                ['snaptime' => $snaptime]
            );
            $componentdata = json_decode($json, true);

            $data = [];
            if (!empty($componentdata)) {
                foreach ($componentdata as $key => $value) {
                    $parts = explode('/', $key, 2);
                    $component = $parts[0];
                    $filearea = $parts[1] ?? '';

                    // Handle both old format (integer) and new format (object).
                    if (is_array($value)) {
                        $data[] = (object) [
                            'id'          => $key,
                            'component'   => $component,
                            'filearea'    => $filearea,
                            'file_count'  => (int) ($value['file_count'] ?? 0),
                            'total_size'  => (int) ($value['total_size'] ?? 0),
                            'avg_size'    => (float) ($value['avg_size'] ?? 0),
                            'max_size'    => (int) ($value['max_size'] ?? 0),
                            'oldest_file' => (int) ($value['oldest_file'] ?? 0),
                            'newest_file' => (int) ($value['newest_file'] ?? 0),
                        ];
                    } else {
                        // Old format: value is just total_size.
                        $data[] = (object) [
                            'id'          => $key,
                            'component'   => $component,
                            'filearea'    => $filearea,
                            'file_count'  => 0,
                            'total_size'  => (int) $value,
                            'avg_size'    => 0,
                            'max_size'    => 0,
                            'oldest_file' => 0,
                            'newest_file' => 0,
                        ];
                    }
                }
                usort($data, function($a, $b) {
                    return $b->total_size <=> $a->total_size;
                });
            }
        } else {
            // Live query (first install or cron calling with usecache=false).
            $concatid = $DB->sql_concat('f.component', "'/'", 'f.filearea');

            $sql = "SELECT {$concatid} as id,
                           f.component,
                           f.filearea,
                           COUNT(*) as file_count,
                           COALESCE(SUM(f.filesize), 0) as total_size,
                           COALESCE(AVG(f.filesize), 0) as avg_size,
                           COALESCE(MAX(f.filesize), 0) as max_size,
                           MIN(f.timecreated) as oldest_file,
                           MAX(f.timecreated) as newest_file
                      FROM {files} f
                     WHERE f.filename != '.'
                     GROUP BY f.component, f.filearea
                     ORDER BY total_size DESC";

            $data = array_values($DB->get_records_sql($sql));
        }

        $this->cache->set('by_component', $data);
        return $data;
    }

    /**
     * Get storage by course with full details.
     *
     * @param int $page Page number (0-based).
     * @param int $perpage Results per page.
     * @param string $sort Sort field.
     * @param string $dir Sort direction (ASC/DESC).
     * @param int $categoryid Filter by category (0 = all).
     * @param int $minsize Minimum total size in bytes (0 = no filter).
     * @param int $visible Filter: -1 = all, 0 = hidden, 1 = visible.
     * @param string $search Search term.
     * @return object {records, totalcount}
     */
    public function get_courses_storage(
        int $page = 0,
        int $perpage = 50,
        string $sort = 'total_size',
        string $dir = 'DESC',
        int $categoryid = 0,
        int $minsize = 0,
        int $visible = -1,
        string $search = ''
    ): object {
        global $DB;

        $allowedsorts = ['total_size', 'file_count', 'fullname', 'backup_size', 'assignment_size'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'total_size';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $latestsnap = $DB->get_field_sql(
            "SELECT MAX(timecreated) FROM {local_storage360_course_snap}"
        );

        if ($latestsnap) {
            $result = $this->get_courses_storage_from_snapshot(
                $latestsnap, $page, $perpage, $sort, $dir,
                $categoryid, $minsize, $visible, $search
            );
        } else {
            $result = $this->get_courses_storage_live(
                $page, $perpage, $sort, $dir,
                $categoryid, $minsize, $visible, $search
            );
        }

        // Batch-load student counts.
        $courseids = array_column($result->records, 'id');
        $studentcounts = [];
        if (!empty($courseids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $studentcounts = $DB->get_records_sql_menu(
                "SELECT e.courseid, COUNT(DISTINCT ue.userid)
                   FROM {enrol} e
                   JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  WHERE e.courseid {$insql}
                  GROUP BY e.courseid",
                $inparams
            );
        }
        foreach ($result->records as $record) {
            $record->student_count = (int) ($studentcounts[$record->id] ?? 0);
        }

        return $result;
    }

    /**
     * Get course storage from snapshot table.
     */
    private function get_courses_storage_from_snapshot(
        int $snaptime, int $page, int $perpage,
        string $sort, string $dir,
        int $categoryid, int $minsize, int $visible, string $search
    ): object {
        global $DB;

        $where = 'c.id != 1';
        $params = ['snaptime' => $snaptime];

        if ($categoryid > 0) {
            $where .= ' AND c.category = :categoryid';
            $params['categoryid'] = $categoryid;
        }
        if ($visible >= 0) {
            $where .= ' AND c.visible = :visible';
            $params['visible'] = $visible;
        }
        if ($search !== '') {
            if (ctype_digit($search)) {
                $where .= ' AND c.id = :searchid';
                $params['searchid'] = (int) $search;
            } else {
                $searchlike = '%' . $DB->sql_like_escape($search) . '%';
                $where .= ' AND (c.fullname LIKE :searchfull OR c.shortname LIKE :searchshort)';
                $params['searchfull'] = $searchlike;
                $params['searchshort'] = $searchlike;
            }
        }
        if ($minsize > 0) {
            $where .= ' AND s.total_size >= :minsize';
            $params['minsize'] = $minsize;
        }

        $sortcol = ($sort === 'fullname') ? "c.{$sort}" : "s.{$sort}";

        $sql = "SELECT c.id,
                       c.fullname,
                       c.shortname,
                       c.category,
                       c.visible,
                       s.file_count,
                       s.total_size,
                       s.backup_size,
                       s.assignment_size
                  FROM {local_storage360_course_snap} s
                  JOIN {course} c ON c.id = s.courseid
                 WHERE s.timecreated = :snaptime
                   AND {$where}
                 ORDER BY {$sortcol} {$dir}";

        $records = array_values($DB->get_records_sql($sql, $params, $page * $perpage, $perpage));

        $countsql = "SELECT COUNT(*)
                       FROM {local_storage360_course_snap} s
                       JOIN {course} c ON c.id = s.courseid
                      WHERE s.timecreated = :snaptime
                        AND {$where}";
        $totalcount = $DB->count_records_sql($countsql, $params);

        return (object) ['records' => $records, 'totalcount' => $totalcount];
    }

    /**
     * Get course storage via live query (fallback before first cron).
     */
    private function get_courses_storage_live(
        int $page, int $perpage, string $sort, string $dir,
        int $categoryid, int $minsize, int $visible, string $search
    ): object {
        global $DB;

        $where = '';
        $params = ['ctxlevel' => CONTEXT_COURSE];

        if ($categoryid > 0) {
            $where .= ' AND c.category = :categoryid';
            $params['categoryid'] = $categoryid;
        }
        if ($visible >= 0) {
            $where .= ' AND c.visible = :visible';
            $params['visible'] = $visible;
        }
        if ($search !== '') {
            if (ctype_digit($search)) {
                $where .= ' AND c.id = :searchid';
                $params['searchid'] = (int) $search;
            } else {
                $searchlike = '%' . $DB->sql_like_escape($search) . '%';
                $where .= ' AND (c.fullname LIKE :searchfull OR c.shortname LIKE :searchshort)';
                $params['searchfull'] = $searchlike;
                $params['searchshort'] = $searchlike;
            }
        }

        $havingsql = '';
        if ($minsize > 0) {
            $havingsql = 'HAVING COALESCE(SUM(f.filesize), 0) >= :minsize';
            $params['minsize'] = $minsize;
        }

        $pathconcat = $DB->sql_concat('coursectx.path', "'/%'");

        $sql = "SELECT c.id,
                       c.fullname,
                       c.shortname,
                       c.category,
                       c.visible,
                       COUNT(DISTINCT f.id) as file_count,
                       COALESCE(SUM(f.filesize), 0) as total_size,
                       COALESCE(SUM(CASE WHEN f.component = 'backup' THEN f.filesize ELSE 0 END), 0) as backup_size,
                       COALESCE(SUM(CASE WHEN f.component LIKE 'assign%' THEN f.filesize ELSE 0 END), 0) as assignment_size
                  FROM {course} c
                  JOIN {context} coursectx ON coursectx.instanceid = c.id AND coursectx.contextlevel = :ctxlevel
             LEFT JOIN {context} ctx ON (ctx.id = coursectx.id OR ctx.path LIKE {$pathconcat})
             LEFT JOIN {files} f ON f.contextid = ctx.id AND f.filename != '.'
                 WHERE c.id != 1 {$where}
                 GROUP BY c.id, c.fullname, c.shortname, c.category, c.visible
                 {$havingsql}
                 ORDER BY {$sort} {$dir}";

        $records = array_values($DB->get_records_sql($sql, $params, $page * $perpage, $perpage));

        $countsql = "SELECT COUNT(*) FROM (
                        SELECT c.id
                          FROM {course} c
                          JOIN {context} coursectx ON coursectx.instanceid = c.id AND coursectx.contextlevel = :ctxlevel
                     LEFT JOIN {context} ctx ON (ctx.id = coursectx.id OR ctx.path LIKE {$pathconcat})
                     LEFT JOIN {files} f ON f.contextid = ctx.id AND f.filename != '.'
                         WHERE c.id != 1 {$where}
                         GROUP BY c.id
                         {$havingsql}
                     ) subq";

        $totalcount = $DB->count_records_sql($countsql, $params);

        return (object) ['records' => $records, 'totalcount' => $totalcount];
    }

    /**
     * Get storage by user with details.
     *
     * @param int $page Page number (0-based).
     * @param int $perpage Results per page.
     * @param string $sort Sort field.
     * @param string $dir Sort direction.
     * @param int $minsize Minimum total size in bytes.
     * @param string $search Search term for username/email.
     * @return object {records, totalcount}
     */
    public function get_users_storage(
        int $page = 0,
        int $perpage = 50,
        string $sort = 'total_size',
        string $dir = 'DESC',
        int $minsize = 0,
        string $search = ''
    ): object {
        global $DB;

        $allowedsorts = ['total_size', 'private_files', 'draft_files', 'assignment_files', 'lastname', 'last_upload'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'total_size';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $latestsnap = $DB->get_field_sql(
            "SELECT MAX(timecreated) FROM {local_storage360_user_snap}"
        );

        if ($latestsnap) {
            return $this->get_users_storage_from_snapshot(
                $latestsnap, $page, $perpage, $sort, $dir, $minsize, $search
            );
        }

        return $this->get_users_storage_live($page, $perpage, $sort, $dir, $minsize, $search);
    }

    /**
     * Get user storage from snapshot table.
     */
    private function get_users_storage_from_snapshot(
        int $snaptime, int $page, int $perpage,
        string $sort, string $dir, int $minsize, string $search
    ): object {
        global $DB;

        $where = 'u.deleted = 0';
        $params = ['snaptime' => $snaptime];

        if (!empty($search)) {
            if (ctype_digit($search)) {
                $where .= " AND u.id = :searchid";
                $params['searchid'] = (int) $search;
            } else {
                $searchlike = '%' . $DB->sql_like_escape($search) . '%';
                $where .= " AND (u.firstname LIKE :search1
                              OR u.lastname LIKE :search2
                              OR u.email LIKE :search3
                              OR u.username LIKE :search4)";
                $params['search1'] = $searchlike;
                $params['search2'] = $searchlike;
                $params['search3'] = $searchlike;
                $params['search4'] = $searchlike;
            }
        }

        if ($minsize > 0) {
            $where .= " AND s.total_size >= :minsize";
            $params['minsize'] = $minsize;
        }

        $sortcol = ($sort === 'lastname') ? "u.{$sort}" : "s.{$sort}";

        $usernamefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT u.id,
                       {$usernamefields},
                       u.email,
                       s.private_files,
                       s.draft_files,
                       s.assignment_files,
                       s.total_size,
                       s.last_upload
                  FROM {local_storage360_user_snap} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.timecreated = :snaptime
                   AND {$where}
                 ORDER BY {$sortcol} {$dir}";

        $records = array_values($DB->get_records_sql($sql, $params, $page * $perpage, $perpage));

        $countsql = "SELECT COUNT(*)
                       FROM {local_storage360_user_snap} s
                       JOIN {user} u ON u.id = s.userid
                      WHERE s.timecreated = :snaptime
                        AND {$where}";
        $totalcount = $DB->count_records_sql($countsql, $params);

        return (object) ['records' => $records, 'totalcount' => $totalcount];
    }

    /**
     * Get user storage via live query (fallback before first cron).
     */
    private function get_users_storage_live(
        int $page, int $perpage, string $sort, string $dir,
        int $minsize, string $search
    ): object {
        global $DB;

        $where = '';
        $params = [];

        if (!empty($search)) {
            if (ctype_digit($search)) {
                $where .= " AND u.id = :searchid";
                $params['searchid'] = (int) $search;
            } else {
                $searchlike = '%' . $DB->sql_like_escape($search) . '%';
                $where .= " AND (u.firstname LIKE :search1
                              OR u.lastname LIKE :search2
                              OR u.email LIKE :search3
                              OR u.username LIKE :search4)";
                $params['search1'] = $searchlike;
                $params['search2'] = $searchlike;
                $params['search3'] = $searchlike;
                $params['search4'] = $searchlike;
            }
        }

        $havingsql = '';
        if ($minsize > 0) {
            $havingsql = 'HAVING COALESCE(SUM(f.filesize), 0) >= :minsize';
            $params['minsize'] = $minsize;
        }

        $usernamefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT u.id,
                       {$usernamefields},
                       u.email,
                       COALESCE(SUM(CASE WHEN f.component = 'user' AND f.filearea = 'private'
                                    THEN f.filesize ELSE 0 END), 0) as private_files,
                       COALESCE(SUM(CASE WHEN f.component = 'user' AND f.filearea = 'draft'
                                    THEN f.filesize ELSE 0 END), 0) as draft_files,
                       COALESCE(SUM(CASE WHEN f.component = 'assignsubmission_file'
                                    THEN f.filesize ELSE 0 END), 0) as assignment_files,
                       COALESCE(SUM(f.filesize), 0) as total_size,
                       MAX(f.timecreated) as last_upload
                  FROM {user} u
             LEFT JOIN {files} f ON f.userid = u.id AND f.filename != '.'
                 WHERE u.deleted = 0 {$where}
                 GROUP BY u.id, {$usernamefields}, u.email
                 {$havingsql}
                 ORDER BY {$sort} {$dir}";

        $records = array_values($DB->get_records_sql($sql, $params, $page * $perpage, $perpage));

        $countsql = "SELECT COUNT(*) FROM (
                        SELECT u.id
                          FROM {user} u
                     LEFT JOIN {files} f ON f.userid = u.id AND f.filename != '.'
                         WHERE u.deleted = 0 {$where}
                         GROUP BY u.id
                         {$havingsql}
                     ) subq";

        $totalcount = $DB->count_records_sql($countsql, $params);

        return (object) ['records' => $records, 'totalcount' => $totalcount];
    }

    /**
     * Get monthly additions for timeline charts.
     *
     * @param int $months Number of months to look back.
     * @return array [{month => 'YYYY-MM', files_added => int, size_added => int}, ...]
     */
    public function get_monthly_additions(int $months = 24): array {
        global $DB;

        $cachekey = 'monthly_additions_' . $months;
        if ($data = $this->cache->get($cachekey)) {
            return $data;
        }

        $cutoff = strtotime("-{$months} months");

        // Always query file creation dates directly for true retrospective analysis.
        // This allows viewing history before the plugin was installed.
        $sql = "SELECT contenthash, MAX(filesize) as filesize, MIN(timecreated) as first_created
                  FROM {files}
                 WHERE filename != '.'
                   AND timecreated >= :cutoff
                 GROUP BY contenthash
                 ORDER BY first_created ASC";

        $records = $DB->get_recordset_sql($sql, ['cutoff' => $cutoff]);

        $bymonth = [];
        foreach ($records as $record) {
            $month = date('Y-m', $record->first_created);
            if (!isset($bymonth[$month])) {
                $bymonth[$month] = (object) [
                    'month'       => $month,
                    'files_added' => 0,
                    'size_added'  => 0,
                ];
            }
            $bymonth[$month]->files_added++;
            $bymonth[$month]->size_added += (int) $record->filesize;
        }
        $records->close();

        $data = array_values($bymonth);

        $this->cache->set($cachekey, $data);
        return $data;
    }

    /**
     * Get historical snapshots from the history table.
     *
     * @param int $limit Max records to return.
     * @return array
     */
    public function get_history(int $limit = 365): array {
        global $DB;

        return array_values($DB->get_records('local_storage360_history', null, 'timecreated ASC', '*', 0, $limit));
    }

    /**
     * Get backup files list for cleanup page.
     *
     * @param int $olderthandays Only backups older than X days (0 = all).
     * @param int $largerthanmb Only backups larger than X MB (0 = all).
     * @param int $page Page number.
     * @param int $perpage Results per page.
     * @return object {records, totalcount}
     */
    public function get_backups(int $olderthandays = 0, int $largerthanmb = 0, int $page = 0, int $perpage = 50): object {
        global $DB;

        $where = "f.component = 'backup'
                  AND f.filearea IN ('course', 'automated', 'activity')
                  AND f.filename != '.'";
        $params = [];

        if ($olderthandays > 0) {
            $cutoff = time() - ($olderthandays * 86400);
            $where .= ' AND f.timecreated < :cutoff';
            $params['cutoff'] = $cutoff;
        }
        if ($largerthanmb > 0) {
            $minbytes = $largerthanmb * 1048576;
            $where .= ' AND f.filesize >= :minbytes';
            $params['minbytes'] = $minbytes;
        }

        $sql = "SELECT f.id, f.filename, f.filesize, f.filearea, f.timecreated,
                       f.contextid, f.component,
                       ctx.instanceid as courseid
                  FROM {files} f
             LEFT JOIN {context} ctx ON f.contextid = ctx.id AND ctx.contextlevel = :ctxlevel
                 WHERE {$where}
                 ORDER BY f.filesize DESC";
        $params['ctxlevel'] = CONTEXT_COURSE;

        $records = array_values($DB->get_records_sql($sql, $params, $page * $perpage, $perpage));

        // Batch-load course names.
        $courseids = array_unique(array_filter(array_column($records, 'courseid')));
        $coursenames = [];
        if (!empty($courseids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $coursenames = $DB->get_records_sql_menu(
                "SELECT id, fullname FROM {course} WHERE id {$insql}", $inparams
            );
        }
        foreach ($records as $record) {
            $record->coursename = !empty($record->courseid) && isset($coursenames[$record->courseid])
                ? $coursenames[$record->courseid] : '-';
        }

        $countsql = "SELECT COUNT(*)
                       FROM {files} f
                      WHERE {$where}";
        $countparams = $params;
        unset($countparams['ctxlevel']);
        $totalcount = $DB->count_records_sql($countsql, $countparams);

        return (object) ['records' => $records, 'totalcount' => $totalcount];
    }

    /**
     * Get old draft files for cleanup.
     *
     * @param int $olderthandays Drafts older than X days.
     * @param int $page Page number.
     * @param int $perpage Results per page.
     * @return object {records, totalcount}
     */
    public function get_old_drafts(int $olderthandays = 30, int $page = 0, int $perpage = 50): object {
        global $DB;

        $cutoff = time() - ($olderthandays * 86400);

        $sql = "SELECT f.id, f.filename, f.filesize, f.timecreated, f.timemodified,
                       f.userid,
                       u.firstname, u.lastname, u.email
                  FROM {files} f
             LEFT JOIN {user} u ON f.userid = u.id
                 WHERE f.component = 'user'
                   AND f.filearea = 'draft'
                   AND f.filename != '.'
                   AND f.timemodified < :cutoff
                 ORDER BY f.filesize DESC";

        $records = array_values($DB->get_records_sql($sql, ['cutoff' => $cutoff], $page * $perpage, $perpage));

        $countsql = "SELECT COUNT(*)
                       FROM {files} f
                      WHERE f.component = 'user'
                        AND f.filearea = 'draft'
                        AND f.filename != '.'
                        AND f.timemodified < :cutoff";

        $totalcount = $DB->count_records_sql($countsql, ['cutoff' => $cutoff]);

        return (object) ['records' => $records, 'totalcount' => $totalcount];
    }

    /**
     * Get individual files with filters.
     *
     * @param int $page Page number (0-based).
     * @param int $perpage Results per page.
     * @param string $sort Sort field.
     * @param string $dir Sort direction.
     * @param int $courseid Filter by course (0 = all).
     * @param int $userid Filter by user (0 = all).
     * @param string $component Filter by component ('' = all).
     * @param string $filearea Filter by filearea ('' = all).
     * @param string $search Search in filename.
     * @param string $mimetype Filter by MIME type prefix ('' = all).
     * @return object {records, totalcount}
     */
    public function get_files(
        int $page = 0,
        int $perpage = 50,
        string $sort = 'filesize',
        string $dir = 'DESC',
        int $courseid = 0,
        int $userid = 0,
        string $component = '',
        string $filearea = '',
        string $search = '',
        string $mimetype = ''
    ): object {
        global $DB;

        $allowedsorts = ['filesize', 'filename', 'timecreated', 'component', 'mimetype'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'filesize';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = "f.filename != '.'";
        $params = [];

        if ($courseid > 0) {
            $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
            if ($coursecontext) {
                $where .= " AND (ctx.id = :ctxid OR ctx.path LIKE :ctxpath)";
                $params['ctxid'] = $coursecontext->id;
                $params['ctxpath'] = $coursecontext->path . '/%';
            }
        }
        if ($userid > 0) {
            $where .= " AND f.userid = :userid";
            $params['userid'] = $userid;
        }
        if ($component !== '') {
            $where .= " AND f.component = :component";
            $params['component'] = $component;
        }
        if ($filearea !== '') {
            $where .= " AND f.filearea = :filearea";
            $params['filearea'] = $filearea;
        }
        if ($search !== '') {
            $where .= " AND f.filename LIKE :search";
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
        }
        if ($mimetype !== '') {
            $where .= " AND f.mimetype LIKE :mimetype";
            $params['mimetype'] = $DB->sql_like_escape($mimetype) . '%';
        }

        $sql = "SELECT f.id, f.contenthash, f.filename, f.filepath, f.filesize,
                       f.mimetype, f.component, f.filearea, f.itemid,
                       f.timecreated, f.timemodified, f.userid,
                       f.contextid
                  FROM {files} f
             LEFT JOIN {context} ctx ON f.contextid = ctx.id
                 WHERE {$where}
                 ORDER BY f.{$sort} {$dir}";

        $records = array_values($DB->get_records_sql($sql, $params, $page * $perpage, $perpage));

        // Batch-load user names.
        $userids = array_unique(array_filter(array_column($records, 'userid')));
        $users = [];
        if (!empty($userids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $usernamefields = implode(', ', array_merge(['id'], \core_user\fields::for_name()->get_required_fields()));
            $users = $DB->get_records_sql(
                "SELECT {$usernamefields} FROM {user} WHERE id {$insql}",
                $inparams
            );
        }

        // Batch-resolve contexts to courses.
        $contextids = array_unique(array_filter(array_column($records, 'contextid')));
        $ctxcourses = [];
        $contexts = [];
        $moduleinfos = [];
        if (!empty($contextids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
            $contexts = $DB->get_records_sql(
                "SELECT id, contextlevel, instanceid, path FROM {context} WHERE id {$insql}",
                $inparams
            );

            $ancestorids = [];
            foreach ($contexts as $ctx) {
                foreach (explode('/', trim($ctx->path, '/')) as $part) {
                    $ancestorids[(int) $part] = true;
                }
            }

            list($insql2, $inparams2) = $DB->get_in_or_equal(array_keys($ancestorids), SQL_PARAMS_NAMED);
            $coursectxs = $DB->get_records_sql(
                "SELECT ctx.id, c.id as courseid, c.fullname
                   FROM {context} ctx
                   JOIN {course} c ON c.id = ctx.instanceid
                  WHERE ctx.id {$insql2} AND ctx.contextlevel = :ctxlevel",
                array_merge($inparams2, ['ctxlevel' => CONTEXT_COURSE])
            );

            foreach ($contexts as $ctx) {
                $ctxcourses[$ctx->id] = (object) ['courseid' => 0, 'coursename' => '-'];
                foreach (explode('/', trim($ctx->path, '/')) as $part) {
                    if (isset($coursectxs[(int) $part])) {
                        $ctxcourses[$ctx->id]->courseid = (int) $coursectxs[(int) $part]->courseid;
                        $ctxcourses[$ctx->id]->coursename = $coursectxs[(int) $part]->fullname;
                        break;
                    }
                }
            }

            // Batch-load module type for module-level contexts.
            $modulecmids = [];
            foreach ($contexts as $ctx) {
                if ((int) $ctx->contextlevel === CONTEXT_MODULE) {
                    $modulecmids[(int) $ctx->instanceid] = true;
                }
            }
            if (!empty($modulecmids)) {
                list($insqlmod, $inparamsmod) = $DB->get_in_or_equal(array_keys($modulecmids), SQL_PARAMS_NAMED);
                $moduleinfos = $DB->get_records_sql(
                    "SELECT cm.id, m.name as modtype
                       FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module
                      WHERE cm.id {$insqlmod}",
                    $inparamsmod
                );
            }
        }

        // Enrich records.
        foreach ($records as $record) {
            $record->userfullname = (!empty($record->userid) && isset($users[$record->userid]))
                ? fullname($users[$record->userid]) : '-';

            if (isset($ctxcourses[$record->contextid])) {
                $record->resolvedcourseid = $ctxcourses[$record->contextid]->courseid;
                $record->coursename = $ctxcourses[$record->contextid]->coursename;
            } else {
                $record->resolvedcourseid = 0;
                $record->coursename = '-';
            }

            $ctx = $contexts[$record->contextid] ?? null;
            $record->contextlevel = $ctx ? (int) $ctx->contextlevel : 0;
            $record->contextinstanceid = $ctx ? (int) $ctx->instanceid : 0;
            if ($record->contextlevel === CONTEXT_MODULE && isset($moduleinfos[$record->contextinstanceid])) {
                $record->modtype = $moduleinfos[$record->contextinstanceid]->modtype;
            } else {
                $record->modtype = '';
            }
        }

        // Count.
        $countsql = "SELECT COUNT(*)
                       FROM {files} f
                  LEFT JOIN {context} ctx ON f.contextid = ctx.id
                      WHERE {$where}";
        $totalcount = $DB->count_records_sql($countsql, $params);

        return (object) ['records' => $records, 'totalcount' => $totalcount];
    }

    /**
     * Delete files by their IDs using the Moodle File API.
     *
     * @param array $fileids Array of file IDs to delete.
     * @return int Number of files successfully deleted.
     */
    public function delete_files(array $fileids): int {
        $fs = get_file_storage();
        $count = 0;

        foreach ($fileids as $fileid) {
            $file = $fs->get_file_by_id($fileid);
            if ($file && $file->delete()) {
                $count++;
            }
        }

        // Invalidate caches after deletion.
        $this->cache->purge();

        return $count;
    }

    /**
     * Get courses with backup statistics and scheduling info.
     *
     * Optimised two-step approach to avoid a single heavy JOIN across
     * course + context + files + backup_courses on large instances:
     *   Step 1 – lightweight aggregation on files only (backup stats per context).
     *   Step 2 – batch-load course metadata and scheduling info in PHP.
     *
     * @param int $page Page number (0-based).
     * @param int $perpage Results per page (0 = unlimited, for CSV export).
     * @param string $sort Sort field.
     * @param string $dir Sort direction (ASC/DESC).
     * @param string $search Search term for course name/ID.
     * @return object {records, totalcount}
     */
    public function get_courses_backups(
        int $page = 0,
        int $perpage = 50,
        string $sort = 'backup_totalsize',
        string $dir = 'DESC',
        string $search = ''
    ): object {
        global $DB;

        $allowedsorts = ['backup_totalsize', 'backup_count', 'fullname', 'nextstarttime', 'laststarttime'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'backup_totalsize';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        // ----------------------------------------------------------
        // Step 1: Get backup file stats per course (lightweight).
        // Only touches context + files tables, no JOIN on course row.
        // ----------------------------------------------------------
        $sql1 = "SELECT ctx.instanceid AS courseid,
                        COUNT(f.id) AS backup_count,
                        COALESCE(SUM(f.filesize), 0) AS backup_totalsize
                   FROM {files} f
                   JOIN {context} ctx ON f.contextid = ctx.id
                        AND ctx.contextlevel = :ctxlevel
                  WHERE f.component = 'backup'
                    AND f.filearea IN ('course', 'automated', 'activity')
                    AND f.filename != '.'
                  GROUP BY ctx.instanceid";

        $backupstats = $DB->get_records_sql($sql1, ['ctxlevel' => CONTEXT_COURSE]);

        // ----------------------------------------------------------
        // Step 2: Get scheduling info from backup_courses.
        // ----------------------------------------------------------
        $schedules = $DB->get_records('backup_courses', null, '',
            'courseid, nextstarttime, laststarttime, lastendtime, laststatus');

        // ----------------------------------------------------------
        // Merge: build a map of courseids that have backups OR scheduling.
        // ----------------------------------------------------------
        $courseids = [];
        foreach ($backupstats as $row) {
            $courseids[(int) $row->courseid] = true;
        }
        foreach ($schedules as $row) {
            if ((int) $row->nextstarttime > 0) {
                $courseids[(int) $row->courseid] = true;
            }
        }

        // Remove the site course.
        unset($courseids[1]);

        if (empty($courseids)) {
            return (object) ['records' => [], 'totalcount' => 0];
        }

        // ----------------------------------------------------------
        // Step 3: Batch-load course metadata for matching IDs only.
        // ----------------------------------------------------------
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($courseids), SQL_PARAMS_NAMED);
        $searchwhere = '';
        if ($search !== '') {
            if (ctype_digit($search)) {
                $searchwhere = ' AND c.id = :searchid';
                $inparams['searchid'] = (int) $search;
            } else {
                $searchlike = '%' . $DB->sql_like_escape($search) . '%';
                $searchwhere = ' AND (c.fullname LIKE :searchfull OR c.shortname LIKE :searchshort)';
                $inparams['searchfull'] = $searchlike;
                $inparams['searchshort'] = $searchlike;
            }
        }

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.visible
               FROM {course} c
              WHERE c.id {$insql} {$searchwhere}",
            $inparams
        );

        // ----------------------------------------------------------
        // Step 4: Assemble final result set in PHP.
        // ----------------------------------------------------------
        $rows = [];
        foreach ($courses as $c) {
            $cid = (int) $c->id;
            $bs = isset($backupstats[$cid]) ? $backupstats[$cid] : null;
            $sc = isset($schedules[$cid]) ? $schedules[$cid] : null;

            $row = new \stdClass();
            $row->id = $cid;
            $row->fullname = $c->fullname;
            $row->shortname = $c->shortname;
            $row->visible = $c->visible;
            $row->backup_count = $bs ? (int) $bs->backup_count : 0;
            $row->backup_totalsize = $bs ? (int) $bs->backup_totalsize : 0;
            $row->nextstarttime = $sc ? (int) $sc->nextstarttime : 0;
            $row->laststarttime = $sc ? (int) $sc->laststarttime : 0;
            $row->laststatus = $sc ? (int) $sc->laststatus : -1;
            $rows[] = $row;
        }

        // Sort in PHP (fast – only matching courses, not the full course table).
        $sortfield = $sort;
        $sortdir = $dir;
        usort($rows, function ($a, $b) use ($sortfield, $sortdir) {
            $va = $a->{$sortfield} ?? '';
            $vb = $b->{$sortfield} ?? '';
            if (is_string($va)) {
                $cmp = strnatcasecmp($va, $vb);
            } else {
                $cmp = $va <=> $vb;
            }
            return $sortdir === 'ASC' ? $cmp : -$cmp;
        });

        $totalcount = count($rows);

        // Apply pagination.
        if ($perpage > 0) {
            $rows = array_slice($rows, $page * $perpage, $perpage);
        }

        return (object) ['records' => $rows, 'totalcount' => $totalcount];
    }

    /**
     * Get individual backup files for a specific course.
     *
     * @param int $courseid The course ID.
     * @return array Array of file records.
     */
    public function get_course_backup_files(int $courseid): array {
        global $DB;

        $sql = "SELECT f.id, f.filename, f.filesize, f.filearea, f.timecreated, f.contenthash
                  FROM {files} f
                  JOIN {context} ctx ON f.contextid = ctx.id AND ctx.contextlevel = :ctxlevel
                 WHERE ctx.instanceid = :courseid
                   AND f.component = 'backup'
                   AND f.filearea IN ('course', 'automated', 'activity')
                   AND f.filename != '.'
                 ORDER BY f.timecreated DESC";

        return array_values($DB->get_records_sql($sql, [
            'ctxlevel' => CONTEXT_COURSE,
            'courseid' => $courseid,
        ]));
    }

    /**
     * Get integrity scan progress statistics.
     *
     * @return object|null Progress object or null if scan has never run.
     */
    public function get_scan_progress(): ?object {
        global $DB;

        $scanned = (int) $DB->count_records('local_storage360_scanresult');
        if ($scanned === 0) {
            return null;
        }

        $emptyhash = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';
        $totalunique = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT contenthash)
               FROM {files}
              WHERE filename != '.'
                AND contenthash != :emptyhash",
            ['emptyhash' => $emptyhash]
        );

        // Count by status.
        $statuscounts = $DB->get_records_sql(
            "SELECT status, COUNT(*) AS cnt
               FROM {local_storage360_scanresult}
              GROUP BY status"
        );

        $ok = 0;
        $missing = 0;
        $sizemismatch = 0;
        $intrash = 0;
        $orphans = 0;
        foreach ($statuscounts as $row) {
            switch ((int) $row->status) {
                case 0: $ok = (int) $row->cnt; break;
                case 1: $missing = (int) $row->cnt; break;
                case 2: $sizemismatch = (int) $row->cnt; break;
                case 3: $intrash = (int) $row->cnt; break;
                case 4: $orphans = (int) $row->cnt; break;
            }
        }

        // Orphan total size.
        $orphansize = 0;
        if ($orphans > 0) {
            $orphansize = (int) $DB->get_field_sql(
                "SELECT COALESCE(SUM(filesize_disk), 0)
                   FROM {local_storage360_scanresult}
                  WHERE status = 4"
            );
        }

        // Disk scan cursor.
        $orphancursor = get_config('local_storage360', 'orphan_scan_cursor');

        // Exclude orphans from the DB→disk scan count (orphans are not in mdl_files).
        $dbscanned = $scanned - $orphans;

        return (object) [
            'total_unique'  => $totalunique,
            'scanned'       => $dbscanned,
            'remaining'     => max(0, $totalunique - $dbscanned),
            'percent'       => $totalunique > 0 ? round(($dbscanned / $totalunique) * 100, 1) : 0,
            'anomalies'     => $missing + $sizemismatch + $intrash,
            'ok'            => $ok,
            'missing'       => $missing,
            'size_mismatch' => $sizemismatch,
            'in_trash'      => $intrash,
            'orphans'       => $orphans,
            'orphan_size'   => $orphansize,
            'orphan_cursor' => $orphancursor ?: null,
        ];
    }

    /**
     * Get scan results for a set of contenthashes (batch-loading for the files table).
     *
     * @param array $contenthashes Array of contenthash strings.
     * @return array Associative array keyed by contenthash => status (int).
     */
    public function get_scan_status_for_hashes(array $contenthashes): array {
        global $DB;

        if (empty($contenthashes)) {
            return [];
        }

        $contenthashes = array_unique($contenthashes);

        list($insql, $params) = $DB->get_in_or_equal($contenthashes, SQL_PARAMS_NAMED);
        $records = $DB->get_records_sql(
            "SELECT contenthash, status
               FROM {local_storage360_scanresult}
              WHERE contenthash {$insql}",
            $params
        );

        $result = [];
        foreach ($records as $row) {
            $result[$row->contenthash] = (int) $row->status;
        }

        return $result;
    }

    /**
     * Get orphan files detected by the disk scan.
     *
     * @param int $page Page number (0-based).
     * @param int $perpage Results per page.
     * @return object Object with 'records' array and 'total' count.
     */
    public function get_orphan_files(int $page = 0, int $perpage = 50): object {
        global $DB;

        $total = (int) $DB->count_records('local_storage360_scanresult', ['status' => 4]);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT id, contenthash, filesize_disk, timescanned
                   FROM {local_storage360_scanresult}
                  WHERE status = 4
                  ORDER BY filesize_disk DESC",
                [],
                $page * $perpage,
                $perpage
            );
        }

        return (object) [
            'records' => array_values($records),
            'total'   => $total,
        ];
    }
}
