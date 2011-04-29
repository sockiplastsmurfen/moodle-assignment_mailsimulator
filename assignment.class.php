<?php

/*
 * Database assignment:
 * var1 = the total number of mail (groups)
 * var2 = the maxweight
 * var3 = default teacher mail
 *
 * Database assignment_submissions field data1:
 * 1 = inprogress
 * 2 = readyforgrading
 * 3 = needcompletion
 */

DEFINE('TO_STUDENT_ID', 9999999); // To identify when a mail is sent to a student

class assignment_mailsimulator extends assignment_base {

    function assignment_mailsimulator($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'mailsimulator';
    }

    function view() {
        global $USER, $CFG;

        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        require_capability('mod/assignment:view', $context);
        $teacher = has_capability('mod/assignment:grade', $context);

        $CFG->stylesheets[] = $CFG->wwwroot . '/mod/assignment/type/mailsimulator/styles.php';
        $add = optional_param('add', 0, PARAM_INT);         // Add mail
        $delete = optional_param('delete', 0, PARAM_INT);   // Delete mail
        $mid = optional_param('mid', 0, PARAM_INT);         // Mail ID
        $addc = optional_param('addc', 0, PARAM_INT);       // Add Contact
        $pid = optional_param('pid', 0, PARAM_INT);         // Group ID
        $gid = optional_param('gid', 0, PARAM_INT);         // Parent ID
        $route = optional_param('route', 0, PARAM_INT);     // Page routing 0=inbox, 1=sent, drafts=2, trash=3, description=10
        $file = optional_param('file', null, PARAM_FILE);   // Used when deleting files

        $this->check_assignment_setup();

        if ($add) {
            $this->add_mail($pid, $gid);
        }

        if ($teacher) {
            if ($file) {
                $this->delete_file();
            }
            if ($addc) {
                $this->add_contacts();
            }

            if ($delete == 1) {
                $this->delete_mail_and_children($mid);
            } elseif ($delete == 2) {
                $this->handle_trash($mid);
            } elseif ($delete == 3) {
                $this->handle_trash($mid, false);
                $route = 3;
            }

            // Teacher views
            if ($route == 10) {
                $this->view_header('description');
                $this->view_intro();
            } elseif ($route == 3) {
                $this->view_header('trashmail');
                $this->view_all_mail(true);
            } else {
                $this->view_header();
                $this->view_all_mail();
            }
        } else {

            // Student views
            if ($route == 10) {
                $this->view_header('description');
                $this->view_intro();
            } else {
                $this->view_header();
                $this->view_mailwindow();
                $this->view_feedback();
                $this->view_grading_feedback($USER->id);
            }
        }

        $this->view_dates();
        print_footer();
    }

    function copy_teacher_attachments() {
        global $CFG, $USER;

        $teacherbasedir = $this->file_area(0);
        $studentbasedir = $this->file_area($USER->id);

        $this->recurse_copy_attachments($teacherbasedir, $studentbasedir);
    }

