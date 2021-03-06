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
 * Defines the version of surveypro autofill subplugin
 *
 * This code fragment is called by moodle_needs_upgrading() and
 * /admin/index.php
 *
 * @package    surveyproreport
 * @subpackage count
 * @copyright  2013 onwards kordan <kordan@mclink.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/mod/surveypro/classes/reportbase.class.php');

class mod_surveypro_report_attachments_overview extends mod_surveypro_reportbase {
    /**
     * outputtable
     */
    public $outputtable = null;

    /**
     * report_apply
     *
     * @param none
     * @return none
     */
    public function report_apply() {
        return (!$this->surveypro->anonymous);
    }

    /**
     * setup_outputtable
     *
     * @param none
     * @return none
     */
    public function setup_outputtable() {
        $this->outputtable = new flexible_table('attachmentslist');

        $paramurl = array('id' => $this->cm->id, 'cover' => 0);
        $baseurl = new moodle_url('/mod/surveypro/view.php', $paramurl);
        $this->outputtable->define_baseurl($baseurl);

        $tablecolumns = array();
        $tablecolumns[] = 'picture';
        $tablecolumns[] = 'fullname';
        $tablecolumns[] = 'uploads';
        $this->outputtable->define_columns($tablecolumns);

        $tableheaders = array();
        $tableheaders[] = '';
        $tableheaders[] = get_string('fullname');
        $tableheaders[] = get_string('uploads', 'surveyproreport_attachments_overview');
        $this->outputtable->define_headers($tableheaders);

        $this->outputtable->sortable(true, 'lastname', 'ASC'); // sorted by lastname by default
        $this->outputtable->no_sorting('uploads');

        $this->outputtable->column_class('picture', 'picture');
        $this->outputtable->column_class('fullname', 'fullname');
        $this->outputtable->column_class('uploads', 'uploads');

        $this->outputtable->initialbars(true);

        // hide the same info whether in two consecutive rows
        $this->outputtable->column_suppress('picture');
        $this->outputtable->column_suppress('fullname');

        // general properties for the whole table
        $this->outputtable->summary = get_string('submissionslist', 'surveypro');
        $this->outputtable->set_attribute('cellpadding', '5');
        $this->outputtable->set_attribute('id', 'userattempts');
        $this->outputtable->set_attribute('class', 'generaltable');
        // $this->outputtable->set_attribute('width', '90%');
        $this->outputtable->setup();
    }

    /**
     * fetch_data
     *
     * @param none
     * @return none
     */
    public function fetch_data() {
        global $DB, $COURSE, $OUTPUT;

        $coursecontext = context_course::instance($COURSE->id);
        $roles = get_roles_used_in_context($coursecontext);
        if (!$role = array_keys($roles)) {
            // return nothing
            return;
        }

        $displayuploads = get_string('display_uploads', 'surveyproreport_attachments_overview');
        $missinguploads = get_string('missing_uploads', 'surveyproreport_attachments_overview');
        $submissionidstring = get_string('submissionid', 'surveyproreport_attachments_overview');

        $sql = 'SELECT '.user_picture::fields('u').', s.id as submissionid
                FROM {user} u
                JOIN (SELECT id, userid
                        FROM {role_assignments}
                        WHERE contextid = :contextid
                          AND roleid IN ('.implode(',', $role).')) ra ON u.id = ra.userid
                LEFT JOIN (SELECT id, userid
                         FROM {surveypro_submission}
                         WHERE surveyproid = :surveyproid) s ON u.id = s.userid';
        $whereparams = array();
        $whereparams['surveyproid'] = $this->surveypro->id;
        $whereparams['contextid'] = $coursecontext->id;
        list($where, $filterparams) = $this->outputtable->get_sql_where();
        if ($where) {
            $sql .= ' WHERE '.$where;
            $whereparams = array_merge($whereparams,  $filterparams);
        }

        if ($this->outputtable->get_sql_sort()) {
            $sql .= ' ORDER BY '.$this->outputtable->get_sql_sort().', submissionid ASC';
        } else {
            $sql .= ' ORDER BY u.lastname ASC, submissionid ASC';
        }
        $usersubmissions = $DB->get_recordset_sql($sql, $whereparams);

        foreach ($usersubmissions as $usersubmission) {
            $tablerow = array();

            // picture
            $tablerow[] = $OUTPUT->user_picture($usersubmission, array('courseid' => $COURSE->id));

            // user fullname
            $paramurl = array('id' => $usersubmission->id, 'cover' => 0);
            $url = new moodle_url('/user/view.php', $paramurl);
            $tablerow[] = '<a href="'.$url->out().'">'.fullname($usersubmission).'</a>';

            // users with $usersubmission->submissionid == null have no submissions
            if (!empty($usersubmission->submissionid)) {
                $paramurl = array();
                $paramurl['s'] = $this->surveypro->id;
                $paramurl['userid'] = $usersubmission->id;
                $paramurl['submissionid'] = $usersubmission->submissionid;
                $url = new moodle_url('/mod/surveypro/report/attachments_overview/uploads.php', $paramurl);
                $tablerow[] = '('.$submissionidstring.': '.$usersubmission->submissionid.') <a href="'.$url->out().'">'.$displayuploads.'</a>';
            } else {
                $tablerow[] = $missinguploads;
            }

            // add row to the table
            $this->outputtable->add_data($tablerow);
        }

        $usersubmissions->close();
    }

    /**
     * output_data
     *
     * @param none
     * @return none
     */
    public function output_data() {
        global $OUTPUT;

        echo $OUTPUT->heading(get_string('pluginname', 'surveyproreport_attachments_overview'));
        $this->outputtable->print_html();
    }

    /**
     * check_attachmentitems
     *
     * @param none
     * @return none
     */
    public function check_attachmentitems() {
        global $OUTPUT, $DB;

        $params = array();
        $params['surveyproid'] = $this->surveypro->id;
        $params['plugin'] = 'fileupload';
        $attachmentitems = $DB->count_records('surveypro_item', $params);

        if (!$attachmentitems) {
            $message = get_string('noattachmentitemsfound', 'surveyproreport_attachments_overview');
            echo $OUTPUT->box($message, 'notice centerpara');

            // Finish the page
            echo $OUTPUT->footer();

            die();
        }
    }

    /**
     * prevent_direct_user_input
     *
     * @param none
     * @return none
     */
    public function prevent_direct_user_input() {
        if ($this->surveypro->anonymous) {
            print_error('incorrectaccessdetected', 'surveypro');
        }
    }
}
