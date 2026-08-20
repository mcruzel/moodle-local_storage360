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

namespace local_storage360\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_storage360.
 *
 * The deletelog table stores personal data (owneruserid, deletedby, ownerfullname).
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about the data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_storage360_history',
            [
                'timecreated' => 'privacy:metadata:history:timecreated',
                'total_size' => 'privacy:metadata:history:total_size',
            ],
            'privacy:metadata:history'
        );

        $collection->add_database_table(
            'local_storage360_deletelog',
            [
                'owneruserid' => 'privacy:metadata:deletelog:owneruserid',
                'ownerfullname' => 'privacy:metadata:deletelog:ownerfullname',
                'deletedby' => 'privacy:metadata:deletelog:deletedby',
                'filename' => 'privacy:metadata:deletelog:filename',
                'filesize' => 'privacy:metadata:deletelog:filesize',
                'timedeleted' => 'privacy:metadata:deletelog:timedeleted',
            ],
            'privacy:metadata:deletelog'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND EXISTS (
                       SELECT 1 FROM {local_storage360_deletelog} d
                        WHERE d.owneruserid = :ownerid OR d.deletedby = :deletedbyid
                   )";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'ownerid' => $userid,
            'deletedbyid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $sql = "SELECT owneruserid AS userid FROM {local_storage360_deletelog} WHERE owneruserid IS NOT NULL
                UNION
                SELECT deletedby AS userid FROM {local_storage360_deletelog}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        // Export records where user is the file owner.
        $records = $DB->get_records_select(
            'local_storage360_deletelog',
            'owneruserid = :userid',
            ['userid' => $userid]
        );
        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'filename' => $record->filename,
                    'filesize' => $record->filesize,
                    'component' => $record->component,
                    'filearea' => $record->filearea,
                    'timedeleted' => \core_privacy\local\request\transform::datetime($record->timedeleted),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_storage360'), get_string('privacy:deletelog:asowner', 'local_storage360')],
                (object) ['records' => $data]
            );
        }

        // Export records where user performed the deletion.
        $records = $DB->get_records_select(
            'local_storage360_deletelog',
            'deletedby = :userid',
            ['userid' => $userid]
        );
        if (!empty($records)) {
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'filename' => $record->filename,
                    'filesize' => $record->filesize,
                    'component' => $record->component,
                    'filearea' => $record->filearea,
                    'timedeleted' => \core_privacy\local\request\transform::datetime($record->timedeleted),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_storage360'), get_string('privacy:deletelog:asdeleter', 'local_storage360')],
                (object) ['records' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Anonymize rather than delete (audit log must be preserved).
        $DB->execute(
            "UPDATE {local_storage360_deletelog}
                SET owneruserid = NULL,
                    ownerfullname = NULL
              WHERE owneruserid IS NOT NULL"
        );
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        // Anonymize owner references.
        $DB->execute(
            "UPDATE {local_storage360_deletelog}
                SET owneruserid = NULL,
                    ownerfullname = NULL
              WHERE owneruserid = :userid",
            ['userid' => $userid]
        );

        // Anonymize deleter references (keep the audit record itself).
        $DB->execute(
            "UPDATE {local_storage360_deletelog}
                SET deletedby = 0
              WHERE deletedby = :userid",
            ['userid' => $userid]
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->execute(
            "UPDATE {local_storage360_deletelog}
                SET owneruserid = NULL,
                    ownerfullname = NULL
              WHERE owneruserid {$insql}",
            $inparams
        );

        $DB->execute(
            "UPDATE {local_storage360_deletelog}
                SET deletedby = 0
              WHERE deletedby {$insql}",
            $inparams
        );
    }
}