    // Copy teacher attachments from teachers folder to students folder
    // /6/moddata/assignment/<assignment id>/<user id>/<mail id>/file.ex
    // teachers user id is 0
    function recurse_copy_attachments($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);

        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    $this->recurse_copy_attachments($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    // Get attachment string for a mail
    function get_files_str($mid, $userid) {
        global $CFG, $USER;

        if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {
            $userid = $USER->id;
        }
        $filearea = $this->file_area_name($userid);
        $output = '';
        $basedir = $this->file_area($userid);

        if ($basedir) {
            $basedir .= '/' . $mid;
            $files = get_directory_list($basedir);

            if ($files) {
                require_once($CFG->libdir . '/filelib.php');

                foreach ($files as $key => $file) {
                    $icon = mimeinfo('icon', $file);
                    $ffurl = get_file_url("$filearea/$mid/$file", array('forcedownload' => 1));
                    $output .= '<img src="' . $CFG->pixpath . '/f/' . $icon . '" class="icon" alt="' . $icon . '" />' .
                            '<a href="' . $ffurl . '" >' . $file . '</a> ';

                    if ($userid == 0) {
                        $top = $this->get_top_parent_id($mid);
                        $inactive = !$this->get_signed_out_status($top);

                        if ($inactive) {
                            $delurl = "$CFG->wwwroot/mod/assignment/view.php?id={$this->cm->id}&amp;file=$file&amp;userid={$userid}&amp;mid=$mid";
                            $output .= '<a href="' . $delurl . '">&nbsp;'
                                    . '<img title="' . get_string('delete') . '" src="' . $CFG->pixpath . '/t/delete.gif" class="iconsmall" alt="" /></a> ';
                        }
                    }
                    $output .= '<br />';
                }
            }
        }

        if ($output == '') {
            return false;
        }
        $output = '<div class="files">' . $output . '</div>';

        return $output;
    }

    function delete_file() {
        global $CFG;

        $returnurl = 'view.php?id=' . $this->cm->id;

        if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {
            $this->view_header();
            error('You do not have access to this page.', $returnurl);
        }

        $file = required_param('file', PARAM_FILE);
        $mid = required_param('mid', PARAM_INT);
        $confirm = optional_param('confirm', 0, PARAM_BOOL);

        $userid = 0;
        $dir = $this->file_area_name($userid) . '/' . $mid;

        if (!data_submitted('nomatch') or !$confirm or !confirm_sesskey()) {


            $optionsyes = array('id' => $this->cm->id, 'file' => $file, 'confirm' => 1, 'sesskey' => sesskey(), 'mid' => $mid);
            $this->view_header();
            print_heading(get_string('delete'));
            notice_yesno(get_string('confirmdeletefile', 'assignment', $file), $returnurl, 'view.php', $optionsyes, array('id' => $this->cm->id), 'post', 'get');
            $this->view_footer();

            die;
        }

        $filepath = $CFG->dataroot . '/' . $dir . '/' . $file;

        if (file_exists($filepath)) {
            if (@unlink($filepath)) {
                redirect($returnurl);
            }
        }

        // print delete error
        $this->view_header();
        notify(get_string('deletefilefailed', 'assignment'));
        print_continue($returnurl);
        $this->view_footer();

        die;
    }

    // Assign mail to student the first time the student acces the assignment
    function assign_student_parents() {
        global $CFG, $USER;

        $sql = 'SELECT m.id, p.randgroup FROM ' . $CFG->prefix . 'assignment_mailsimulation_mail AS m
                LEFT JOIN ' . $CFG->prefix . 'assignment_mailsimulation_parent_mail AS p ON m.id = p.mailid
                WHERE m.assignment = ' . $this->assignment->id . '
                AND m.userid = 0
                AND m.parent = 0
                AND p.deleted = 0
                ORDER BY p.randgroup';

        $assignmentparens = get_records_sql($sql);
        $groupedparentids = array();

        foreach ($assignmentparens as $key => $value) {
            $groupedparentids[$value->randgroup][] = $value->id;
        }

        foreach ($groupedparentids as $key => $value) {
            $signedoutmailobj = new stdClass();
            $signedoutmailobj->userid = $USER->id;
            $signedoutmailobj->gainedweight = 0;
            $signedoutmailobj->comment = '';

            $count = count($value) - 1;

            if ($count > 0)
                $signedoutmailobj->mailid = $value[rand(0, $count)];
            else
                $signedoutmailobj->mailid = $value[0];

            insert_record('assignment_mailsimulation_signed_out_mail', $signedoutmailobj);
        }
    }

    function get_user_parents($userid=null, $forgrading=false) {
        global $CFG, $USER;

        if (!$userid) {
            $userid = $USER->id;
        }

        if ($forgrading) {
            $sql = 'SELECT sm.id, sm.mailid, p.weight, sm.gainedweight, sm.comment, m.sender, m.subject, m.message, p.correctiontemplate, m.timesent, m.priority, m.attachment, m.userid
                    FROM ' . $CFG->prefix . 'assignment_mailsimulation_signed_out_mail AS sm
                    LEFT JOIN ' . $CFG->prefix . 'assignment_mailsimulation_parent_mail AS p ON sm.mailid = p.mailid
                    LEFT JOIN ' . $CFG->prefix . 'assignment_mailsimulation_mail AS m ON m.id = sm.mailid
                    WHERE sm.userid = ' . $userid . '
                    AND m.assignment = ' . $this->assignment->id;
        } else {
            $sql = 'SELECT signed.mailid AS id, m.userid, m.parent, m.priority, m.sender, m.subject, m.message, m.timesent, m.attachment
                    FROM ' . $CFG->prefix . 'assignment_mailsimulation_signed_out_mail AS signed
                    LEFT JOIN ' . $CFG->prefix . 'assignment_mailsimulation_mail as m ON m.id = signed.mailid
                    WHERE signed.userid = ' . $userid . '
                    AND m.assignment = ' . $this->assignment->id;
        }

        return get_records_sql($sql);
    }

    function get_user_sent($userid=null) {
        global $USER;

        if (!$userid) {
            $userid = $USER->id;
        }

        $select = 'userid = ' . $userid . ' AND assignment = ' . $this->assignment->id;
        return get_records_select('assignment_mailsimulation_mail', $select, 'timesent DESC');
    }

    function sidebar() {
        global $CFG;

        $route = optional_param('route', 0, PARAM_INT);
        $imgurl = $CFG->wwwroot . '/mod/assignment/type/mailsimulator/images/';

        $sidebarstr = '<div class="mailboxheader">' . get_string('mailboxes', 'assignment_mailsimulator') . '</div>';
        $sidebarstr .= '<div class="' . ($route == 0 ? 'mailboxselect' : 'mailbox') . '"><img src="' . $imgurl . '/inbox.png"><a href="' . $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&route=0">' . get_string('inbox', 'assignment_mailsimulator') . '</a></div>';
        $sidebarstr .= '<div class="' . ($route == 1 ? 'mailboxselect' : 'mailbox') . '"><img src="' . $imgurl . '/sent.png"><a href="' . $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&route=1">' . get_string('sent', 'assignment_mailsimulator') . '</a></div>';

        return $sidebarstr;
    }

    function topbar() {
        global $CFG;

        $submission = $this->get_submission();
        $mid = optional_param('mid', 0, PARAM_INT);       // Mail id
        $link = $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&add=1&re=';
        $imgurl = $CFG->wwwroot . '/mod/assignment/type/mailsimulator/images/';

        if ($mid) {
            $topmenu = '<!-- Start Mail Top Menu-->
                            <table border="0px" >
                                <tr>
                                    <td width="92"></td>
                                    <td >
                                        <a href="' . $link . '1&pid=' . $mid . '" title="' . get_string('reply', 'assignment_mailsimulator') . '" onmouseover="document.re.src=\'' . $imgurl . 'button-reply-down.png\'" onmouseout="document.re.src=\'' . $imgurl . 'button-reply.png\'">
                                            <img name="re" src="' . $imgurl . 'button-reply.png">
					</a>
                                    </td>
                                    <td >
                                        <a href="' . $link . '2&pid=' . $mid . '" title="' . get_string('replyall', 'assignment_mailsimulator') . '" onmouseover="document.all.src=\'' . $imgurl . 'button-replyall-down.png\'" onmouseout="document.all.src=\'' . $imgurl . 'button-replyall.png\'">
                                            <img name="all" src="' . $imgurl . 'button-replyall.png">
                                        </a>
                                    </td>
                                    <td >
                                        <a href="' . $link . '3&pid=' . $mid . '" title="' . get_string('forward', 'assignment_mailsimulator') . '" onmouseover="document.fwd.src=\'' . $imgurl . 'button-forward-down.png\'" onmouseout="document.fwd.src=\'' . $imgurl . 'button-forward.png\'">
                                            <img name="fwd" src="' . $imgurl . 'button-forward.png">
                                        </a>
                                    </td>
                                    <td width="10">&nbsp;</td>
                                    <td >
                                        <a href="' . $link . '0&pid=0" title="' . get_string('newmail', 'assignment_mailsimulator') . '" onmouseover="document.newmail.src=\'' . $imgurl . 'button-newmail-down.png\'" onmouseout="document.newmail.src=\'' . $imgurl . 'button-newmail.png\'">
                                            <img name="newmail" src="' . $imgurl . 'button-newmail.png">
                                        </a>
                                    </td>

                                </tr>
                            </table>
                            <!-- End Mail Top Menu-->';
        } else {
            $topmenu = '<!-- Start Mail Top Menu-->
                            <table border="0px" >
                                <tr>
                                    <td width="92"></td>
                                    <td >
                                        <img name="re" src="' . $imgurl . 'button-reply-dissabled.png">
                                    </td>
                                    <td >
                                        <img name="all" src="' . $imgurl . 'button-replyall-dissabled.png">
                                    </td>
                                    <td >
                                        <img name="fwd" src="' . $imgurl . 'button-forward-dissabled.png">
                                    </td>
                                    <td width="10">&nbsp;</td>
                                    <td >
                                        <a href="' . $link . '0&pid=0" title="' . get_string('newmail') . '" onmouseover="document.newmail.src=\'' . $imgurl . 'button-newmail-down.png\'" onmouseout="document.newmail.src=\'' . $imgurl . 'button-newmail.png\'">
                                            <img name="newmail" src="' . $imgurl . 'button-newmail.png">
                                        </a>
                                    </td>

                                </tr>
                            </table>
                            <!-- End Mail Top Menu-->';
        }

        return $topmenu;
    }

    // Student view for Sent mail and Inbox
    function view_mailwindow() {
        global $CFG, $USER;

        $submission = $this->get_submission($USER->id);

        if (!empty($this->assignment->timedue) && time() > $this->assignment->timedue && $this->assignment->preventlate) {
            return;
        }

        // data1:
        // 1 = inprogress, 2 = readyforgrading, 3 = needcompletion
        if (isset($submission->data1) && $submission->data1 == 2) {
            return;
        }
        $route = optional_param('route', 0, PARAM_INT);     // Page routing 0=inbox, 1=sent, drafts=2, trash=3, description=10
        $mid = optional_param('mid', 0, PARAM_INT);         // Mail id

        $imgurl = $CFG->wwwroot . '/mod/assignment/type/mailsimulator/images/';
        $mailcontent = '&nbsp;';
        $mailheaders = '';

        // SENT VIEW
        if ($route == 1) {
            $sentarr = $this->get_user_sent();
            $titlestr = get_string('sent', 'assignment_mailsimulator') . ' (' . count($sentarr) . ' ' . get_string('mail', 'assignment_mailsimulator') . ')';

            if ($sentarr) {
                $mailcount = count($sentarr);

                foreach ($sentarr as $k => $sentmail) {
                    $link = $CFG->pagepath . '?id=' . $this->cm->id . '&mid=' . $sentmail->id . '&route=' . $route;
                    $attachment = false;

                    if ($sentmail->attachment == 1) {
                        $attachment = true;
                    }
                    $mailheaders .= $this->mail_header($sentmail, $link, $attachment);

                    if ($mid == $sentmail->id) {
                        $key = $k;
                    }
                }

                if (isset($key)) {
                    $mailcontent = $this->mail_body($sentarr[$key], true);
                }
            }
        } else {

            // INBOX VIEW
            if (!$parentmailarr = $this->get_user_parents()) {
                $this->copy_teacher_attachments();
                $this->assign_student_parents();
                $this->new_submission();
                redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id, '', 0);
            }

            $replyobject = false;
            $titlestr = get_string('inbox', 'assignment_mailsimulator') . ' (' . count($parentmailarr) . ' ' . get_string('mail', 'assignment_mailsimulator') . ')';

            foreach ($parentmailarr as $mailobj) {
                $nested = $this->get_nested_reply_object($mailobj);

                if ($nested)
                    $replyobject[] = $nested;
                else {
                    $mailobj->message = '<div class="mailmessage">' . ($mailobj->attachment ? $this->get_files_str($mailobj->id, $mailobj->userid) : '') . format_text($mailobj->message, FORMAT_MOODLE) . '</div>';
                    $replyobject[] = $mailobj;
                }
            }

            $replyobject = $this->vsort($replyobject, 'timesent', false);
            $key = null;

            foreach ($replyobject as $k => $m) {
                $link = $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&mid=' . $m->id;
                $attachment = false;

                if ($m->attachment == 1) {
                    $attachment = true;
                }
                $mailheaders .= $this->mail_header($m, $link, $attachment);

                if ($mid == $m->id) {
                    $key = $k;
                }
            }

            if (isset($key)) {
                $mailcontent = $this->mail_body($replyobject[$key]);
            }
        }

        // Mailbox printout
        ##########################
        echo '<div class="mailboxwrapper">';
        echo '	<!-- Start Window Top Table -->
                <table border="0" width="100%"  style="margin-bottom: -4px;">
                        <tr>
                                <td width="32px"><img src="' . $imgurl . 'shadow-top-left.png"></td>
                                <td width="8"><img src="' . $imgurl . 'window-top-left.png"></td>
                                <td class="window-top-bg">
                                <div class="mailtoptitle">' . $titlestr . '</div>';
        echo $this->topbar();

        echo '              </td>
                                <td width="8"><img src="' . $imgurl . 'window-top-right.png"></td>
                                <td width="32px"><img src="' . $imgurl . 'shadow-top-right.png"></td>
                        </tr>
                </table>
                <!-- End Window Top Table -->

                <!-- Start Window Content Table -->
                <table border="0"  width="100%">
                        <tr>
                                <td width="32px" class="shadow-left-bg"></td>
                                <td >
                                        <table class="mailmidletable"  width="100%">
                                                <tr>
                                                        <td class="mailboxes">' . $this->sidebar() . '</td>
                                                        <td class="mailheaders"><div class="scroll">' . $mailheaders . '</div></td>
                                                        <td>' . $mailcontent . '</td>
                                                </tr>
                                        </table>
                                </td>
                                <td width="32px" class="shadow-right-bg"></td>
                        </tr>
                </table>
                <!-- End Window Content Table -->';

        echo '	<!-- Start Bottom Shadow Table -->
                <table border="0"  width="100%">
                    <tr>
			<td width="32px"><img src="' . $imgurl . 'shadow-bottom-left.png"></td>
			<td>
                            <table border="0"  width="100%">
                                <tr>
                                    <td width="32px"><img src="' . $imgurl . 'shadow-bottom-center-left.png"></td>
                                    <td class="shadow-bottom-bg">&nbsp;</td>
                                    <td width="32px"><img src="' . $imgurl . 'shadow-bottom-center-right.png"></td>
				</tr>
                            </table>
			</td>
                        <td width="32px"><img src="' . $imgurl . 'shadow-bottom-right.png"></td>
                    </tr>
                </table>
                <!-- End Bottom Shadow Table -->
        </div>';
        ###########################
    }

    // Function for sorting mail
    function vsort($array, $id="id", $sort_ascending=true) {
        $temp_array = array();

        while (count($array) > 0) {
            $lowest_id = 0;
            $index = 0;

            foreach ($array as $item) {
                if (isset($item->$id)) {
                    if ($array[$lowest_id]->$id) {
                        if ($item->$id < $array[$lowest_id]->$id) {
                            $lowest_id = $index;
                        }
                    }
                }
                $index++;
            }

            $temp_array[] = $array[$lowest_id];
            $array = array_merge(array_slice($array, 0, $lowest_id), array_slice($array, $lowest_id + 1));
        }

        if ($sort_ascending) {
            return $temp_array;
        } else {
            return array_reverse($temp_array);
        }
    }

    function mail_header($obj, $link='#', $attachment = false) {
        global $USER, $CFG;

        $selected = optional_param('mid', 0, PARAM_INT);
        $route = optional_param('route', 0, PARAM_INT);


        if ($selected == $obj->id) {
            $stylelink = 'class="mailheadertableselected"';
            $datestyle = 'class="headerdateselected"';
        } else {
            $stylelink = 'class="mailheadertable" onclick="window.location.href=\'' . $link . '\';"';
            $datestyle = 'class="headerdate"';
        }

        $from = $this->get_sender_string($obj);

        if (!$route) {
            $statusobj = $this->get_mail_status($obj->id);

            if ($selected == $obj->id && $statusobj->status == 0) {
                $statusobj->status = $this->set_mail_status($statusobj->mailid, 1);
            }

            $statusstr = $this->get_mail_status_img($statusobj->status);
        } else {
            $statusstr = '&nbsp;&nbsp;';
        }

        if ($attachment) {
            $attachment = '<img src="' . $CFG->wwwroot . '/mod/assignment/type/mailsimulator/images/attachment.png">';
        }

        $header = '<table width="100%" ' . $stylelink . ' >';
        $header .= '    <tr>';
        $header .= '        <td>' . $attachment . '</td>';
        $header .= '        <td class="mailheadertd"><table width="100%"><tr><td><strong>' . $from . '</strong></td><td ' . $datestyle . '>' . date('Y-m-d', $obj->timesent) . '&nbsp;</td></tr></table></td>';
        $header .= '    </tr><tr>';
        $header .= '        <td class="statusimg">' . $statusstr . '</td>';
        $header .= '        <td><strong>' . $this->get_prio_string($obj->priority) . '</strong> ' . $obj->subject . '</td>';
        $header .= '    </tr>';
        $header .= '</table>';

        return $header;
    }

