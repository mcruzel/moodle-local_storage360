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
 * Unit tests for Storage 360 events.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    /**
     * Test backup_deleted event.
     */
    public function test_backup_deleted_event(): void {
        $this->resetAfterTest(true);
        $context = \context_system::instance();

        $event = \local_storage360\event\backup_deleted::create([
            'objectid' => 123,
            'context' => $context,
            'other' => [
                'filename' => 'backup-course-1.mbz',
                'filesize' => 1048576,
            ],
        ]);

        $this->assertInstanceOf(\local_storage360\event\backup_deleted::class, $event);
        $this->assertEquals('d', $event->crud);
        $this->assertStringContainsString('backup file', $event->get_description());
        $this->assertStringContainsString('123', $event->get_description());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
    }

    /**
     * Test file_deleted event.
     */
    public function test_file_deleted_event(): void {
        $this->resetAfterTest(true);
        $context = \context_system::instance();

        $event = \local_storage360\event\file_deleted::create([
            'objectid' => 456,
            'context' => $context,
            'other' => [
                'filename' => 'draft-document.pdf',
                'component' => 'user',
            ],
        ]);

        $this->assertInstanceOf(\local_storage360\event\file_deleted::class, $event);
        $this->assertEquals('d', $event->crud);
        $this->assertStringContainsString('456', $event->get_description());
        $this->assertStringContainsString('user', $event->get_description());
    }

    /**
     * Test backup_deleted event triggers without error.
     */
    public function test_backup_deleted_triggers(): void {
        $this->resetAfterTest(true);
        $context = \context_system::instance();

        $sink = $this->redirectEvents();

        $event = \local_storage360\event\backup_deleted::create([
            'objectid' => 1,
            'context' => $context,
            'other' => ['filename' => 'test.mbz', 'filesize' => 100],
        ]);
        $event->trigger();

        $events = $sink->get_events();
        $sink->close();

        $this->assertNotEmpty($events);
        $lastevent = end($events);
        $this->assertInstanceOf(\local_storage360\event\backup_deleted::class, $lastevent);
    }
}
