<?php

require_once($CFG->libdir . '/formslib.php');

class parent_form extends moodleform {

    function definition() {
        global $USER, $CFG;

        // Create form object
        $mform = & $this->_form;

        $cmid = optional_param('id', 0, PARAM_INT);
        $userid = optional_param('userid', 0, PARAM_INT);

        // Course Module Id
        $mform->addElement('hidden', 'id', $cmid);
        $mform->setType('id', PARAM_INT);

        if (isset($this->_customdata->id) && $this->_customdata->id) {
            $mform->addElement('header', '', get_string('updatecorrectiontemplate', 'assignment_mailsimulator'));
        } else {
            $mform->addElement('header', '', get_string('newcorrectiontemplate', 'assignment_mailsimulator'));
        }

        $mform->addElement('hidden', 'parentid', $this->_customdata->id);
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('hidden', 'mailid', $this->_customdata->mailid);
        $mform->setType('mailid', PARAM_INT);

        $mform->addElement('hidden', 'randgroup', $this->_customdata->randgroup);
        $mform->setType('randgroup', PARAM_INT);

        $weights = array();

        for ($i = 1; $i <= $this->_customdata->maxweight; $i++) {
            $weights[$i] = $i;
        }

        if (isset($this->_customdata->weight) && $this->_customdata->weight != 0) {
            $select = $mform->addElement('select', 'weight', get_string('weight', 'assignment_mailsimulator'), array());

            foreach ($weights as $key => $value) {
                if ($key == $this->_customdata->weight) {
                    $select->addOption($value, $key, array('selected' => 'selected'));
                } else {
                    $select->addOption($value, $key);
                }
            }
        } else {
            $mform->addElement('select', 'weight', get_string('weight', 'assignment_mailsimulator'), $weights);
        }
        $mform->setHelpButton('weight', array('weight', get_string('weight', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/'));

        $mform->addElement('hidden', 'deleted', $this->_customdata->deleted);
        $mform->setType('deleted', PARAM_INT);

        $mform->addElement('textarea', 'correctiontemplate', get_string('correctiontemplate', 'assignment_mailsimulator') . ':', array('rows' => 4, 'cols' => 60));
        $mform->setType('correctiontemplate', PARAM_TEXT);
        $mform->setDefault('correctiontemplate', $this->_customdata->correctiontemplate);

        // Buttons for submit and cancel
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('send', 'assignment_mailsimulator'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }

    function validation($data, $files) {
        $errors = array();

        if (strlen(ltrim($data['correctiontemplate'])) < 1) {
            $errors['correctiontemplate'] = 'Rättnigs mallen kan ej vara tom';
        }

        return $errors;
    }

}

?>
