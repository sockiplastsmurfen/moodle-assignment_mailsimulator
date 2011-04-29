<?php

require_once("../../../../config.php");
require_once($CFG->dirroot . "/mod/assignment/lib.php");

// Get course ID and assignment ID
$id = optional_param('id', 0, PARAM_INT);         // Course module ID
$a = optional_param('a', 0, PARAM_INT);           // Assignment ID

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

/// Load up the required assignment code
require('assignment.class.php');
$assignmentinstance = new assignment_mailsimulator($cm->id, $assignment, $cm, $course);

$assignmentinstance->view_header('addcontacts');
if(!record_exists('assignment_mailsimulation_contact', 'assignment', $assignmentinstance->assignment->id)) {
    echo notify(get_string('addonecontact', 'assignment_mailsimulator'));
}
echo notify(get_string('deletecontact', 'assignment_mailsimulator'));

require_once('contacts_form.php');
$mform = new contacts_form(null, array('moduleID' => (int) $cm->id, 'assignmentID' => $a));

if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, 'cancelled', 0);
}

if ($fromform = $mform->get_data()) {
    if ($mform->is_validated()) {
        for ($i = 0; $i < $fromform->option_repeats; $i++) {
            $contact = new Object;
            $contact->assignment = $assignment->id;
            $contact->firstname = $fromform->firstname[$i];
            $contact->lastname = $fromform->lastname[$i];
            $contact->email = $fromform->email[$i];

            // Insert/Update record in database
            if ($existingRecord = get_record('assignment_mailsimulation_contact', 'id', $fromform->contactid[$i])) {
                $contact->id = $existingRecord->id;

                if(strlen($contact->firstname.$contact->lastname.$contact->email) == 0) {
                    $assignmentinstance->delete_contact($contact->id);
                } else {
                    update_record('assignment_mailsimulation_contact', $contact);
                }
            } else {
                if(!strlen($contact->firstname.$contact->lastname.$contact->email) == 0) {
                     insert_record('assignment_mailsimulation_contact', $contact);
                }
            }
        }
        redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, 'Add contact', 0);
    }
}

// Get criteria from database
if ($contactlist = get_records_list('assignment_mailsimulation_contact', 'assignment', $a, 'firstname')) {
    // Fill form with data
    $toform = new Object;
    $toform->contactid = array();
    $toform->firstname = array();
    $toform->lastname = array();
    $toform->email = array();
    foreach ($contactlist as $i => $contact) {
        $toform->contactid[] = (int) ($contact->id);
        $toform->firstname[] = $contact->firstname;
        $toform->lastname[] = $contact->lastname;
        $toform->email[] = $contact->email;
    }
    $mform->set_data($toform);
}

$mform->display();
$assignmentinstance->view_footer();
?>
