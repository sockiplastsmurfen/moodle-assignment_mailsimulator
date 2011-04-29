<?php

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/validateurlsyntax.php');

class contacts_form extends moodleform {

    function definition() {
        global $USER, $CFG;

        // Create form object
        $mform = & $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['moduleID']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'a', $this->_customdata['assignmentID']);
        $mform->setType('a', PARAM_INT);

        $repeatarray[] = &MoodleQuickForm::createElement('header', '', get_string('contact', 'assignment_mailsimulator') . ' {no}');
        $repeatarray[] = &MoodleQuickForm::createElement('hidden', 'contactid', 0);
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'firstname', get_string('firstname'));
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'lastname', get_string('lastname'));
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'email', get_string('email'));

        $repeatno = count_records('assignment_mailsimulation_contact', 'assignment', $this->_customdata['assignmentID']);
        $repeatno = $repeatno == 0 ? 1 : $repeatno;

        $this->repeat_elements($repeatarray, $repeatno, array(), 'option_repeats', 'option_add_contat_fields', 1, get_string('addnewcontact', 'assignment_mailsimulator'));

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('submit'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }

    function validation($data, $files) {
        $errors = array();

        foreach ($data as $key => $value) {

            if (is_array($value)) {
                for ($i = 0; $i < $data['option_repeats']; $i++) {
                    $inputname = $key . '[' . $i . ']';

                    if (!isset($errcount[$i])) {
                        $errcount[$i] = 0;
                    }

                    if (strlen(ltrim($value[$i])) < 1) {
                        $errors[$inputname] = get_string('err_required', 'form');
                        $errcount[$i] = $errcount[$i] + 1;
                    }
                    if ($key == 'email') {
                        if (!validateEmailSyntax($value[$i])) {
                            $errors[$inputname] =  get_string('err_email', 'form');
                        }
                    }
                }
            }
        }

        // For deletion of contact
        foreach ($errcount as $key => $value) {
            if ($value == 3) {
                unset($errors['firstname[' . $key . ']']);
                unset($errors['lastname[' . $key . ']']);
                unset($errors['email[' . $key . ']']);
            }
        }

        return $errors;
    }

}

?>
