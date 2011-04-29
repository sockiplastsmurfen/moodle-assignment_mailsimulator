<?php

require("../../../../config.php");
require("../../lib.php");
require("assignment.class.php");

$id = required_param('id', PARAM_INT);          // Course Module ID
$userid = required_param('userid', PARAM_INT);  // User ID

if (!$cm = get_coursemodule_from_id('assignment', $id)) {
    error("Course Module ID was incorrect");
}

if (!$assignment = get_record("assignment", "id", $cm->instance)) {
    error("Assignment ID was incorrect");
}

if (!$course = get_record("course", "id", $assignment->course)) {
    error("Course is misconfigured");
}

if (!$user = get_record("user", "id", $userid)) {
    error("User is misconfigured");
}

require_login($course->id, false, $cm);


$teacher = has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $cm->id));

if (!$teacher && ($USER->id != $user->id)) {
    error("You can not view this assignment");
}

if ($assignment->assignmenttype != 'mailsimulator') {
    error("Incorrect assignment type");
}

$assignmentinstance = new assignment_mailsimulator($cm->id, $assignment, $cm, $course);
print_header(fullname($user, true) . ': ' . $assignmentinstance->assignment->name);
$assignmentinstance->view_grading_feedback($userid);
close_window_button();
print_footer('none');
?>
