<?php

require_once("../../../../config.php");
require_once($CFG->dirroot . "/mod/assignment/lib.php");

$id = optional_param('id', 0, PARAM_INT);         // Course module ID
$a = optional_param('a', 0, PARAM_INT);           // Assignment ID
$pid = optional_param('pid', 0, PARAM_INT);       // Parent ID
$gid = optional_param('gid', 0, PARAM_INT);       // Group ID
$re = optional_param('re', 0, PARAM_INT);         // Reply 1=one, 2=all
$mid = optional_param('mid', 0, PARAM_INT);

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

$CFG->stylesheets[] = $CFG->wwwroot . '/mod/assignment/type/mailsimulator/styles.php';

/// Load up the required assignment code
require('assignment.class.php');
$assignmentinstance = new assignment_mailsimulator($cm->id, $assignment, $cm, $course);

$teacher = has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $assignmentinstance->cm->id));

if ($teacher) {
    $tab = 'addmail';
} else {
    $tab = 'mail';
}

$assignmentinstance->view_header($tab);

// Edit mail
if ($mid) {
    if (!$teacher) {
        error("You are not allowed to view this page.", $CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id);
    }
    $customdata = get_record('assignment_mailsimulation_mail', 'id', $mid);

    if (!$customdata) {
        error("Mail ID was incorrect", $CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id);
    }
    if (!$customdata->userid == 0) { // Only edit teacher mail
        error("You are not allowed to edit this mail.", $CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id);
    }

    $teacherid = get_field('assignment', 'var3', 'id', $assignmentinstance->assignment->id);
    $contacts = get_records('assignment_mailsimulation_contact', 'assignment', $assignmentinstance->assignment->id);
    $senttoobjarr = get_records_select('assignment_mailsimulation_to', 'mailid = ' . $mid, '', 'contactid');
    $sendtoarr = array();

    foreach ($senttoobjarr as $key => $value) {
        $sendtoarr[$value->contactid] = $value->contactid;
    }

    $teacherobj = get_record_select('user', 'id=' . $teacherid, 'firstname, lastname, email');

    if ($contacts) {
        foreach ($contacts as $key => $con) {
            $contacts[$key] = $con->firstname . ' ' . $con->lastname . ' &lt;' . $con->email . '&gt;';
        }
    }

    $contacts[0] = $teacherobj->firstname . ' ' . $teacherobj->lastname . ' &lt;' . $teacherobj->email . '&gt;';
    $contacts[TO_STUDENT_ID] = get_string('mailtostudent', 'assignment_mailsimulator');
    asort($contacts);

    $customdata->to = $contacts;
    $customdata->sentto = $sendtoarr;
    $customdata->mailid = $customdata->id;
    $customdata->teacher = true;

    $top = $assignmentinstance->get_top_parent_id($mid);
    $inactive = !$assignmentinstance->get_signed_out_status($top);

    $customdata->inactive = $inactive;
    unset($customdata->id);
} else {
    $customdata = $assignmentinstance->prepare_mail($pid);
}

$titlestr = get_string('newmail', 'assignment_mailsimulator');
$mailstr = '';

if ($pid) {

    if (!$mailobj = get_record('assignment_mailsimulation_mail', 'id', $pid)) {
        error("Mail ID is incorrect");
    }
    $message = $assignmentinstance->get_nested_from_child($mailobj);
    $mailstr = '<div style="background-color:#ffffff; margin:10px; padding:5px; border:1px; border-style:solid; border-color:#999999;">' . $message . '</div>';

    if ($re == 3) {
        $customdata->subject = get_string('fwd', 'assignment_mailsimulator') . $mailobj->subject;
        $titlestr = get_string('fwd', 'assignment_mailsimulator') . ' ' . $mailobj->subject;
    } else {
        $customdata->subject = get_string('re', 'assignment_mailsimulator') . $mailobj->subject;
        $titlestr = get_string('re', 'assignment_mailsimulator') . ' ' . $mailobj->subject;
    }
}

$imgurl = $CFG->wwwroot . '/mod/assignment/type/mailsimulator/images/';

echo '<div style="width:80%; margin: auto">';
echo '	<!-- Start Window Top Table -->';
echo '  <table border="0" width="100%"  style="margin-bottom: -4px;">';
echo '      <tr>';
echo '          <td width="32px"><img src="' . $imgurl . 'shadow-top-left.png"></td>';
echo '          <td width="8"><img src="' . $imgurl . 'window-top-left.png"></td>';
echo '          <td class="window-top-bg"><div class="mailtoptitle">' . $titlestr . '</div></td>';
echo '          <td width="8"><img src="' . $imgurl . 'window-top-right.png"></td>';
echo '          <td width="32px"><img src="' . $imgurl . 'shadow-top-right.png"></td>';
echo '      </tr>';
echo '  </table>';
echo '  <!-- End Window Top Table -->';

echo '  <!-- Start Window Content Table -->';
echo '  <table border="0"  width="100%">';
echo '      <tr>';
echo '          <td width="32px" class="shadow-left-bg"></td>';
echo '          <td >';
echo '              <table class="mailmidletable"  width="100%">';
echo '                  <tr>';
echo '                      <td style="background-color:lightgray;">' . $mailstr;

$customdata->reply = $re;

$typearr = get_records('assignment_mailsimulator_filetypes', 'assignment', $assignmentinstance->assignment->id, '', 'filetype');

if ($typearr && $c = count($typearr)) {
    $filestr = '';
    $fileregexp = '(';
    $i = 1;

    foreach ($typearr as $value) {
        $filestr .= $value->filetype . ($c > $i ? ', ' : '');
        $fileregexp .= $value->filetype . ($c > $i ? '|' : '');
        $i++;
    }
    $customdata->file_types_str = $filestr;
    $customdata->file_types_regexp = $fileregexp . ')';
} else {
    $customdata->file_types_str = false;
    $customdata->file_types_regexp = false;
}


require_once('mail_form.php');
$mailform = new mail_form('?gid=' . $gid, $customdata);

if ($mailform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, 'cancelled', 0);
}

if ($fromform = $mailform->get_data()) {
    if ($mailform->is_validated()) {
        if (record_exists('assignment_mailsimulation_mail', 'id', $fromform->mailid)) {
            $assignmentinstance->update_mail($fromform);
        } else {
            $assignmentinstance->insert_mail($fromform, $gid);
        }

        redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, '', 0);
    }
}

$mailform->display();


echo '                      </td>';
echo '                  </tr>';
echo '              </table>';
echo '          </td>';
echo '          <td width="32px" class="shadow-right-bg"></td>';
echo '      </tr>';
echo '  </table>';
echo '  <!-- End Window Content Table -->';

echo '	<!-- Start Bottom Shadow Table -->';
echo '  <table border="0"  width="100%">';
echo '      <tr>';
echo '          <td width="32px"><img src="' . $imgurl . 'shadow-bottom-left.png"></td>';
echo '          <td>';
echo '              <table border="0"  width="100%">';
echo '                  <tr>';
echo '                      <td width="32px"><img src="' . $imgurl . 'shadow-bottom-center-left.png"></td>';
echo '                      <td class="shadow-bottom-bg">&nbsp;</td>';
echo '                      <td width="32px"><img src="' . $imgurl . 'shadow-bottom-center-right.png"></td>';
echo '                  </tr>';
echo '              </table>';
echo '          </td>';
echo '          <td width="32px"><img src="' . $imgurl . 'shadow-bottom-right.png"></td>';
echo '      </tr>';
echo '  </table>';
echo '  <!-- End Bottom Shadow Table -->';
echo '</div>';

$assignmentinstance->view_footer();
?>