    // Get the status from user signedout mail
    // 0 = unread, 1 = read, 2 = replied
    function get_mail_status($mailid) {
        global $USER;

        $statusobj = new stdClass();

        for (;;) {
            $statusobj->status = get_field('assignment_mailsimulation_signed_out_mail', 'status', 'mailid', $mailid, 'userid', $USER->id);

            if ($statusobj->status === false) {
                $mailid = get_field('assignment_mailsimulation_mail', 'parent', 'id', $mailid);
            } else {
                break;
            }
        }
        $statusobj->mailid = $mailid;

        return $statusobj;
    }

    function get_mail_status_img($status) {
        global $CFG;

        $imgurl = '<img src="' . $CFG->wwwroot . '/mod/assignment/type/mailsimulator/images/';

        switch ($status) {
            case 2:
                $status = $imgurl . 'status-replied.png">';
                break;

            case 1:
                $status = $imgurl . 'status-read.png">';
                break;

            case 0:
            default:
                $status = $imgurl . 'status-unread.png">';
                break;
        }
        return $status;
    }

    function set_mail_status($mailid, $newstatus) {
        global $USER;

        $dataobject = new stdClass();
        $dataobject->id = get_field('assignment_mailsimulation_signed_out_mail', 'id', 'mailid', $mailid, 'userid', $USER->id);
        $dataobject->status = $newstatus;

        update_record('assignment_mailsimulation_signed_out_mail', $dataobject);

        return $newstatus;
    }

    function get_sender_string($mailobject, $long=false) {
        global $USER;

        if ($mailobject->sender == 0 && isset($mailobject->userid) && $mailobject->userid == 0) {
            $teacherid = get_field('assignment', 'var3', 'id', $this->assignment->id);
            $fromobj = get_record_select('user', 'id=' . $teacherid, 'firstname, lastname, email');
        } elseif ($mailobject->sender == 0) {
            $fromobj = get_record_select('user', 'id=' . $USER->id, 'firstname, lastname, email');
        } else {
            $fromobj = get_record('assignment_mailsimulation_contact', 'id', $mailobject->sender);
        }

        if ($fromobj) {
            $from = $fromobj->firstname . ' ' . $fromobj->lastname . ($long ? ' &lt;' . $fromobj->email . '&gt;' : '');
        } else {
            $from = $mailobject->sender;
        }

        return $from;
    }

