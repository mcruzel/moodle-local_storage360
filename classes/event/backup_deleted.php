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

namespace local_storage360\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a backup file is deleted via Storage 360.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_deleted extends \core\event\base {

    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'files';
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:backupdeleted', 'local_storage360');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' deleted backup file with id '{$this->objectid}'" .
               " ({$this->other['filename']}, {$this->other['filesize']} bytes) via Storage 360.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/storage360/pages/cleanup.php');
    }

    /**
     * Returns the objectid mapping for backup/restore.
     *
     * The deleted backup file no longer exists, so the id cannot be mapped on restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'files', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Returns the mapping of the 'other' fields for backup/restore.
     *
     * No ids in 'other' need to be mapped.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
