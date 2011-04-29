<?php

/*
  id
  parent
  assignment
  userid
  priority
  sender
  subject
  message
  timesent
 */
require_once($CFG->libdir . '/formslib.php');

class mail_form extends moodleform {

    function definition() {
        global $USER, $CFG, $COURSE;

        // Create form object
        $mform = & $this->_form;

        $cmid = optional_param('id', 0, PARAM_INT);
        $a = optional_param('a', 0, PARAM_INT);
        $userid = optional_param('userid', 0, PARAM_INT);

        // Course Module Id
        $mform->addElement('hidden', 'id', $cmid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'mailid', $this->_customdata->mailid);
        $mform->setType('mailid', PARAM_INT);

        $mform->addElement('hidden', 'parent', $this->_customdata->parent);
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('hidden', 'assignment', $this->_customdata->assignment);
        $mform->setType('assignment', PARAM_INT);

        $mform->addElement('hidden', 'userid', $this->_customdata->userid);
        $mform->setType('userid', PARAM_INT);


        $prio = array(0 => '- ' . get_string('low', 'assignment_mailsimulator'), 1 => '! ' . get_string('medium', 'assignment_mailsimulator'), 2 => '!! ' . get_string('high', 'assignment_mailsimulator'));
        $mform->addElement('select', 'priority', get_string('priority', 'assignment_mailsimulator'), $prio);

        $to = $this->_customdata->to;

        // Student Reply mail
        if ($this->_customdata->parent != 0 && !$this->_customdata->teacher) {
            $replyobj = get_record('assignment_mailsimulation_mail', 'id', $this->_customdata->parent);

            // Reply To All --------- or forward
            if ($this->_customdata->reply > 1) {
                $toarr = get_records('assignment_mailsimulation_to', 'mailid', $this->_customdata->parent);
                foreach ($toarr as $value) {
                    $replyto[$value->contactid] = $value->contactid;
                }
            }

            // Reply To Sender
            if ($replyobj->userid != 0) {
                $replyto[TO_STUDENT_ID] = TO_STUDENT_ID;
            } else {
                $replyto[$replyobj->sender] = $replyobj->sender;
            }
            # -------------

            if ($this->_customdata->reply <= 2) {
                $select = $mform->addElement('select', 'to', get_string('to'), array());

                if ($this->_customdata->reply == 2)
                    $select->setMultiple(true);

                foreach ($to as $key => $value) {

                    if (key_exists($key, $replyto)) {
                        $select->addOption($value, $key, array('selected' => 'selected'));
                    }
                }
            } elseif ($this->_customdata->reply == 3) {
                $select = $mform->addElement('select', 'to', get_string('to'), $to);
                $select->setMultiple(true);
            }

            // Teacher New mail
        } else {
            if ($this->_customdata->reply) {
                $replyobj = get_record('assignment_mailsimulation_mail', 'id', $this->_customdata->parent);

                if ($this->_customdata->reply == 2) {
                    $toarr = get_records('assignment_mailsimulation_to', 'mailid', $this->_customdata->parent);
                    foreach ($toarr as $value) {
                        $replyto[$value->contactid] = $value->contactid;
                    }
                }

                // Reply To Sender
                if ($replyobj->userid != 0) {
                    $replyto[TO_STUDENT_ID] = TO_STUDENT_ID;
                } else {
                    $replyto[$replyobj->sender] = $replyobj->sender;
                }

                $select = $mform->addElement('select', 'to', get_string('to'), array());
                $select->setMultiple(true);

                foreach ($to as $key => $value) {
                    if (key_exists($key, $replyto)) {
                        $select->addOption($value, $key, array('selected' => 'selected'));
                    } else {
                        $select->addOption($value, $key);
                    }
                }
            } else {
                if (isset($this->_customdata->sentto)) {
                    $select = $mform->addElement('select', 'to', get_string('to'), array());
                    $select->setMultiple(true);

                    foreach ($to as $key => $value) {

                        if (key_exists($key, $this->_customdata->sentto)) {
                            $select->addOption($value, $key, array('selected' => 'selected'));
                        } else {
                            $select->addOption($value, $key);
                        }
                    }
                } else {
                    $select = $mform->addElement('select', 'to', get_string('to'), $to, array('size' => count($to)));
                    $select->setMultiple(true);
                }
            }
        }

        if (!$this->_customdata->teacher) {
            $mform->addElement('hidden', 'sender', 0);
            $mform->setType('sender', PARAM_INT);
            $mform->addElement('hidden', 'timesent', time());
            $mform->setType('timesent', PARAM_INT);
        } else {
            $from = get_field('assignment_mailsimulation_to', 'contactid', 'mailid ', $this->_customdata->parent);
            unset($to[9999999]);

            if ($from) {
                $select = $mform->addElement('select', 'sender', get_string('from'), array());

                foreach ($to as $key => $value) {
                    if ($key == $from) {
                        $select->addOption($value, $key, array('selected' => 'selected'));
                    } else {
                        $select->addOption($value, $key);
                    }
                }
            } else {
                $mform->addElement('select', 'sender', get_string('from'), $to);
            }

            $mform->addElement('date_time_selector', 'timesent', get_string('timesent', 'assignment_mailsimulator'));
            $mform->setType('timesent', PARAM_TEXT);
            $mform->setDefault('timesent', $this->_customdata->timesent);
        }

        $mform->addElement('text', 'subject', get_string('subject', 'assignment_mailsimulator'), array('size' => '83'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->setDefault('subject', $this->_customdata->subject);

        if ($this->_customdata->teacher) {
            $mform->addElement('htmleditor', 'message', get_string('message', 'assignment_mailsimulator'), array('cols' => 60, 'rows' => 30));
            $mform->setType('message', PARAM_RAW); // to be cleaned before display
            $mform->setHelpButton('message', array('reading', 'writing', 'richtext'), false, 'editorhelpbutton');
        } else {
            $mform->addElement('textarea', 'message', get_string('message', 'assignment_mailsimulator'), array('rows' => 10, 'cols' => 60));
            $mform->setType('message', PARAM_TEXT);
        }

        $mform->setDefault('message', $this->_customdata->message);

        if (((!isset($this->_customdata->inactive) || $this->_customdata->inactive) && $this->_customdata->file_types_str)) {
            require_once($CFG->libdir . '/uploadlib.php');

            $modbytes = get_field('assignment', 'maxbytes', 'id', $this->_customdata->assignment);
            $uploadmanager = new upload_manager('attachment', true, false, $COURSE, false, $modbytes, true, true, false);
            $this->set_upload_manager($uploadmanager);

            $mform->addElement('file', 'attachment', get_string('attachment', 'forum'));
            $mform->addRule(
                    'attachment',
                    get_string('err_invalidfile', 'assignment_mailsimulator') . '<br />' . $this->_customdata->file_types_str,
                    'filename',
                    '/\\.' . $this->_customdata->file_types_regexp . '$/');
        }

        // Buttons for submit and cancel
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('send', 'assignment_mailsimulator'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }

    function validation($data, $files) {
        $errors = array();

        if (strlen(ltrim($data['subject'])) < 1) {
            $errors['subject'] = get_string('err_emptysubject', 'assignment_mailsimulator');
        }
        if (strlen(ltrim($data['message'])) < 1) {
            $errors['message'] = get_string('err_emptymessage', 'assignment_mailsimulator');
        }
        if ($data['timesent'] > time()) {
            $errors['timesent'] = get_string('err_date', 'assignment_mailsimulator');
        }
        if (!isset($data['to'])) {
            $errors['to'] = get_string('err_reciever', 'assignment_mailsimulator');
        }

        return $errors;
    }

}

?>
