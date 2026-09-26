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
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/storage360/lib.php');

/**
 * Unit tests for the Storage 360 entries of the site administration tree (settings.php).
 *
 * @package    local_storage360
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class settings_test extends \advanced_testcase {
    /**
     * Test the Storage 360 category sits under Server and lists the pages, then the settings page.
     */
    public function test_admin_tree_category(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $root = admin_get_root(true, false);
        $category = $root->locate('local_storage360_category', true);

        $this->assertInstanceOf(\admin_category::class, $category);
        $this->assertSame(['local_storage360_category', 'server', 'root'], $category->path);

        $expected = array_map(function ($tab) {
            return 'local_storage360_' . $tab->id;
        }, local_storage360_get_tabs('dashboard'));
        $expected[] = 'local_storage360';
        $names = array_map(function ($child) {
            return $child->name;
        }, $category->children);
        $this->assertSame($expected, $names);

        // The settings page keeps its section name, so admin/settings.php?section=local_storage360 still works.
        $this->assertInstanceOf(\admin_settingpage::class, $root->locate('local_storage360'));
    }

    /**
     * Test every plugin tab has an admin tree entry pointing to the same page.
     */
    public function test_admin_tree_pages_match_tabs(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $root = admin_get_root(true, false);
        foreach (local_storage360_get_tabs('dashboard') as $tab) {
            $page = $root->locate('local_storage360_' . $tab->id);
            $this->assertInstanceOf(\admin_externalpage::class, $page);
            $this->assertEquals($tab->link->out(false), $page->get_settings_page_url()->out(false));
        }
    }

    /**
     * Test pages are listed only for users holding the capability each page requires.
     */
    public function test_admin_tree_access(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $roleid = $generator->create_role();
        $syscontext = \context_system::instance();
        assign_capability('local/storage360:view', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $user->id, $syscontext->id);
        $this->setUser($user);

        $root = admin_get_root(true, false);

        $this->assertTrue($root->locate('local_storage360_category')->check_access());
        $this->assertTrue($root->locate('local_storage360_dashboard')->check_access());
        $this->assertTrue($root->locate('local_storage360_timeline')->check_access());
        $this->assertTrue($root->locate('local_storage360_orphans')->check_access());
        $this->assertFalse($root->locate('local_storage360_courses')->check_access());
        $this->assertFalse($root->locate('local_storage360_cleanup')->check_access());
        // The settings page is only added for users with moodle/site:config.
        $this->assertNull($root->locate('local_storage360'));
    }

    /**
     * Test the settings navigation of a plugin page holds each Storage 360 node once.
     *
     * The pages used to be added again by a settings navigation callback, under the key of the
     * settings page, which raised "Navigation node intersect: Adding a node that already exists".
     */
    public function test_settings_navigation(): void {
        global $PAGE;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $url = new \moodle_url('/local/storage360/pages/dashboard.php');
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('admin');
        \navigation_node::override_active_url($url);

        $category = $PAGE->settingsnav->find('local_storage360_category', \navigation_node::TYPE_SETTING);

        $this->assertDebuggingNotCalled();
        $this->assertInstanceOf(\navigation_node::class, $category);
        $this->assertCount(11, $category->children);
        $this->assertTrue($category->get('local_storage360_dashboard')->isactive);
    }
}
