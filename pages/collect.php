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
 * Run the storage data collection task on demand.
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/storage360:managesettings', $context);
require_sesskey();

// Allow up to 10 minutes for large instances.
@set_time_limit(600);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/storage360/pages/collect.php'));
$PAGE->set_title(get_string('collect:title', 'local_storage360'));
$PAGE->set_heading(get_string('collect:title', 'local_storage360'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('collect:running', 'local_storage360'), ['class' => 'lead']);

// Flush output so the admin sees progress.
ob_implicit_flush(true);
if (ob_get_level()) {
    ob_end_flush();
}

echo html_writer::start_tag('pre');

$task = new \local_storage360\task\collect_storage_stats();
$task->execute();

echo html_writer::end_tag('pre');

echo $OUTPUT->notification(get_string('collect:done', 'local_storage360'), 'success');
echo html_writer::link(
    new moodle_url('/local/storage360/pages/dashboard.php'),
    get_string('nav:dashboard', 'local_storage360'),
    ['class' => 'btn btn-primary']
);

echo $OUTPUT->footer();
