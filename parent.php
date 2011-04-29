<?php

require_once("../../../../config.php");
require_once($CFG->dirroot . "/mod/assignment/lib.php");

// Get course ID and assignment ID
$id = optional_param('id', 0, PARAM_INT);         // Course module ID
$a = optional_param('a', 0, PARAM_INT);           // Assignment ID
$mid = optional_param('mid', 0, PARAM_INT);       // Mail ID
$gid = optional_param('gid', 0, PARAM_INT);       // Group ID

if ($id) {
    if (!$cm = get_coursemodule_from_id('assignment', $id)) {
        error("Course Module ID was incorrect");
    }

    if (!$assignment = get_record("assignment", "id", $cm->instance)) {
        error("assignment ID was incorrect");
    }

    if (!$course = get_record("course", "id", $assignment->course)) {
        error("Course is misconfigured");
    }
} else {
    if (!$assignment = get_record("assignment", "id", $a)) {
        error("Course module is incorrect");
    }
    if (!$course = get_record("course", "id", $assignment->course)) {
        error("Course is misconfigured");
    }
    if (!$cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
        error("Course Module ID was incorrect");
    }
}

require('assignment.class.php');

$assignmentinstance = new assignment_mailsimulator($cm->id, $assignment, $cm, $course);
$mailstr = 'NO MAIL IN URL<br>';

if ($mid) {
    // Check if mail exists. A parent must have a mail.
    if (!$mailobj = get_record('assignment_mailsimulation_mail', 'id', $mid)) {
        error("Mail ID is incorrect");
    }
    $mail = $assignmentinstance->get_nested_reply_object($mailobj);

    if (!$mail) {
        $mail = $mailobj;
    }
    $mailstr = '<div style="background-color:#ffffff; margin:auto; padding:5px; border:1px; border-style:solid; border-color:#999999; width:80%">' . format_text($mail->message, FORMAT_MOODLE) . '</div>';
    $customdata = $assignmentinstance->prepare_parent($mid, $gid);
} else {
    $customdata = $assignmentinstance->prepare_parent();
}

if ($existingparent = get_record("assignment_mailsimulation_parent_mail", "mailid", $mid)) {
    $existingparent->maxweight = $customdata->maxweight;
    $existingparent->randgroup = $customdata->randgroup;
    $customdata = $existingparent;
}

if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $assignmentinstance->cm->id))) {
    if (!$existingparent) {
        insert_record('assignment_mailsimulation_parent_mail', $customdata);
    }
    redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, '', 0);
}

$assignmentinstance->view_header('addmail');
echo $mailstr;

require_once('parent_form.php');

$pform = new parent_form(null, $customdata);

if ($pform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, 'cancelled', 0);
}

if ($fromform = $pform->get_data()) {
    if ($pform->is_validated()) {
        $pstatus = 'Parent ';

        if (isset($fromform->parentid) && record_exists('assignment_mailsimulation_parent_mail', 'id', $fromform->parentid)) {
            $fromform->id = $fromform->parentid;
            update_record('assignment_mailsimulation_parent_mail', $fromform);
            $pstatus .= $fromform->id . ' UPDATED';
        } else {
            $pid = insert_record('assignment_mailsimulation_parent_mail', $fromform);
            $pstatus .= $pid . ' ADDED';
        }
        redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, $pstatus, 0);
    }
}

if (optional_param('updated', false, PARAM_BOOL)) {
    notify(get_string('criteriaupdated', 'assignment_peerreview'), 'notifysuccess');
}

$pform->display();
$assignmentinstance->view_footer();
?>