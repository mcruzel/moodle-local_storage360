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

global $CFG;
require_once($CFG->dirroot . '/local/storage360/lib.php');

/**
 * Unit tests for lib.php functions.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib_test extends \advanced_testcase {

    /**
     * Test local_storage360_format_size with bytes.
     */
    public function test_format_size_bytes(): void {
        $result = local_storage360_format_size(500);
        $this->assertStringContainsString('500', $result);
    }

    /**
     * Test local_storage360_format_size with kilobytes.
     */
    public function test_format_size_kilobytes(): void {
        $result = local_storage360_format_size(2048);
        $this->assertStringContainsString('2', $result);
    }

    /**
     * Test local_storage360_format_size with megabytes.
     */
    public function test_format_size_megabytes(): void {
        $result = local_storage360_format_size(5242880); // 5 MB.
        $this->assertStringContainsString('5', $result);
    }

    /**
     * Test local_storage360_format_size with gigabytes.
     */
    public function test_format_size_gigabytes(): void {
        $result = local_storage360_format_size(2147483648); // 2 GB.
        $this->assertStringContainsString('2', $result);
    }

    /**
     * Test local_storage360_format_size with terabytes.
     */
    public function test_format_size_terabytes(): void {
        $result = local_storage360_format_size(1099511627776); // 1 TB.
        $this->assertStringContainsString('1', $result);
    }

    /**
     * Test local_storage360_format_size with zero.
     */
    public function test_format_size_zero(): void {
        $result = local_storage360_format_size(0);
        $this->assertStringContainsString('0', $result);
    }

    /**
     * Test local_storage360_get_tabs returns correct number of tabs.
     */
    public function test_get_tabs_count(): void {
        $this->resetAfterTest(true);
        $tabs = local_storage360_get_tabs('dashboard');
        $this->assertCount(6, $tabs);
    }

    /**
     * Test local_storage360_get_tabs returns tabobject instances.
     */
    public function test_get_tabs_type(): void {
        $this->resetAfterTest(true);
        $tabs = local_storage360_get_tabs('dashboard');
        foreach ($tabs as $tab) {
            $this->assertInstanceOf(\tabobject::class, $tab);
        }
    }

    /**
     * Test tab IDs are as expected.
     */
    public function test_get_tabs_ids(): void {
        $this->resetAfterTest(true);
        $tabs = local_storage360_get_tabs('courses');
        $ids = array_map(function ($tab) {
            return $tab->id;
        }, $tabs);

        $this->assertContains('dashboard', $ids);
        $this->assertContains('courses', $ids);
        $this->assertContains('users', $ids);
        $this->assertContains('components', $ids);
        $this->assertContains('timeline', $ids);
        $this->assertContains('cleanup', $ids);
    }
}
