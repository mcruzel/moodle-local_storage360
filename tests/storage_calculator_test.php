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

namespace local_storage360;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the storage_calculator class.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_storage360\analytics\storage_calculator
 */
class storage_calculator_test extends \advanced_testcase {

    /** @var \local_storage360\analytics\storage_calculator */
    private $calculator;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->calculator = new \local_storage360\analytics\storage_calculator();
    }

    /**
     * Test get_global_stats returns correct structure.
     */
    public function test_get_global_stats_empty(): void {
        $stats = $this->calculator->get_global_stats(false);

        $this->assertIsObject($stats);
        $this->assertObjectHasProperty('total_files', $stats);
        $this->assertObjectHasProperty('total_size', $stats);
        $this->assertObjectHasProperty('avg_size', $stats);
        $this->assertIsInt($stats->total_files);
        $this->assertIsInt($stats->total_size);
    }

    /**
     * Test get_global_stats with files in the system.
     */
    public function test_get_global_stats_with_files(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->create_test_file($course, 'mod_resource', 'content', 1024);
        $this->create_test_file($course, 'mod_resource', 'content', 2048);

        $stats = $this->calculator->get_global_stats(false);

        // At least 2 files with our known sizes (Moodle may create others).
        $this->assertGreaterThanOrEqual(2, $stats->total_files);
        $this->assertGreaterThanOrEqual(3072, $stats->total_size);
    }

    /**
     * Test get_global_stats caching.
     */
    public function test_get_global_stats_cache(): void {
        $stats1 = $this->calculator->get_global_stats(false);
        $stats2 = $this->calculator->get_global_stats(true);

        $this->assertEquals($stats1->total_files, $stats2->total_files);
        $this->assertEquals($stats1->total_size, $stats2->total_size);
    }

    /**
     * Test get_disk_space with auto mode.
     */
    public function test_get_disk_space_auto(): void {
        set_config('diskspacemethod', 'auto', 'local_storage360');
        $disk = $this->calculator->get_disk_space();

        // May return null if disk_total_space fails in test environment.
        if ($disk !== null) {
            $this->assertObjectHasProperty('total', $disk);
            $this->assertObjectHasProperty('free', $disk);
            $this->assertObjectHasProperty('used', $disk);
            $this->assertObjectHasProperty('percent', $disk);
            $this->assertGreaterThan(0, $disk->total);
            $this->assertGreaterThanOrEqual(0, $disk->percent);
            $this->assertLessThanOrEqual(100, $disk->percent);
        }
    }

    /**
     * Test get_disk_space with manual mode.
     */
    public function test_get_disk_space_manual(): void {
        set_config('diskspacemethod', 'manual', 'local_storage360');
        set_config('manualdisksize', '50', 'local_storage360');

        $disk = $this->calculator->get_disk_space();

        $this->assertNotNull($disk);
        $this->assertEquals(50 * 1073741824, $disk->total);
        $this->assertGreaterThanOrEqual(0, $disk->percent);
    }

    /**
     * Test get_disk_space with dbonly mode returns null.
     */
    public function test_get_disk_space_dbonly(): void {
        set_config('diskspacemethod', 'dbonly', 'local_storage360');

        $disk = $this->calculator->get_disk_space();
        $this->assertNull($disk);
    }

    /**
     * Test get_growth_rate returns correct structure.
     */
    public function test_get_growth_rate(): void {
        $growth = $this->calculator->get_growth_rate();

        $this->assertIsObject($growth);
        $this->assertObjectHasProperty('current_month_size', $growth);
        $this->assertObjectHasProperty('previous_month_size', $growth);
        $this->assertObjectHasProperty('growth_bytes', $growth);
        $this->assertObjectHasProperty('growth_percent', $growth);
    }

    /**
     * Test get_top_courses with data.
     */
    public function test_get_top_courses(): void {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // Create files for course1 (larger).
        $this->create_test_file($course1, 'mod_resource', 'content', 5000);
        // Create files for course2 (smaller).
        $this->create_test_file($course2, 'mod_resource', 'content', 1000);

        $top = $this->calculator->get_top_courses(5);

        $this->assertIsArray($top);
        $this->assertLessThanOrEqual(5, count($top));

        if (count($top) >= 2) {
            // First course should have more storage than second.
            $this->assertGreaterThanOrEqual((int) $top[1]->total_size, (int) $top[0]->total_size);
        }
    }

    /**
     * Test get_top_courses with limit.
     */
    public function test_get_top_courses_limit(): void {
        for ($i = 0; $i < 10; $i++) {
            $course = $this->getDataGenerator()->create_course();
            $this->create_test_file($course, 'mod_resource', 'content', ($i + 1) * 1000);
        }

        $top3 = $this->calculator->get_top_courses(3);
        $this->assertCount(3, $top3);
    }

    /**
     * Test get_by_component returns correct structure.
     */
    public function test_get_by_component(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->create_test_file($course, 'mod_resource', 'content', 1024);

        $components = $this->calculator->get_by_component(false);

        $this->assertIsArray($components);
        $this->assertNotEmpty($components);

        $first = $components[0];
        $this->assertObjectHasProperty('component', $first);
        $this->assertObjectHasProperty('filearea', $first);
        $this->assertObjectHasProperty('file_count', $first);
        $this->assertObjectHasProperty('total_size', $first);
    }

    /**
     * Test get_courses_storage pagination.
     */
    public function test_get_courses_storage_pagination(): void {
        for ($i = 0; $i < 5; $i++) {
            $course = $this->getDataGenerator()->create_course();
            $this->create_test_file($course, 'mod_resource', 'content', 1024);
        }

        $page1 = $this->calculator->get_courses_storage(0, 2);
        $this->assertObjectHasProperty('records', $page1);
        $this->assertObjectHasProperty('totalcount', $page1);
        $this->assertCount(2, $page1->records);
        $this->assertGreaterThanOrEqual(5, $page1->totalcount);

        $page2 = $this->calculator->get_courses_storage(1, 2);
        $this->assertCount(2, $page2->records);
    }

    /**
     * Test get_courses_storage sorting.
     */
    public function test_get_courses_storage_sort(): void {
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'AAA Course']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'ZZZ Course']);
        $this->create_test_file($course1, 'mod_resource', 'content', 1024);
        $this->create_test_file($course2, 'mod_resource', 'content', 1024);

        $asc = $this->calculator->get_courses_storage(0, 50, 'fullname', 'ASC');
        $names = array_map(function ($r) {
            return $r->fullname;
        }, $asc->records);

        // AAA should come before ZZZ.
        $aaa = array_search('AAA Course', $names);
        $zzz = array_search('ZZZ Course', $names);
        if ($aaa !== false && $zzz !== false) {
            $this->assertLessThan($zzz, $aaa);
        }
    }

    /**
     * Test get_courses_storage category filter.
     */
    public function test_get_courses_storage_category_filter(): void {
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $cat1->id]);
        $course2 = $this->getDataGenerator()->create_course(['category' => $cat2->id]);
        $this->create_test_file($course1, 'mod_resource', 'content', 1024);
        $this->create_test_file($course2, 'mod_resource', 'content', 1024);

        $filtered = $this->calculator->get_courses_storage(0, 50, 'total_size', 'DESC', $cat1->id);
        foreach ($filtered->records as $r) {
            $this->assertEquals($cat1->id, $r->category);
        }
    }

    /**
     * Test get_users_storage returns correct structure.
     */
    public function test_get_users_storage(): void {
        $result = $this->calculator->get_users_storage();

        $this->assertObjectHasProperty('records', $result);
        $this->assertObjectHasProperty('totalcount', $result);
        $this->assertIsArray($result->records);
    }

    /**
     * Test get_users_storage search filter.
     */
    public function test_get_users_storage_search(): void {
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'UniqueTestName',
            'lastname' => 'StorageUser',
        ]);

        $result = $this->calculator->get_users_storage(0, 50, 'total_size', 'DESC', 0, 'UniqueTestName');

        $found = false;
        foreach ($result->records as $r) {
            if ($r->firstname === 'UniqueTestName') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should find the user by firstname');
    }

    /**
     * Test get_monthly_additions returns correct structure.
     */
    public function test_get_monthly_additions(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->create_test_file($course, 'mod_resource', 'content', 2048);

        $monthly = $this->calculator->get_monthly_additions(12);

        $this->assertIsArray($monthly);
        if (!empty($monthly)) {
            $first = $monthly[0];
            $this->assertObjectHasProperty('month', $first);
            $this->assertObjectHasProperty('files_added', $first);
            $this->assertObjectHasProperty('size_added', $first);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $first->month);
        }
    }

    /**
     * Test get_history with empty table.
     */
    public function test_get_history_empty(): void {
        $history = $this->calculator->get_history();
        $this->assertIsArray($history);
        $this->assertEmpty($history);
    }

    /**
     * Test get_history with data.
     */
    public function test_get_history_with_data(): void {
        global $DB;

        $DB->insert_record('local_storage360_history', (object) [
            'timecreated' => time() - 86400,
            'total_files' => 100,
            'total_size' => 1048576,
            'by_component' => '{}',
        ]);
        $DB->insert_record('local_storage360_history', (object) [
            'timecreated' => time(),
            'total_files' => 110,
            'total_size' => 2097152,
            'by_component' => '{}',
        ]);

        $history = $this->calculator->get_history();
        $this->assertCount(2, $history);
        // Should be ordered by timecreated ASC.
        $this->assertLessThan($history[1]->timecreated, $history[0]->timecreated);
    }

    /**
     * Test get_courses_backups search by course id does not throw a DML exception.
     *
     * Regression: search WHERE used alias c without aliasing {course}.
     */
    public function test_get_courses_backups_search_by_id(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->create_test_file($course, 'backup', 'automated', 1024);

        $result = $this->calculator->get_courses_backups(
            0, 50, 'backup_totalsize', 'DESC', (string) $course->id
        );

        $this->assertObjectHasProperty('records', $result);
        $this->assertObjectHasProperty('totalcount', $result);
        $ids = array_map('intval', array_column($result->records, 'id'));
        $this->assertContains((int) $course->id, $ids);
    }

    /**
     * Test get_courses_backups search by course name does not throw a DML exception.
     */
    public function test_get_courses_backups_search_by_name(): void {
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'UniqueBackupSearchCourse',
        ]);
        $this->create_test_file($course, 'backup', 'automated', 1024);

        $result = $this->calculator->get_courses_backups(
            0, 50, 'backup_totalsize', 'DESC', 'UniqueBackupSearchCourse'
        );

        $this->assertGreaterThanOrEqual(1, $result->totalcount);
        $found = false;
        foreach ($result->records as $record) {
            if ((int) $record->id === (int) $course->id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should find the course by fullname');
    }

    /**
     * Test get_backups returns correct structure.
     */
    public function test_get_backups(): void {
        $result = $this->calculator->get_backups();

        $this->assertObjectHasProperty('records', $result);
        $this->assertObjectHasProperty('totalcount', $result);
        $this->assertIsArray($result->records);
    }

    /**
     * Test get_old_drafts returns correct structure.
     */
    public function test_get_old_drafts(): void {
        $result = $this->calculator->get_old_drafts(30);

        $this->assertObjectHasProperty('records', $result);
        $this->assertObjectHasProperty('totalcount', $result);
        $this->assertIsArray($result->records);
    }

    /**
     * Test delete_files with invalid IDs does not crash.
     */
    public function test_delete_files_invalid_ids(): void {
        $count = $this->calculator->delete_files([999999, 999998]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test SQL injection safety in sort parameter.
     */
    public function test_courses_storage_sort_injection(): void {
        // Pass an invalid sort field, should fallback to total_size.
        $result = $this->calculator->get_courses_storage(0, 10, 'DROP TABLE; --', 'DESC');
        $this->assertObjectHasProperty('records', $result);
    }

    /**
     * Test SQL injection safety in direction parameter.
     */
    public function test_courses_storage_dir_injection(): void {
        $result = $this->calculator->get_courses_storage(0, 10, 'total_size', 'INVALID');
        $this->assertObjectHasProperty('records', $result);
    }

    /**
     * Helper: create a test file in a course context.
     *
     * @param object $course The course object.
     * @param string $component Component name.
     * @param string $filearea File area.
     * @param int $filesize File size in bytes.
     * @return \stored_file
     */
    private function create_test_file($course, string $component, string $filearea, int $filesize): \stored_file {
        $context = \context_course::instance($course->id);
        $fs = get_file_storage();

        $filerecord = [
            'contextid' => $context->id,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'testfile_' . uniqid() . '.dat',
        ];

        // Create content of specified size.
        $content = str_repeat('x', $filesize);

        return $fs->create_file_from_string($filerecord, $content);
    }
}
