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
 * Admin settings for local_storage360.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_storage360', get_string('pluginname', 'local_storage360'));

    // Disk space calculation method.
    $settings->add(new admin_setting_configselect(
        'local_storage360/diskspacemethod',
        get_string('settings:diskspacemethod', 'local_storage360'),
        get_string('settings:diskspacemethod_desc', 'local_storage360'),
        'auto',
        [
            'auto' => get_string('settings:diskspacemethod_auto', 'local_storage360'),
            'manual' => get_string('settings:diskspacemethod_manual', 'local_storage360'),
            'dbonly' => get_string('settings:diskspacemethod_dbonly', 'local_storage360'),
        ]
    ));

    // Manual disk size (GB).
    $settings->add(new admin_setting_configtext(
        'local_storage360/manualdisksize',
        get_string('settings:manualdisksize', 'local_storage360'),
        get_string('settings:manualdisksize_desc', 'local_storage360'),
        '100',
        PARAM_INT
    ));

    // Cache TTL (seconds).
    $settings->add(new admin_setting_configtext(
        'local_storage360/cachettl',
        get_string('settings:cachettl', 'local_storage360'),
        get_string('settings:cachettl_desc', 'local_storage360'),
        '3600',
        PARAM_INT
    ));

    // Draft cleanup threshold (days).
    $settings->add(new admin_setting_configtext(
        'local_storage360/draftcleanupdays',
        get_string('settings:draftcleanupdays', 'local_storage360'),
        get_string('settings:draftcleanupdays_desc', 'local_storage360'),
        '30',
        PARAM_INT
    ));

    // Backup cleanup threshold (days).
    $settings->add(new admin_setting_configtext(
        'local_storage360/backupcleanupdays',
        get_string('settings:backupcleanupdays', 'local_storage360'),
        get_string('settings:backupcleanupdays_desc', 'local_storage360'),
        '365',
        PARAM_INT
    ));

    // Enable auto cleanup.
    $settings->add(new admin_setting_configcheckbox(
        'local_storage360/enableautocleanup',
        get_string('settings:enableautocleanup', 'local_storage360'),
        get_string('settings:enableautocleanup_desc', 'local_storage360'),
        0
    ));

    // Enable storage threshold alert.
    $settings->add(new admin_setting_configcheckbox(
        'local_storage360/enablestoragealert',
        get_string('settings:enablestoragealert', 'local_storage360'),
        get_string('settings:enablestoragealert_desc', 'local_storage360'),
        1
    ));

    // Storage alert threshold (%).
    $settings->add(new admin_setting_configtext(
        'local_storage360/storagethreshold',
        get_string('settings:storagethreshold', 'local_storage360'),
        get_string('settings:storagethreshold_desc', 'local_storage360'),
        '90',
        PARAM_INT
    ));

    // Integrity scan batch size (per direction).
    $settings->add(new admin_setting_configtext(
        'local_storage360/scanbatchsize',
        get_string('settings:scanbatchsize', 'local_storage360'),
        get_string('settings:scanbatchsize_desc', 'local_storage360'),
        '10',
        PARAM_INT
    ));

    $ADMIN->add('server', $settings);
}
