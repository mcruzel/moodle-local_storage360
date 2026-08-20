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
 * Database upgrade steps for local_storage360.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_storage360 upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_storage360_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025021204) {
        // Add deletion audit log table.
        $table = new xmldb_table('local_storage360_deletelog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('filepath', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '/');
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('mimetype', XMLDB_TYPE_CHAR, '255');
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('filearea', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('coursename', XMLDB_TYPE_CHAR, '254');
        $table->add_field('owneruserid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('ownerfullname', XMLDB_TYPE_CHAR, '254');
        $table->add_field('deletedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('source', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'cleanup');
        $table->add_field('timedeleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_timedeleted', XMLDB_INDEX_NOTUNIQUE, ['timedeleted']);
        $table->add_index('idx_deletedby', XMLDB_INDEX_NOTUNIQUE, ['deletedby']);
        $table->add_index('idx_component', XMLDB_INDEX_NOTUNIQUE, ['component']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025021204, 'local', 'storage360');
    }

    if ($oldversion < 2025021209) {
        // Add per-user storage snapshot table.
        $table = new xmldb_table('local_storage360_user_snap');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('total_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('file_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('private_files', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('draft_files', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('assignment_files', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('last_upload', XMLDB_TYPE_INTEGER, '10');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('idx_userid_time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025021209, 'local', 'storage360');
    }

    if ($oldversion < 2025021210) {
        // Add contenthash column to deletelog for disk verification.
        $table = new xmldb_table('local_storage360_deletelog');
        $field = new xmldb_field('contenthash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'filesize');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025021210, 'local', 'storage360');
    }

    if ($oldversion < 2026021501) {
        // Add integrity scan results table.
        $table = new xmldb_table('local_storage360_scanresult');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
        $table->add_field('filesize_db', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL);
        $table->add_field('filesize_disk', XMLDB_TYPE_INTEGER, '20');
        $table->add_field('ref_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL);
        $table->add_field('timescanned', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('uq_contenthash', XMLDB_INDEX_UNIQUE, ['contenthash']);
        $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('idx_timescanned', XMLDB_INDEX_NOTUNIQUE, ['timescanned']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026021501, 'local', 'storage360');
    }

    if ($oldversion < 2026021602) {
        // v1.6.0: Orphan file detection (Disk→DB scan).
        // No schema change — reuses scanresult table with status=4 for orphans.
        // Orphan scan cursor stored in config_plugins (orphan_scan_cursor).
        upgrade_plugin_savepoint(true, 2026021602, 'local', 'storage360');
    }

    if ($oldversion < 2026021701) {
        // v1.7.0: Deduplicated storage metrics, timeline UX improvements,
        // orphan file download and MIME type detection.
        // No schema change.
        upgrade_plugin_savepoint(true, 2026021701, 'local', 'storage360');
    }

    return true;
}