    function get_recipients_string($mailid) {
        global $USER;

        $to_mail_arr = get_records('assignment_mailsimulation_to', 'mailid', $mailid);
        $teacherid = get_field('assignment', 'var3', 'id', $this->assignment->id);
        $toarr = array();

        foreach ($to_mail_arr as $value) {
            if ($value->contactid == 0) {
                $toarr[] = get_record_select('user', 'id=' . $teacherid, 'firstname, lastname, email');
            } elseif ($value->contactid == TO_STUDENT_ID) {
                $obj = new stdClass();

                if (has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {
                    $obj->firstname = 'STUDENT';
                    $obj->lastname = 'STUDENT';
                    $obj->email = 'STUDENT@STUDENT.COM';
                } else {
                    $obj->firstname = $USER->firstname;
                    $obj->lastname = $USER->lastname;
                    $obj->email = $USER->email;
                }
                $toarr[] = $obj;
            } else {
                $toarr[] = get_record('assignment_mailsimulation_contact', 'id', $value->contactid);
            }
        }

        $firsttoname = '';
        $commacount = 0;
        $toarrcount = count($toarr);

        if ($toarr) {
            foreach ($toarr as $con) {
                $commacount++;

                if ($con)
                    $firsttoname .= $con->firstname . ' ' . $con->lastname . ' &lt;' . $con->email . '&gt;';
                else
                    $firsttoname .= 'MISSING CONTACT';

                if ($commacount < $toarrcount)
                    $firsttoname .= ', ';
            }
        } else {
            $firsttoname = 'UNSPECIFIED';
        }

        return $firsttoname;
    }

    function mail_body($mailobject, $sentview = false) {
        $bodystr = '<div class="mailmessage">';
        $bodystr .= '<strong>' . $this->get_sender_string($mailobject, true) . '</strong><br />';
        $bodystr .= format_text($mailobject->subject, 1) . '<br />';
        $bodystr .= date('j F Y, H.i', $mailobject->timesent) . '<br />';
        $bodystr .= $this->get_recipients_string($mailobject->id) . '<br />';
        $bodystr .= '<hr />';
        $bodystr .= '</div>';

        if ($sentview) {
            $bodystr .= ( $mailobject->attachment ? $this->get_files_str($mailobject->id, $mailobject->userid) : '');
            $bodystr .= $this->get_nested_from_child($mailobject);
        } else {
            $bodystr .= format_text($mailobject->message, FORMAT_MOODLE);
        }

        return $bodystr;
    }

    function get_nested_from_child($mailobj) {
        $message = '<div class="mailmessage">' . format_text($mailobj->message, FORMAT_MOODLE);
        $dept = 1;
        $attachment = '';

        while ($mailobj = get_record('assignment_mailsimulation_mail', 'id', $mailobj->parent)) {

            $from = $this->get_sender_string($mailobj);
            $date = date('j M Y, H.i', $mailobj->timesent);
            $message .= '<br /><br/>' . $date . ' ' . get_string('wrote', 'assignment_mailsimulator', $from) . ':';
            $message .= '<div style="border-left: 2px outset #000000; padding: 5px">' . ($mailobj->attachment ? $this->get_files_str($mailobj->id, $mailobj->userid) : '') . format_text($mailobj->message, FORMAT_MOODLE);
            $dept++;
        }

        for ($i = 0; $i < $dept; $i++) {
            $message .= '</div>';
        }

        return $message;
    }

    function get_nested_reply_object($mailobj, $editbuttons=false) {
        global $CFG;

        $replys = false;

        do {
            $replys[] = $mailobj;
        } while ($mailobj = get_record('assignment_mailsimulation_mail', 'parent', $mailobj->id, 'userid', 0));

        if (!$replys || count($replys) <= 1) {
            return false;
        }

        $replys = array_reverse($replys);
        $replystr = '';
        $divcount = 0;
        $attachment = 0;

        foreach ($replys as $m) {
            if ($divcount == 0) {
                $replystr .= '<div class="mailmessage">';
                $mailobj->id = $m->id;
                $mailobj->subject = $m->subject;
                $mailobj->timesent = $m->timesent;
                $mailobj->priority = $m->priority;
                $mailobj->sender = $m->sender;
            } else {

                $from = $this->get_sender_string($m);
                $replystr .= date('j F Y, H.i', $m->timesent) . ' ' . get_string('wrote', 'assignment_mailsimulator', $from) . ':<br /><div style="border-left: 2px outset #000000; padding: 5px">';
            }

            $replystr .= ( $m->attachment ? $this->get_files_str($m->id, $m->userid) : '') . format_text($m->message);

            if ($editbuttons) {
                $replystr .= '<span style="text-align:right">' . print_single_button($CFG->wwwroot . '/mod/assignment/type/mailsimulator/mail.php', array('id' => $this->cm->id, 'mid' => $m->id), get_string('edit'), 'get', '_self', true) . '</span>';
            }
            if ($m->attachment == 1) {
                $attachment = 1;
            }

            $mailobj->attachment = $attachment;
            $divcount++;
        }
        for ($i = 0; $i < count($replys); $i++) {
            $replystr .= '</div>';
        }

        $mailobj->message = $replystr;
        if (isset($m->pid))
            $mailobj->pid = $m->pid;
        if (isset($m->weight))
            $mailobj->weight = $m->weight;
        if (isset($m->correctiontemplate))
            $mailobj->correctiontemplate = $m->correctiontemplate;
        if (isset($m->randgroup))
            $mailobj->randgroup = $m->randgroup;


        return $mailobj;
    }

    // TEACHER VIEW
    function view_all_mail($trash=false) {
        global $CFG;

        $deletestatus = ($trash ? 1 : 0);
        $sql = 'SELECT p.id AS pid, p.mailid AS id, p.randgroup, p.weight, p.correctiontemplate, p.deleted, m.priority, m.sender, m.userid, m.subject, m.message, m.timesent, m.parent, m.attachment
                FROM ' . $CFG->prefix . 'assignment_mailsimulation_parent_mail AS p
                LEFT JOIN ' . $CFG->prefix . 'assignment_mailsimulation_mail AS m ON m.id = p.mailid
                WHERE m.assignment = ' . $this->assignment->id . '
                AND m.parent = 0
                AND m.userid = 0
                AND p.deleted = ' . $deletestatus . '
                ORDER BY p.randgroup';

        if (!$parentmailarr = get_records_sql($sql)) {
            return;
        }

        $editingteacher = has_capability('moodle/legacy:editingteacher', get_context_instance(CONTEXT_MODULE, $this->cm->id));
        $group = 0;

        foreach ($parentmailarr as $mailobj) {
            $pid = $mailobj->id;
            $groupid = get_field('assignment_mailsimulation_parent_mail', 'randgroup', 'mailid', $mailobj->id);

            if ($groupid == 0) {
                $this->add_parent($mailobj->id, $this->calculate_group());
            }

            if ($mailobj->randgroup != $group) {
                if ($group != 0) {
                    echo '</div><br />';
                }
                echo '<div style="border:1px; border-style:solid; width:90%; margin:auto; background-color:#ffffff">';

                echo '<table border="0" width="100%" style="background:gray; color:white;">';
                echo '  <tr>';
                echo '      <td style="padding:5px"> '.get_string('mail', 'assignment_mailsimulator').' ' . $mailobj->randgroup . ' </td>';
                echo '      <td style="padding:5px; text-align:right">';
                echo '          <table align="right">';
                echo '              <tr>';
                echo '                  <td style="padding:0; margin:0;">';
                if($editingteacher) {
                    print_single_button($CFG->pagepath, array('id' => $this->cm->id, 'add' => 1, 'pid' => 0, 'gid' => $mailobj->randgroup), get_string('addmailalt', 'assignment_mailsimulator'));
                }
                echo '                  </td>';
                echo '                  <td style="padding:0; margin:0;">';
                if($editingteacher) {
                    helpbutton('addalternativemail', get_string('addmailalt', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
                }
                echo '                  </td>';
                echo '              </tr>';
                echo '          </table>';
                echo '      </td>';
                echo '  </tr>';
                echo '</table>';
            }

            $replyobject = $this->get_nested_reply_object($mailobj, $editingteacher);

            if ($replyobject) {
                $mailobj = $replyobject;
            } else {
                $mailobj->message = '<div class="mailmessage">' . ($mailobj->attachment ? $this->get_files_str($mailobj->id, 0) : '') . format_text($mailobj->message, FORMAT_MOODLE) . '</div>';
                if($editingteacher) {
                    $mailobj->message .= '<span style="text-align:right">' . print_single_button($CFG->wwwroot . '/mod/assignment/type/mailsimulator/mail.php', array('id' => $this->cm->id, 'mid' => $mailobj->id), get_string('edit'), 'get', '_self', true) . '</span>';
                }
            }

            $p = $this->get_top_parent_id($mailobj->id);
            $from = $this->get_sender_string($mailobj, true);
            $firsttoname = $this->get_recipients_string($mailobj->id);
            $prio = '<span style="color:darkred">' . $this->get_prio_string($mailobj->priority) . '</span>';

            echo '<table class="allmailheader">';
            echo '  <tr>';
            echo '      <td style="width:100px;">' . get_string('subject', 'assignment_mailsimulator') . ': </td>';
            echo '      <td colspan="5" style="background:white;border:1px;border-style:solid"><strong>' . $prio . format_text($mailobj->subject, 1) . '</strong></td>';
            echo '  </tr>';
            echo '  <tr>';
            echo '      <td>' . get_string('from') . ': </td>';
            echo '      <td colspan="5">' . $from . '</td>';
            echo '  </tr>';
            echo '  <tr>';
            echo '      <td>' . get_string('to') . ': </td>';
            echo '      <td colspan="5">' . $firsttoname . '</td>';
            echo '  </tr>';
            echo '  <tr>';
            echo '      <td>' . get_string('date') . ': </td>';
            echo '      <td colspan="5">' . date('Y-m-d H:i', $mailobj->timesent) . '</td>';
            echo '  </tr>';
            echo '  <tr>';
            echo '      <td>';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                print_single_button($CFG->pagepath, array('id' => $this->cm->id, 'add' => 1, 're' => 1, 'pid' => $mailobj->id), get_string('reply', 'assignment_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                helpbutton('reply', get_string('reply', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            }
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                print_single_button($CFG->pagepath, array('id' => $this->cm->id, 'add' => 1, 're' => 2, 'pid' => $mailobj->id), get_string('replyall', 'assignment_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                helpbutton('reply', get_string('reply', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            }
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                print_single_button($CFG->wwwroot . '/mod/assignment/type/mailsimulator/parent.php', array('id' => $this->cm->id, 'mid' => $p, 'gid' => $mailobj->randgroup), get_string('updatecorrectiontemplate', 'assignment_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
           # helpbutton('updatecorrectiontemplate', get_string('updatecorrectiontemplate', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                print_single_button($CFG->wwwroot . '/mod/assignment/view.php', array('id' => $this->cm->id, 'mid' => $pid, 'delete' => 1), get_string('delete'), 'get', '_self', false, '', $this->get_signed_out_status($pid), get_string('confirmdelete', 'assignment_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                helpbutton('delete', get_string('delete'), 'assignment/type/mailsimulator/');
            }
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                if ($trash) {
                    print_single_button($CFG->wwwroot . '/mod/assignment/view.php', array('id' => $this->cm->id, 'mid' => $pid, 'delete' => 3), get_string('restore'));
                } else {
                    print_single_button($CFG->wwwroot . '/mod/assignment/view.php', array('id' => $this->cm->id, 'mid' => $pid, 'delete' => 2), get_string('trash', 'assignment_mailsimulator'));
                }
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                helpbutton('trashrestore', get_string('trashrestore', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            }
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td rowspan="3">';
            echo '          <table align="right">';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            echo get_string('weight', 'assignment_mailsimulator') . ': ' . $mailobj->weight;
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;" >';
            helpbutton('weight', get_string('weight', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '  </tr>';
            echo '</table>';

            echo '<div>' . $mailobj->message . '</div>';
            echo '<div style="padding: 5px; color:green; background: white">' . format_text($mailobj->correctiontemplate, FORMAT_MOODLE) . '</div>';
            echo '<br />';

            $group = $mailobj->randgroup;
        }
        echo '</div>';
    }

    function get_top_parent_id($mailid) {
        $parentid = $mailid;

        do {
            $mailid = $parentid;
        } while ($parentid = get_field('assignment_mailsimulation_mail', 'parent', 'id', $mailid));

        return $mailid;
    }

    function get_prio_string($prionumb) {

        switch ($prionumb) {
            case 1:
                $prio = '! ';
                break;

            case 2:
                $prio = '!! ';
                break;
            default:
                $prio = ' ';
                break;
        }

        return $prio;
    }

    function check_assignment_setup() {
        global $CFG;

        $addc = optional_param('addc', 0, PARAM_INT);       // Add Contact        
        $mailsetupcount = $this->assignment->var1;          // Count how many mailgroups this assignment requires.

        // Count how many mailgroups this assignment has.
        $sql = 'SELECT count(DISTINCT pm.randgroup)
                FROM ' . $CFG->prefix . 'assignment_mailsimulation_parent_mail AS pm
                LEFT JOIN ' . $CFG->prefix . 'assignment_mailsimulation_mail AS m ON m.id = pm.mailid
                WHERE m.assignment = ' . $this->assignment->id . '
                AND m.userid = 0
                AND pm.deleted = 0
                AND pm.randgroup != 0';

        $mailgroupcount = count_records_sql($sql);

        if ($mailsetupcount > $mailgroupcount) {
            /// ADD NEW MAIL

            if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id)))
                error("assignment needs to be setup correctly, contact your teacher");


            if (!record_exists('assignment_mailsimulation_contact', 'assignment', $this->assignment->id) || $addc) {
                $this->add_contacts();
            }

            $this->add_mail();
        } elseif ($mailsetupcount < $mailgroupcount) {
            set_field('assignment', 'var1', $mailgroupcount, 'id', $this->assignment->id);
        }

        // All Teacher Mail Parents must have a Parent 
        $sql = 'SELECT id
                FROM ' . $CFG->prefix . 'assignment_mailsimulation_mail
                WHERE assignment = ' . $this->assignment->id . '
                AND parent = 0
                AND userid = 0 ';

        $teacherparents = get_records_sql($sql);

        foreach ($teacherparents as $mailid) {
            $id = $mailid->id;
            $parentexists = record_exists('assignment_mailsimulation_parent_mail', 'mailid', $id);

            if (!$parentexists) {
                $this->add_parent($id);
            }
        }
    }

    function add_contacts() {
        global $CFG;

        redirect($CFG->wwwroot . '/mod/assignment/type/mailsimulator/contacts.php?id=' . $this->cm->id . '&a=' . $this->assignment->id, 'ADD Contact', 0);
    }

    function add_mail($pid=0, $gid=0) {
        global $CFG;

        $re = optional_param('re', 0, PARAM_INT);         // Reply 1=one, 2=all
        redirect($CFG->wwwroot . '/mod/assignment/type/mailsimulator/mail.php?id=' . $this->cm->id . '&a=' . $this->assignment->id . '&pid=' . $pid . '&gid=' . $gid . '&re=' . $re, 'ADD MAIL', 0);
    }

    function add_parent($mailid=0, $gid=0) {
        global $CFG;

        if ($mailid) {
            redirect($CFG->wwwroot . '/mod/assignment/type/mailsimulator/parent.php?id=' . $this->cm->id . '&a=' . $this->assignment->id . '&mid=' . $mailid . '&gid=' . $gid, 'ADD PARENT ', 0);
        }
    }

    function calculate_group() {
        global $CFG;

        $sql = 'SELECT DISTINCT pm.randgroup
                FROM ' . $CFG->prefix . 'assignment_mailsimulation_parent_mail AS pm
                LEFT JOIN ' . $CFG->prefix . 'assignment_mailsimulation_mail AS m ON m.id = pm.mailid
                WHERE m.assignment = ' . $this->assignment->id . '
                AND pm.randgroup != 0';

        $grouparr = get_fieldset_sql($sql);
        $group = 1;

        if ($grouparr) {
            sort($grouparr);

            for ($i = 0; $i < count($grouparr); $i++) {
                if ($grouparr[$i] != $group) {
                    break;
                }
                $group++;
            }
        }

        return $group;
    }

    function prepare_mail($parent=0, $from=0, $priority=0) {
        global $USER, $CFG;

        $teacher = has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id));

        $mail = new Object;
        $mail->userid = 0;              // 0 = assignment mail
        $mail->teacher = $teacher;

        if (!$teacher) {
            $mail->userid = $USER->id;  // !0 = student mail
        }
        $mail->mailid = 0;
        $mail->parent = $parent;        // 0 = new mail, 1 = reply
        $mail->assignment = (integer) $this->assignment->id;
        $mail->priority = $priority;
        $mail->sender = $from;
        $mail->subject = '';
        $mail->message = '';
        $mail->timesent = '';

        $contacts = get_records('assignment_mailsimulation_contact', 'assignment', $this->assignment->id);

        if ($contacts) {
            foreach ($contacts as $key => $con) {
                $contacts[$key] = $con->firstname . ' ' . $con->lastname . ' &lt;' . $con->email . '&gt;';
            }
        }

        $teacherid = get_field('assignment', 'var3', 'id', $this->assignment->id);
        $teacherobj = get_record_select('user', 'id=' . $teacherid, 'firstname, lastname, email');
        $studentobj = get_record_select('user', 'id=' . $USER->id, 'firstname, lastname, email');
        $contacts[0] = $teacherobj->firstname . ' ' . $teacherobj->lastname . ' &lt;' . $teacherobj->email . '&gt;';

        if ($teacher)
            $contacts[TO_STUDENT_ID] = get_string('mailtostudent', 'assignment_mailsimulator');
        else
            $contacts[TO_STUDENT_ID] = $studentobj->firstname . ' ' . $studentobj->lastname . ' &lt;' . $studentobj->email . '&gt;';;

        asort($contacts);

        $mail->to = $contacts;

        return $mail;
    }

    function prepare_parent($mailid=0, $group=0) {
        $parentmail = new Object;
        $parentmail->maxweight = get_field('assignment', 'var2', 'id', $this->assignment->id);
        $parentmail->id = 0;

        if ($mailid) {
            $id = get_field('assignment_mailsimulation_parent_mail', 'id', 'mailid', $mailid);
            if ($id) {
                $parentmail->id = $id;
            }
        }
        $parentmail->mailid = $mailid;
        $parentmail->randgroup = $group;

        // If assignment parent
        if (has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id)) && !$group) {
            $parentmail->randgroup = $this->calculate_group();
        }

        $parentmail->weight = 0;
        $parentmail->correctiontemplate = '';
        $parentmail->deleted = 0;

        return $parentmail;
    }

    function upload_attachment($mailid, $mailuserid) {
        $dir = $this->file_area_name($mailuserid) . '/' . $mailid;
        $um = new upload_manager('attachment', false, false, $this->course, false, $this->assignment->maxbytes, true, true, false);
        $um->process_file_uploads($dir);

        return $um->get_new_filename();
    }

    // Creates a new mail and returns the id or false
    function insert_mail($mail, $gid=0) {
        global $CFG, $USER;

        $mailid = insert_record('assignment_mailsimulation_mail', $mail);

        if ($mailid) {
            foreach ($mail->to as $to) {
                $obj = new stdClass();
                $obj->contactid = $to;
                $obj->mailid = $mailid;

                insert_record('assignment_mailsimulation_to', $obj);
            }

            if ($this->upload_attachment($mailid, $mail->userid)) {

                $fileobj = new stdClass();
                $fileobj->id = $mailid;
                $fileobj->attachment = 1;

                update_record('assignment_mailsimulation_mail', $fileobj);
            }

            if ($mail->parent == 0) {
                $this->add_parent($mailid, $gid);
            } else {
                if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {

                    $obj = $this->get_mail_status($mailid);
                    $this->set_mail_status($obj->mailid, 2);
                }
            }

            return $mailid;
        }

        return false;
    }

    function update_mail($mail) {

        delete_records('assignment_mailsimulation_to', 'mailid', $mail->mailid);

        foreach ($mail->to as $to) {
            $obj = new stdClass();
            $obj->contactid = $to;
            $obj->mailid = $mail->mailid;

            insert_record('assignment_mailsimulation_to', $obj);
        }

        $mail->id = $mail->mailid;
        unset($mail->mailid);
        unset($mail->MAX_FILE_SIZE);

        if ($this->upload_attachment($mail->id, $mail->userid)) {
            $mail->attachment = 1;
        }

        update_record('assignment_mailsimulation_mail', $mail);
    }

    function handle_trash($mailid, $delete=true) {
        $status = 0;

        if ($delete) {
            $status = 1;
            $mailcount = get_field('assignment', 'var1', 'id', $this->assignment->id) - 1;
            set_field('assignment', 'var1', $mailcount, 'id', $this->assignment->id);
        }

        $pid = get_field('assignment_mailsimulation_parent_mail', 'id', 'mailid', $mailid);
        set_field('assignment_mailsimulation_parent_mail', 'deleted', $status, 'id', $pid);
    }

    function delete_contact($contactid) {
        global $CFG;

        // Check if contact is in use.
        if (!$active = record_exists('assignment_mailsimulation_to', 'contactid', $contactid)) {
            $active = record_exists('assignment_mailsimulation_mail', 'userid', 0, 'sender', $contactid);
        }

        if ($active) {
            $contact = get_record('assignment_mailsimulation_contact', 'id', $contactid);
            $msg = $contact->firstname . ' ' . $contact->lastname . ' &lt;' . $contact->email . '&gt<br />';
            $msg .= get_string('contactinuse', 'assignment_mailsimulator');
            error($msg, $CFG->wwwroot . '/mod/assignment/type/mailsimulator/contacts.php?id=' . $this->cm->id . '&a=' . $this->assignment->id);
        } else {
            delete_records('assignment_mailsimulation_contact', 'id', $contactid);
        }
    }

    function delete_mail_and_children($mailid) {

        $this->delete_mail($mailid);

        $cid = get_field('assignment_mailsimulation_mail', 'id', 'parent', $mailid);

        if ($cid) {
            $this->delete_mail_and_children($cid);
        } else {
            $mailcount = get_field('assignment', 'var1', 'id', $this->assignment->id) - 1;
            set_field('assignment', 'var1', $mailcount, 'id', $this->assignment->id);
        }
    }

    function delete_mail($mailid) {
        global $CFG;

        $userid = 0;
        $dir = $this->file_area_name($userid) . '/' . $mailid;
        $filepath = $CFG->dataroot . '/' . $dir;

        // Remove attachments
        $this->delete_dir_recursively($filepath);

        delete_records('assignment_mailsimulation_to', 'mailid', $mailid);
        delete_records('assignment_mailsimulation_parent_mail', 'mailid', $mailid);
        delete_records('assignment_mailsimulation_mail', 'id', $mailid);
    }

    function delete_dir_recursively($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);

            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . '/' . $object) == 'dir') {
                        $this->delete_dir_recursively($dir . '/' . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    function get_signed_out_status($mailid) {
        return record_exists('assignment_mailsimulation_signed_out_mail', 'mailid', $mailid);
    }

    function add_instance($assignment) {
        if (isset($assignment->filetypes)) {
            $filetypes = $assignment->filetypes;
            unset($assignment->filetypes);
        } else {
            $filetypes = false;
        }

        $aid = parent::add_instance($assignment);

        if ($aid && $filetypes) {
            foreach ($filetypes as $filetype) {
                $assignment_extra = new Object();
                $assignment_extra->assignment = $aid;
                $assignment_extra->filetype = $filetype;
                insert_record('assignment_mailsimulator_filetypes', $assignment_extra);
            }
        }

        return $aid;
    }

    function update_instance($assignment) {
        $filetypes = null;

        if (isset($assignment->filetypes)) {
            $filetypes = $assignment->filetypes;
            unset($assignment->filetypes);
        }

        $updatesucceeded = parent::update_instance($assignment);

        if ($filetypes && $updatesucceeded) {
            delete_records('assignment_mailsimulator_filetypes', 'assignment', $assignment->instance);

            if (!$assignment->var4) {
                foreach ($filetypes as $filetype) {
                    $assignment_extra = new Object();
                    $assignment_extra->assignment = $assignment->instance;
                    $assignment_extra->filetype = $filetype;
                    insert_record('assignment_mailsimulator_filetypes', $assignment_extra);
                }
            }
        }

        return $updatesucceeded;
    }

    function delete_instance($assignment) {
        global $CFG;

        $result = true;
        $mailids = get_fieldset_sql('SELECT id FROM ' . $CFG->prefix . 'assignment_mailsimulation_mail WHERE assignment=' . $assignment->id);

        if ($mailids) {
            foreach ($mailids as $mailid) {
                if (!delete_records('assignment_mailsimulation_to', 'mailid', $mailid)) {
                    $result = false;
                }
                if (!delete_records('assignment_mailsimulation_signed_out_mail', 'mailid', $mailid)) {
                    $result = false;
                }
                if (!delete_records('assignment_mailsimulation_parent_mail', 'mailid', $mailid)) {
                    $result = false;
                }
            }
        }

        if (!delete_records('assignment_mailsimulator_filetypes', 'assignment', $assignment->id)) {
            $result = false;
        }
        if (!delete_records('assignment_mailsimulation_contact', 'assignment', $assignment->id)) {
            $result = false;
        }
        if (!delete_records('assignment_mailsimulation_mail', 'assignment', $assignment->id)) {
            $result = false;
        }

        $retval = parent::delete_instance($assignment);

        return $retval && $result;
    }

    // Assignment setup
    // var1 Number of mail in the assignment
    // var2 Max weight for each mail
    // var3 Default teacher email
    // var4 Enable attachments
    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $update = optional_param('update', 0, PARAM_INT);
        $add = optional_param('add', 0, PARAM_ALPHA);
        $selectedtypes = null;

        if (!empty($update)) {
            if (!$cm = get_record("course_modules", "id", $update)) {
                error("This course module doesn't exist");
            }

            $courseid = $cm->course;
            $assignmentid = $cm->instance;

            if ($filetypes = get_records('assignment_mailsimulator_filetypes', 'assignment', $cm->instance)) {
                foreach ($filetypes as $key => $obj) {
                    $selectedtypes[] = $obj->filetype;
                }
            }

        } elseif (!empty($add)) {
            $courseid = required_param('course', PARAM_INT);
            $assignmentid = null;
        }

        require_once("$CFG->dirroot/lib/filelib.php");

        // Get the list of file extensions and mime types
        $extensions = array();
        require_once("$CFG->dirroot/lib/filelib.php"); // for file types
        $mime = get_mimetypes_array();

        foreach ($mime as $extension => $typeandicon) {
            if ($extension != 'xxx') {
                $extensions[$extension] = $extension . ' (' . $typeandicon['type'] . ')';
            }
        }
        ksort($extensions, SORT_STRING);

        // Create an array with same index as values
        function numb_span($i=1, $j=1) {
            $arr = array();

            for ($i = 1; $i <= $j; $i++) {
                $arr[$i] = $i;
            }
            return $arr;
        }

        // Define how many mail the assignment will have
        $mform->addElement('select', 'var1', get_string('numberofmail', 'assignment_mailsimulator'), numb_span(1, 100));
        $mform->setHelpButton('var1', array('numberofmail', get_string('numberofmail', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/'));
        // Define the max weight for each mail
        $mform->addElement('select', 'var2', get_string('maxweightpermail', 'assignment_mailsimulator'), numb_span(1, 100));
        $mform->setHelpButton('var2', array('maxweightpermail', get_string('maxweightpermail', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/'));

        $sql = 'SELECT distinct u.id AS uid, u.firstname, u.lastname, u.email ' .
                'FROM ' . $CFG->prefix . 'course as c, '
                . $CFG->prefix . 'role_assignments AS ra, '
                . $CFG->prefix . 'user AS u, '
                . $CFG->prefix . 'context AS ct ' .
                'WHERE c.id = ct.instanceid ' .
                'AND ra.roleid =3 ' .
                'AND ra.userid = u.id ' .
                #       'AND ct.id = ra.contextid '.
                'AND c.id = ' . $COURSE->id;

        $records = get_records_sql($sql);

        if (!$records)
            error('This course does not have any teachers.');

        foreach ($records as $teacher) {
            $teachers[$teacher->uid] = $teacher->firstname . ' ' . $teacher->lastname . ' &lt;' . $teacher->email . '&gt;';
        }

        // Define the default teacher
        $mform->addElement('select', 'var3', get_string('teachermail', 'assignment_mailsimulator'), $teachers);
        $mform->setHelpButton('var3', array('teachermail', get_string('teachermail', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/'));
        $mform->addElement('selectyesno', 'var4', get_string('disableattachments', 'assignment_mailsimulator'));

        $filesizechoices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $filesizechoices[0] = get_string('courseuploadlimit') . ' (' . display_size($COURSE->maxbytes) . ')';
        $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'assignment_mailsimulator'), $filesizechoices);
        $mform->setDefault('maxbytes', $CFG->assignment_maxbytes);
        $mform->setHelpButton('maxbytes', array('maxbytes', get_string('maxbytes', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/'));
        $mform->disabledIf('maxbytes', 'var4', 'eq', '1');

        if ($selectedtypes) {
            $select = $mform->addElement('select', 'filetypes', get_string('filetypes', 'assignment_mailsimulator'), array(), array('size' => '20'));

            foreach ($extensions as $key => $value) {
                if (in_array($key, $selectedtypes)) {
                    $select->addOption($value, $key, array('selected' => 'selected'));
                } else {
                    $select->addOption($value, $key);
                }
            }
            $select->setMultiple(true);
        } else {
            $mform->addElement('select', 'filetypes', get_string('filetypes', 'assignment_mailsimulator'), $extensions, array('size' => '20'))->setMultiple(true);
        }

        $mform->disabledIf('filetypes', 'var4', 'eq', '1');
    }

    // Tabs for the header
    function print_tabs($current='mail') {
        global $CFG;

        $route = optional_param('route', 0, PARAM_INT);
        $tabs = array();
        $row = array();
        $row[] = new tabobject('mail', $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id, get_string('mailbox', 'assignment_mailsimulator'));

        if (has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {
            $countready = count_records('assignment_submissions', 'assignment', $this->assignment->id, 'data1', '2', 'grade', '-1');
            $sql = 'SELECT COUNT(*) FROM ' . $CFG->prefix . 'assignment_mailsimulation_parent_mail WHERE deleted = 1 AND randgroup != 0 AND mailid IN (SELECT id FROM ' . $CFG->prefix . 'assignment_mailsimulation_mail WHERE assignment = ' . $this->assignment->id . ')';
            $counttrashmail = get_field_sql($sql);

            $row[] = new tabobject('trashmail', $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&route=3', get_string('trash', 'assignment_mailsimulator') . ' (' . $counttrashmail . ')');
            if(has_capability('moodle/legacy:editingteacher', get_context_instance(CONTEXT_MODULE, $this->cm->id))) { 
                $row[] = new tabobject('addmail', $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&add=1', get_string('addmail', 'assignment_mailsimulator'));
                $row[] = new tabobject('addcontacts', $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&addc=1', get_string('addcontacts', 'assignment_mailsimulator'));
            }
            $row[] = new tabobject('readysubmissions', $CFG->wwwroot . '/mod/assignment/submissions.php?id=' . $this->cm->id . '&ready=1', get_string('readyforgrading', 'assignment_mailsimulator') . ' (' . $countready . ')');
            $row[] = new tabobject('submissions', $CFG->wwwroot . '/mod/assignment/submissions.php?id=' . $this->cm->id . '&ready=0', get_string('allsubmissions', 'assignment_mailsimulator') . ' (' . $this->count_real_submissions(0) . ')');
        }

        $row[] = new tabobject('description', $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&route=10', get_string('description'));
        $tabs[] = $row;

        print_tabs($tabs, $current);
    }

    // Page header
    function view_header($subpage='') {
        global $CFG;

        if ($subpage) {
            $navigation = build_navigation($subpage, $this->cm);
        } else {
            $navigation = build_navigation('', $this->cm);
        }

        print_header($this->pagetitle, $this->course->fullname, $navigation, '', '',
                true, update_module_button($this->cm->id, $this->course->id, $this->strassignment),
                navmenu($this->course, $this->cm));

        $this->print_tabs($subpage != '' ? $subpage : 'mail');
    }

    // Print assignment introduction. Overide to add content
    function view_intro() {
        $teacher = has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id));

        if (!$teacher) {
            $sid = optional_param('readyforgrading', 0);    // Submission id

            if ($sid) {
                $dataobject = new stdClass();
                $dataobject->id = $_POST['readyforgrading'];
                $dataobject->data1 = 2;
                $dataobject->timemodified = time();
                update_record('assignment_submissions', $dataobject);
            }

            $submission = $this->get_submission();

            $dissabled = false;
            if ($submission->data1 == 2) {
                $dissabled = true;
            }
        }

        print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        echo format_text($this->assignment->description, $this->assignment->format, $formatoptions);
        print_simple_box_end();

        if (!$teacher) {
            echo "<div style='text-align:center'>";
            print_single_button('view.php?id=' . $this->cm->id . '&route=10', array('readyforgrading' => $submission->id),
                    get_string('imreadyforgrading', 'assignment_mailsimulator'), 'post', 'self', false, '', $dissabled);
            echo "</div>";
        }
    }

    function new_submission() {
        global $CFG, $USER;

        $newsubmission = $this->prepare_new_submission($USER->id);
        $newsubmission->data1 = '1';
        $newsubmission->timecreated = time();
        $newsubmission->timemodified = time();

        $submission = insert_record('assignment_submissions', $newsubmission);

        if ($submission) {
            return true;
        }

        return false;
    }

    // For the submission table
    function print_student_answer($userid, $return=false) {
        global $CFG;
        if (!$submission = $this->get_submission($userid)) {
            return '';
        }

        switch ($submission->data1) {
            case 3:
                $status = get_string('needcompletion', 'assignment_mailsimulator');
                break;
            case 2:
                $status = get_string('readyforgrading', 'assignment_mailsimulator');
                break;

            case 1:
            default:
                $status = get_string('inprogress', 'assignment_mailsimulator');
                break;
        }

        $output = '<div class="files">' .
                '<img src="' . $CFG->pixpath . '/f/html.gif" class="icon" alt="html" />' .
                link_to_popup_window('/mod/assignment/type/mailsimulator/file.php?id=' . $this->cm->id . '&amp;userid=' .
                        $submission->userid, 'file' . $userid, $status, 450, 580,
                        get_string('submission', 'assignment'), 'none', true) .
                '</div>';

        return $output;
    }

    function display_submissions($message='') {
        global $CFG, $db, $USER, $SESSION;
        require_once($CFG->libdir . '/gradelib.php');


        if (!isset($SESSION->allsubmissions)) {
            $SESSION->allsubmissions = optional_param('ready');
        }

        $readysubmissions = optional_param('ready', $SESSION->allsubmissions);

        if ($readysubmissions)
            $SESSION->allsubmissions = true;
        else
            $SESSION->allsubmissions = false;


        /* first we check to see if the form has just been submitted
         * to request user_preference updates
         */

        if (isset($_POST['updatepref'])) {
            $perpage = optional_param('perpage', 10, PARAM_INT);
            $perpage = ($perpage <= 0) ? 10 : $perpage;
            set_user_preference('assignment_perpage', $perpage);
            set_user_preference('assignment_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
        }

        /* next we get perpage and quickgrade (allow quick grade) params
         * from database
         */
        $perpage = get_user_preferences('assignment_perpage', 10);

        $quickgrade = get_user_preferences('assignment_quickgrade', 0);

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id);

        if (!empty($CFG->enableoutcomes) and !empty($grading_info->outcomes)) {
            $uses_outcomes = true;
        } else {
            $uses_outcomes = false;
        }

        $page = optional_param('page', 0, PARAM_INT);
        $strsaveallfeedback = get_string('saveallfeedback', 'assignment');

        /// Some shortcuts to make the code read better

        $course = $this->course;
        $assignment = $this->assignment;
        $cm = $this->cm;

        $tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet
        add_to_log($course->id, 'assignment', 'view submission', 'submissions.php?id=' . $this->cm->id, $this->assignment->id, $this->cm->id);
        $navigation = build_navigation($this->strsubmissions, $this->cm);
        print_header_simple(format_string($this->assignment->name, true), "", $navigation,
                '', '', true, update_module_button($cm->id, $course->id, $this->strassignment), navmenu($course, $cm));

        /// Added this to get the tabs working
        if (!$SESSION->allsubmissions) {
            $this->print_tabs('submissions');
        } else {
            $this->print_tabs('readysubmissions');
        }

        $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
            . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }

        if (!empty($message)) {
            echo $message;   // display messages here if any
        }

        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        /// Check to see if groups are being used in this assignment
        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/assignment/submissions.php?id=' . $this->cm->id);
        if (!empty($CFG->gradebookroles)) {
            $gradebookroles = explode(",", $CFG->gradebookroles);
        } else {
            $gradebookroles = '';
        }
        $users = get_role_users($gradebookroles, $context, true, '', 'u.lastname ASC', true, $currentgroup);

        if ($users) {
            $users = array_keys($users);
            if (!empty($CFG->enablegroupings) and $cm->groupmembersonly) {
                $groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id');
                if ($groupingusers) {
                    $users = array_intersect($users, array_keys($groupingusers));
                }
            }
        }

        $tablecolumns = array('picture', 'fullname', 'grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade');
       
        if ($uses_outcomes) {
            $tablecolumns[] = 'outcome'; // no sorting based on outcomes column
        }

        $tableheaders = array('',
            get_string('fullname'),
            get_string('grade') . ' (' . get_string('weight', 'assignment_mailsimulator') . ')',
            get_string('comment', 'assignment'),
            get_string('lastmodified') . ' (' . $course->student . ')',
            get_string('lastmodified') . ' (' . $course->teacher . ')',
            get_string('status'),
            get_string('finalgrade', 'grades'));
        if ($uses_outcomes) {
            $tableheaders[] = get_string('outcome', 'grades');
        }

        require_once($CFG->libdir . '/tablelib.php');
        $table = new flexible_table('mod-assignment-submissions');

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot . '/mod/assignment/submissions.php?id=' . $this->cm->id . '&amp;currentgroup=' . $currentgroup);

        $table->sortable(true, 'lastname'); //sorted by lastname by default
        $table->collapsible(true);

        $table->column_suppress('picture');
        $table->column_suppress('fullname');

        $table->column_class('picture', 'picture');
        $table->column_class('fullname', 'fullname');
        $table->column_class('grade', 'grade');
        $table->column_class('submissioncomment', 'comment');
        $table->column_class('timemodified', 'timemodified');
        $table->column_class('timemarked', 'timemarked');
        $table->column_class('status', 'status');
        $table->column_class('finalgrade', 'finalgrade');
        if ($uses_outcomes) {
            $table->column_class('outcome', 'outcome');
        }

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');
        //$table->set_attribute('align', 'center');

        $table->no_sorting('finalgrade');
        $table->no_sorting('outcome');

        // Start working -- this is necessary as soon as the niceties are over
        $table->setup();

        if (empty($users)) {
            print_heading(get_string('nosubmitusers', 'assignment'));
            return true;
        }

        /// Construct the SQL

        if ($where = $table->get_sql_where()) {
            $where .= ' AND ';
        }

        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY ' . $sort;
        }

        $select = 'SELECT u.id, u.firstname, u.lastname, u.picture, u.imagealt,
                          s.id AS submissionid, s.grade, s.submissioncomment,
                          s.timemodified, s.timemarked, s.data1,
                          COALESCE(SIGN(SIGN(s.timemarked) + SIGN(s.timemarked - s.timemodified)), 0) AS status ';


        if (!$SESSION->allsubmissions) {
            $sql = 'FROM ' . $CFG->prefix . 'user u ' .
                    'LEFT JOIN ' . $CFG->prefix . 'assignment_submissions s ON u.id = s.userid ' .
                    'AND s.assignment = ' . $this->assignment->id . ' ' .
                    'WHERE ' . $where . 'u.id IN (' . implode(',', $users) . ') ';
        } else {
            $sql = 'FROM ' . $CFG->prefix . 'user u ' .
                    'LEFT JOIN ' . $CFG->prefix . 'assignment_submissions s ON u.id = s.userid ' .
                    'AND s.assignment = ' . $this->assignment->id . ' ' .
                    'WHERE ' . $where . 'u.id IN (' . implode(',', $users) . ') ' .
                    'AND s.data1 = 2 ' .
                    'AND s.grade = -1 ';
        }

        $table->pagesize($perpage, count($users));

        ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
        $offset = $page * $perpage;

        $strupdate = get_string('update');
        $strgrade = get_string('grade');
        $grademenu = make_grades_menu($this->assignment->grade);

        if (($ausers = get_records_sql($select . $sql . $sort, $table->get_page_start(), $table->get_page_size())) !== false) {
            $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array_keys($ausers));
            foreach ($ausers as $auser) {
                ######################################                
                $allmail = $this->get_user_parents($auser->id, true);

                if (!$allmail) {
                    $weightstr = ' (-)';
                } else {

                    $maxweight = 0;
                    $userweight = 0;

                    foreach ($allmail as $mail) {
                        $maxweight += $mail->weight;
                        $userweight += $mail->gainedweight * $mail->weight;
                    }

                    if ($userweight < 0) {
                        $weightstr = ' (-/' . $maxweight * 2 . ')';
                    } else {
                        $weightstr = ' (' . $userweight . '/' . $maxweight * 2 . ')';
                    }
                }

                ######################################
                $final_grade = $grading_info->items[0]->grades[$auser->id];
                $grademax = $grading_info->items[0]->grademax;
                $final_grade->formatted_grade = round($final_grade->grade, 2) . ' / ' . round($grademax, 2);
                $locked_overridden = 'locked';
                if ($final_grade->overridden) {
                    $locked_overridden = 'overridden';
                }

                /// Calculate user status
                $auser->status = ($auser->timemarked > 0) && ($auser->timemarked >= $auser->timemodified);
                $picture = print_user_picture($auser, $course->id, $auser->picture, false, true);

                if (empty($auser->submissionid)) {
                    $auser->grade = -1; //no submission yet
                }

                if (!empty($auser->submissionid)) {
                    ///Prints student answer and student modified date
                    ///attach file or print link to student answer, depending on the type of the assignment.
                    ///Refer to print_student_answer in inherited classes.
                    if ($auser->timemodified > 0) {
                        $studentmodified = '<div id="ts' . $auser->id . '">' . $this->print_student_answer($auser->id) ########################################
                                . userdate($auser->timemodified) . '</div>';
                    } else {
                        $studentmodified = '<div id="ts' . $auser->id . '">&nbsp;</div>';
                    }
                    ///Print grade, dropdown or text
                    if ($auser->timemarked > 0) {
                        $teachermodified = '<div id="tt' . $auser->id . '">' . userdate($auser->timemarked) . '</div>';

                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g' . $auser->id . '" class="' . $locked_overridden . '">' . $final_grade->formatted_grade . $weightstr . '</div>';
                        } else if ($quickgrade) {
                            $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                            'menu[' . $auser->id . ']', $auser->grade,
                                            get_string('nograde'), '', -1, true, false, $tabindex++);
                            $grade = '<div id="g' . $auser->id . '">' . $menu . $weightstr . '</div>';
                        } else {
                            $grade = '<div id="g' . $auser->id . '">' . $this->display_grade($auser->grade) . $weightstr . '</div>';
                        }
                    } else {
                        $teachermodified = '<div id="tt' . $auser->id . '">&nbsp;</div>';
                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g' . $auser->id . '" class="' . $locked_overridden . '">' . $final_grade->formatted_grade . $weightstr . '</div>';
                        } else if ($quickgrade) {
                            $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                            'menu[' . $auser->id . ']', $auser->grade,
                                            get_string('nograde'), '', -1, true, false, $tabindex++);
                            $grade = '<div id="g' . $auser->id . '">' . $menu . $weightstr . '</div>';
                        } else {
                            $grade = '<div id="g' . $auser->id . '">' . $this->display_grade($auser->grade) . $weightstr . '</div>';
                        }
                    }
                    ///Print Comment
                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com' . $auser->id . '">' . shorten_text(strip_tags($final_grade->str_feedback), 15) . '</div>';
                    } else if ($quickgrade) {
                        $comment = '<div id="com' . $auser->id . '">'
                                . '<textarea tabindex="' . $tabindex++ . '" name="submissioncomment[' . $auser->id . ']" id="submissioncomment'
                                . $auser->id . '" rows="2" cols="20">' . ($auser->submissioncomment) . '</textarea></div>';
                    } else {
                        $comment = '<div id="com' . $auser->id . '">' . shorten_text(strip_tags($auser->submissioncomment), 15) . '</div>';
                    }
                } else {
                    $studentmodified = '<div id="ts' . $auser->id . '">&nbsp;</div>';
                    $teachermodified = '<div id="tt' . $auser->id . '">&nbsp;</div>';
                    $status = '<div id="st' . $auser->id . '">&nbsp;</div>';

                    if ($final_grade->locked or $final_grade->overridden) {
                        $grade = '<div id="g' . $auser->id . '">' . $final_grade->formatted_grade . $weightstr . '</div>';
                    } else if ($quickgrade) {   // allow editing
                        $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                        'menu[' . $auser->id . ']', $auser->grade,
                                        get_string('nograde'), '', -1, true, false, $tabindex++);
                        $grade = '<div id="g' . $auser->id . '">' . $menu . $weightstr . '</div>';
                    } else {
                        $grade = '<div id="g' . $auser->id . '">-  ' . $weightstr . '</div>';
                    }

                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com' . $auser->id . '">' . $final_grade->str_feedback . '</div>';
                    } else if ($quickgrade) {
                        $comment = '<div id="com' . $auser->id . '">'
                                . '<textarea tabindex="' . $tabindex++ . '" name="submissioncomment[' . $auser->id . ']" id="submissioncomment'
                                . $auser->id . '" rows="2" cols="20">' . ($auser->submissioncomment) . '</textarea></div>';
                    } else {
                        $comment = '<div id="com' . $auser->id . '">&nbsp;</div>';
                    }
                }

                if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
                    $auser->status = 0;
                } else {
                    $auser->status = 1;
                }

                $buttontext = ($auser->status == 1) ? $strupdate : $strgrade;

                ///No more buttons, we use popups ;-).
                $popup_url = '/mod/assignment/submissions.php?id=' . $this->cm->id
                        . '&amp;userid=' . $auser->id . '&amp;mode=single' . '&amp;offset=' . $offset++;
                $button = link_to_popup_window($popup_url, 'grade' . $auser->id, $buttontext, 600, 780,
                                $buttontext, 'none', true, 'button' . $auser->id);

                $status = '<div id="up' . $auser->id . '" class="s' . $auser->status . '">' . $button . '</div>';

                $finalgrade = '<span id="finalgrade_' . $auser->id . '">' . $final_grade->str_grade . '</span>';

                $outcomes = '';

                if ($uses_outcomes) {

                    foreach ($grading_info->outcomes as $n => $outcome) {
                        $outcomes .= '<div class="outcome"><label>' . $outcome->name . '</label>';
                        $options = make_grades_menu(-$outcome->scaleid);

                        if ($outcome->grades[$auser->id]->locked or !$quickgrade) {
                            $options[0] = get_string('nooutcome', 'grades');
                            $outcomes .= ': <span id="outcome_' . $n . '_' . $auser->id . '">' . $options[$outcome->grades[$auser->id]->grade] . '</span>';
                        } else {
                            $outcomes .= ' ';
                            $outcomes .= choose_from_menu($options, 'outcome_' . $n . '[' . $auser->id . ']',
                                            $outcome->grades[$auser->id]->grade, get_string('nooutcome', 'grades'), '', 0, true, false, 0, 'outcome_' . $n . '_' . $auser->id);
                        }
                        $outcomes .= '</div>';
                    }
                }

                $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course=' . $course->id . '">' . fullname($auser, has_capability('moodle/site:viewfullnames', $this->context)) . '</a>';
                $row = array($picture, $userlink, $grade, $comment, $studentmodified, $teachermodified, $status, $finalgrade);

                if ($uses_outcomes) {
                    $row[] = $outcomes;
                }

                $table->add_data($row);
            }
        }

        /// Print quickgrade form around the table
        if ($quickgrade) {
            echo '<form action="submissions.php" id="fastg" method="post">';
            echo '<div>';
            echo '<input type="hidden" name="id" value="' . $this->cm->id . '" />';
            echo '<input type="hidden" name="mode" value="fastgrade" />';
            echo '<input type="hidden" name="page" value="' . $page . '" />';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
            echo '</div>';
        }

        $table->print_html();  /// Print the whole table

        if ($quickgrade) {
            $lastmailinfo = get_user_preferences('assignment_mailinfo', 1) ? 'checked="checked"' : '';
            echo '<div class="fgcontrols">';
            echo '<div class="emailnotification">';
            echo '<label for="mailinfo">' . get_string('enableemailnotification', 'assignment') . '</label>';
            echo '<input type="hidden" name="mailinfo" value="0" />';
            echo '<input type="checkbox" id="mailinfo" name="mailinfo" value="1" ' . $lastmailinfo . ' />';
            helpbutton('emailnotification', get_string('enableemailnotification', 'assignment'), 'assignment') . '</p></div>';
            echo '</div>';
            echo '<div class="fastgbutton"><input type="submit" name="fastg" value="' . get_string('saveallfeedback', 'assignment') . '" /></div>';
            echo '</div>';
            echo '</form>';
        }
        /// End of fast grading form
        /// Mini form for setting user preference
        echo '<div class="qgprefs">';
        echo '<form id="options" action="submissions.php?id=' . $this->cm->id . '" method="post"><div>';
        echo '<input type="hidden" name="updatepref" value="1" />';
        echo '<table id="optiontable">';
        echo '<tr><td>';
        echo '<label for="perpage">' . get_string('pagesize', 'assignment') . '</label>';
        echo '</td>';
        echo '<td>';
        echo '<input type="text" id="perpage" name="perpage" size="1" value="' . $perpage . '" />';
        helpbutton('pagesize', get_string('pagesize', 'assignment'), 'assignment');
        echo '</td></tr>';
        echo '<tr><td>';
        echo '<label for="quickgrade">' . get_string('quickgrade', 'assignment') . '</label>';
        echo '</td>';
        echo '<td>';
        $checked = $quickgrade ? 'checked="checked"' : '';
        echo '<input type="checkbox" id="quickgrade" name="quickgrade" value="1" ' . $checked . ' />';
        helpbutton('quickgrade', get_string('quickgrade', 'assignment'), 'assignment') . '</p></div>';
        echo '</td></tr>';
        echo '<tr><td colspan="2">';
        echo '<input type="submit" value="' . get_string('savepreferences') . '" />';
        echo '</td></tr></table>';
        echo '</div></form></div>';
        ///End of mini form
        print_footer($this->course);
    }

    function view_grading_feedback($userid=null) {
        global $CFG;

        if (!$user = get_record("user", "id", $userid)) {
            error("User is misconfigured");
        }

        $teacher = has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id));

        if ($submission = $this->get_submission($userid)) {

            if (!$teacher && $submission->data1 == 1) {
                return;
            }

            if ($teacher) {
                // Submit comment and weight
                if (isset($_POST['submit'])) {
                    $submission->data1 = $_POST['completion'];
                    unset($_POST['submit']);
                    unset($_POST['completion']);

                    foreach ($_POST as $key => $value) {
                        $obj = new stdClass();
                        $karr = explode('_', $key);

                        if ($karr[0] == 'gainedweight') {
                            $obj->id = $karr[1];
                            $obj->$karr[0] = $value;
                            $sarr[$karr[1]] = $obj;
                        }

                        $sarr[$karr[1]]->$karr[0] = $value;
                    }

                    foreach ($sarr as $dataobject) {
                        update_record('assignment_mailsimulation_signed_out_mail', $dataobject);
                    }
                    $submission->timemarked = time();
                    $submission->teacher = $user->id;

                    update_record('assignment_submissions', $submission);

                    echo '<script language="javascript" type="text/javascript">';
                    echo '  window.opener.location.reload(true);window.close();';
                    echo '</script>';
                }
            }

            // The mail which the student has signed out
            $signedoutarr = $this->get_user_parents($userid, true);
            // The new mail the student has sent
            $newmailarr = get_records_select('assignment_mailsimulation_mail', 'parent = 0 AND userid = ' . $userid . ' AND assignment = ' . $this->assignment->id);
            // Get replies the student has made on hes own mail
            $newmailarr = $this->get_recursive_replies($newmailarr, $tmparr, $userid);

            $maxweight = 0;
            $totalgained = 0;

            if ($signedoutarr) {
                foreach ($signedoutarr as $mailobj) {
                    $mailobj->id = $mailobj->mailid;
                    $mailobj->message = '<div class="mailmessage">' . format_text($mailobj->message) . ($mailobj->attachment ? $this->get_files_str($mailobj->id, $mailobj->userid) : '') . '</div>';
                    unset($mailobj->mailid);
                    #       $nestedarr[] = $assignmentinstance->get_nested_reply_object($mailobj);
                    $nestedobj = $this->get_nested_reply_object($mailobj);

                    if ($nestedobj) {
                        $mailobj->id = $nestedobj->id;
                        $mailobj->subject = $nestedobj->subject;
                        $mailobj->timesent = $nestedobj->timesent;
                        $mailobj->sender = $nestedobj->sender;
                        $mailobj->message = $nestedobj->message;
                    }

                    $select = 'parent = ' . $mailobj->id . ' AND userid = ' . $userid . ' AND assignment = ' . $this->assignment->id;
                    $mailobj->studentreplys = get_records_select('assignment_mailsimulation_mail', $select);

                    if ($mailobj->studentreplys) {
                        $tmp = array();
                        $mailobj->studentreplys = $this->get_recursive_replies($mailobj->studentreplys, $tmp, $userid);
                    }

                    $maxweight += $mailobj->weight;
                    $totalgained += ( $mailobj->gainedweight * $mailobj->weight);
                }
            }

            $show = get_string('show') . ' ' . get_string('teachermail', 'assignment_mailsimulator');
            $hide = get_string('hide') . ' ' . get_string('teachermail', 'assignment_mailsimulator');

            if(!$teacher) {
                print_heading(get_string('extendedfeedback', 'assignment_mailsimulator'));
            }
            echo '<script language="javascript">';
            echo 'function toggle(showHideDiv, switchText) {';
            echo '	var ele = document.getElementById(showHideDiv);';
            echo '	var text = document.getElementById(switchText);';
            echo '	if(ele.style.display == "block") {';
            echo '		ele.style.display = "none";';
            echo '		text.innerHTML = "' . $show . '";';
            echo '	}';
            echo '	else {';
            echo '		ele.style.display = "block";';
            echo '		text.innerHTML = "' . $hide . '";';
            echo '	}';
            echo '}';
            echo '</script>';


            if ($teacher) {
                print_simple_box_start('center', '', '', '', 'generalbox', 'dates');
                echo '<table>';
                if ($this->assignment->timedue) {
                    echo '  <tr>';
                    echo '      <td class="c0">' . get_string('duedate', 'assignment') . ':</td>';
                    echo '      <td class="c1">' . userdate($this->assignment->timedue) . '</td>';
                    echo '  </tr>';
                }

                echo '  <tr>';
                echo '      <td class="c0">' . get_string('lastmodified') . ' (' . $this->course->student . '):</td>';
                echo '      <td class="c1">' . userdate($submission->timemodified) . '</td>';
                echo '  </tr>';
                echo '  <tr>';
                echo '      <td class="c0" style="padding-right: 15px;">' . get_string('lastmodified') . ' (' . $this->course->teacher . '):</td>';
                echo '      <td class="c1">' . userdate($submission->timemarked) . '</td>';
                echo '  </tr>';
                echo '  <tr>';
                echo '      <td class="c0">' . get_string('weight_maxweight', 'assignment_mailsimulator') . ':';
                helpbutton('weight', get_string('weight', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
                echo '      </td>';
                echo '      <td class="c1">' . $totalgained . ' / ' . $maxweight * 2 . '</td>';
                echo '  </tr>';
                echo '</table>';
                print_simple_box_end();
            }

            print_simple_box_start('center');

            if ($teacher) {
                echo '<form name="input" action="' . $CFG->wwwroot . '/mod/assignment/type/mailsimulator/file.php?id=' . $this->cm->id . '&userid=' . $userid . '" method="post">';
            }
            if ($signedoutarr) {
                print_heading(get_string('studentreplys', 'assignment_mailsimulator'));

                $toggleid = 0;

                foreach ($signedoutarr as $signedoutid => $mobj) {

                    $toggleid++;
                    $multiplier = $mobj->gainedweight;

                    echo '<table style="border:1px solid" width=100%>';
                    echo '  <tr style="background:lightgrey">';
                    echo '      <td style="width:200px; padding-left:5px; white-space:nowrap;">' . format_text($mobj->subject) . ' </td>';
                    echo '      <td><p><a id="showhide' . $toggleid . '" href="javascript:toggle(\'teachermail' . $toggleid . '\',\'showhide' . $toggleid . '\');">' . $show . '</a></p></td>';

                    if ($teacher) {
                        echo '<td style="text-align:right">';

                        echo '<select name="gainedweight_' . $signedoutid . '" >';
                        echo '  <option value="0" ' . (($multiplier == 0) ? 'selected' : '') . '>' . get_string('fail', 'assignment_mailsimulator') . '</option>';
                        echo '  <option value="1" ' . ($multiplier == 1 ? 'selected' : '') . '>' . get_string('ok') . '</option>';
                        echo '  <option value="2" ' . ($multiplier == 2 ? 'selected' : '') . '>' . get_string('good', 'assignment_mailsimulator') . '</option>';
                        echo '</select> ';

                        echo get_string('weight', 'assignment_mailsimulator') . ': ' . $mobj->gainedweight * $mobj->weight . ' / ' . $mobj->weight * 2;
                        helpbutton('weight', get_string('weight', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
                        echo '&nbsp; </td>';
                    }
                    echo '  </tr>';
                    echo '</table>';
                    echo '<div id="teachermail' . $toggleid . '" style="border:1px solid; padding: 5px; background-color:#ffffff; display: none;">' . $mobj->message . '</div>'; ######

                    if ($mobj->studentreplys) {

                        echo '<div style="padding: 5px; background:white; border-left:1px solid; border-right:1px solid">';
                        foreach ($mobj->studentreplys as $reply) {
                            echo format_text('<b>' . $reply->subject . '</b><br />' . $reply->message) . ($reply->attachment ? $this->get_files_str($reply->id, $reply->userid) : '');
                        }
                        echo '</div>';
                    } else {
                        notify(get_string('noanswer', 'assignment_mailsimulator'), 'error', 'center');
                    }

                    if ($teacher) {
                        echo '<div style="padding: 5px; background:white; color:green; border:1px solid black">' . format_text($mobj->correctiontemplate) . '</div>';
                        echo '<label for="c' . $signedoutid . '">' . get_string('comment', 'assignment') . ':</label>';
                        echo '<input id="c' . $signedoutid . '" type="text" name="comment_' . $signedoutid . '" value="' . $mobj->comment . '" style="width: 100%;"><br /><br />';
                    } else {
                        echo '<div style="padding: 5px; background:white; border:1px solid black"><p style="color:green;">' . get_string('comment', 'assignment') . ':</p>' . $mobj->comment . '</div><br />';
                    }
                }
            } else {
                echo get_string('noreplys') . '<br />';
            }


            if ($newmailarr) {
                print_heading(get_string('newmail', 'assignment_mailsimulator') . ':');

                foreach ($newmailarr as $mid => $mobj) {
                    echo '<div style="padding-left: 5px; background:lightgrey; border:1px solid;">' . format_text($mobj->subject) . '</div>';
                    echo '<div style="padding: 5px; background:white; border-left:1px solid; border-right:1px solid; border-bottom:1px solid">';
                    echo format_text($mobj->message) . ($mobj->attachment ? $this->get_files_str($mobj->id, $mobj->userid) : '');
                    echo '</div><br />';
                }
//            } else {
//                print_heading(get_string('nonewstudentmail', 'assignment_mailsimulator'));
            }

            if ($signedoutarr && $teacher) {
                echo '<br /><br />' . get_string('needcompletion', 'assignment_mailsimulator') . ': ';
                echo '<select name="completion" >';
                echo '  <option value="2" >' . get_string('no') . '</option>';
                echo '  <option value="3" >' . get_string('yes') . '</option>';
                echo '</select> ';
                echo '<br /><br /><input type="submit" value="Submit" name="submit" />';
            }
            if ($teacher) {
                echo '</form>';
            }
            print_box_end();
//        } else {
//            print_string('emptysubmission', 'assignment');
        }
    }

    function get_recursive_replies($arr, &$tmp, $userid) {
        if (!$arr) {
            return false;
        }

        foreach ($arr as $key => $value) {
            $select = 'parent = ' . $key . ' AND userid = ' . $userid . ' AND assignment = ' . $this->assignment->id;
            $rearr = get_records_select('assignment_mailsimulation_mail', $select);

            if ($rearr)
                $this->get_recursive_replies($rearr, $tmp, $userid);

            $tmp[$value->id] = $value;
        }
        return $tmp;
    }

}

?>
