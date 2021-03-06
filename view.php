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
 * Prints a particular instance of englishcentral
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_englishcentral
 * @copyright  2014 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/englishcentral/lib.php');;

$id = optional_param('id', 0, PARAM_INT); // course_module ID
$ecid = optional_param('ecid', 0, PARAM_INT);  // englishcentral instance ID

if ($id) {
    $cm = get_coursemodule_from_id('englishcentral', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $instance = $DB->get_record('englishcentral', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($ecid) {
    $instance = $DB->get_record('englishcentral', array('id' => $ecid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $instance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('englishcentral', $instance->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Trigger module viewed event.
$event = \mod_englishcentral\event\course_module_viewed::create(array(
   'objectid' => $instance->id,
   'context' => $context
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('englishcentral', $instance);
$event->trigger();

//if we got this far, we can consider the activity "viewed"
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

/// Set up the page header
$PAGE->set_url('/mod/englishcentral/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');

$ec = \mod_englishcentral\activity::create($instance, $cm, $course, $context);
$auth = \mod_englishcentral\auth::create($ec);

$renderer = $PAGE->get_renderer($ec->plugin);
$renderer->attach_activity_and_auth($ec, $auth);

echo $renderer->header($ec->get_string('view'));

if ($msg = $auth->missing_config()) {
    echo $renderer->show_missingconfig($msg);
    die;
}

if ($msg = $auth->invalid_config()) {
    echo $renderer->show_invalidconfig($msg);
    die;
}

if ($ec->not_available()) {
    echo $renderer->show_notavailable();
    die;
}

echo $renderer->show_intro();
//echo $renderer->show_dates_available();

// get ECSDK javascript object
$PAGE->requires->js($auth->get_js_url());

$opts = array('accept1'       => \mod_englishcentral\auth::ACCEPT_V1,
              'consumerkey'   => $auth->consumerkey,
              'sdktoken'      => $auth->get_sdk_token(),
              'authorization' => $auth->get_authorization(),
              'sitelanguage'  => $auth->get_site_language(),
              'searchurl'     => $auth->get_search_url(),
              'fetchurl'      => $auth->get_fetch_url(),
              'cmid'          => $ec->cm->id,
              'moodlesesskey' => sesskey(),
              'viewajaxurl'   => $ec->get_viewajax_url(false),
              'videoinfourl'  => $ec->get_videoinfo_url(false),
              'targetwindow'  => 'EC');

$PAGE->requires->js_call_amd("$ec->plugin/view", 'init', array($opts));

// We could preload the string cache - it might speed things up.
// Otherwise, the strings will be fetched as needed by "core/str".
//$strings = array();
//$PAGE->strings_for_js($strings, $ec->plugin);

echo $renderer->show_progress();

if ($ec->viewable) {
    echo $renderer->show_videos();
    echo $renderer->show_search();
    echo $renderer->show_player();
} else {
    echo $renderer->show_notviewable($ec);
}

echo $renderer->footer();
