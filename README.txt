The Mail Simulator Assignment simulates an email inbox, which can be useful for instance in project management courses.
It has a built-in rating assistance based on [[#weight|weights]] making it easy to grade large assignments.
It can perform random distribution of alternative emails (student tasks) which makes cheating harder.
Thoughts and questions about the module can be sent to the following [http://moodle.org/mod/forum/discuss.php?d=177120 forum topic].

This project was developed by Thomas Alsén at the [http://dsv.su.se/en/ Department of Computer and Systems sciences]  within Stockholms University.


==Features==
* Task (email) randomization
* File upload
* Rating assistance built on weights
* Dummy email contacts
* Deactivation/reactivation of tasks (emails)
* Ability for students to recomplete an assignment
* Enhanced grading view
* Correction templates for each task (email)
* Teacher can comment on each task (email)
* Extended feedback for students

==Installation==
# Unzip the files to <code>/mod/assignment/type/mailsimulator</code>
# Visit the notifications page and the module will install.

==Setting up a Mailsimulator assignment==
===General setup===
# Select Mail Simulator under Assignments from the Activities menu on the course page.
# Fill out the settings form
## General settings
### Fill in the assignment name§
### Fill in the assignment description
## Mail Simulator settings
### Select a value for "Number of mail". This will decide how many times the setup process for email will iterate. For example if it is set to 2, the setup process will automatically redirect you to add an email, weight and correction template 2 times before the setup is finished. Emails can be deleted or new ones can be added after the setup process is done.
### Select a value for "Maxweight per mail". If this is set to 1 then an email in this assignment can never have another value than 1 (see [[#weight|Weight declaration]]).
### Select a value for "Teacher mail". This will set the default "Teacher mail" for this assignment, it can be changed later on when more contacts have been added by clicking the "Update this Assignment" button.
### If "Disable attachments" is set to Yes or if no fields are selected in "Allowed filetypes", the assignment will not allow files to be uploaded.
# Add at least one "dummy" contact
# Add new mail, weights and correction templates as many times as you specified in 2.2.1

===Adding alternative mail===
To add an alternative mail: 
# Click the "Mailbox" tab if not already in the Mailbox view
# Click the "Add alternative mail" button in the mail in which you would like to add an alternative
# Fill out the mail form and then the weight/correctiontemplate form

===Delete, deactivate or restore a mail===
To delete a mail just click the "Delete" button for that mail. When a mail is in use (when at least one student has checked out the mail) the Delete button will be dissabled and can not be deleted. If you don't want new students (ones who hasn't already attended the assignment) to get a specific mail you can click the "Trash" button and it will be moved to the trash tab. Mail found under the trash tab can be reactivated by clicking the "Restore" button.

==<span id="weight">Weight declaration</span>==
Weights are used to assist in the grading, the weight determines how much a mail is worth.
If a student has given a Good answer the weight is multiplied by 2, if the answer is OK multiply the weight by 1 and if the answer is Fail multiply the weight with 0.

Example of an assignment with three mail:
 5 (w) x 2 (Good) = 10
 3 (w) x 1 (OK) = 3
 4 (w) x 0 (Fail) = 0
 Total weight is 13 out of 24 possible.

==Tips and tricks==
===Multiple file attachments===
A teacher can add multiple files in a mail by clicking the "Edit" button in the mail body and then the "Choose file" button. When the "Send" button is clicked this procedure can be repeated to add more files. Note that this can only be done on mail that hasn't been checked out by a student.

A student need to make a new reply or reply on his/her own mail to be able to send multiple files in a mail. If an assignment requires multiple files in a single mail you should describe this procedure in the assignment description.

===Nested dummy conversations===
To create a nested conversation just click "Reply" or "Reply all" and then chose the receiver and sender of the mail and last fill out the mail content. This can be repeated to simulate an ongoing conversation.

==Screenshots==
===Teacher Views===
{| border="0"
|-
| [[File:SetupAddAssignment.png|200px|thumb|none|New Assignment setup]]
| [[File:teacherAddContacts.png|200px|thumb|none|Add Contacts]]
| [[File:teacherAddMail.png|200px|thumb|none|Add Mail]]
| [[File:teacherAddCorrectionTemp.png|200px|thumb|none|Add Correction Template & Weight]]
|-
| [[File:teacherMailboxView.png|200px|thumb|none|Mailbox]]
| [[File:teacherTrashTab.png|200px|thumb|none|Trash]]
| [[File:teacherAlternativeMail.png|200px|thumb|none|Alternative Mail]]
| [[File:teacherGradingView.png|200px|thumb|none|Grading]]
|-
| [[File:teacherEnhancedGrading.png|200px|thumb|none|Enhanced Grading]]
| 
| 
| 
|}
===Student Views===
{| border="0"
|-
| [[File:studentInbox.png|200px|thumb|none|Inbox]]
| [[File:studentSent.png|200px|thumb|none|Sent]]
| [[File:studentReply.png|200px|thumb|none|Reply]]
| [[File:studentDescriptionReadyforgrading.png|200px|thumb|none|Description & Ready for grading button]]
|-
| [[File:studentFeedback.png|200px|thumb|none|Feedback]]
| 
| 
| 
|}

==See also==
* [http://moodle.org/mod/data/view.php?d=13&rid=4866 Moodle Modules Posting] is a Modules and plugins database page that has download links and more information.
* [http://moodle.org/mod/forum/discuss.php?d=177120 Discussion]
* [https://github.com/sockiplastsmurfen/moodle-assignment_reflection/tree/MOODLE_19_STABLE Git Repository for Moodle 1.9]
* [https://github.com/sockiplastsmurfen/moodle-assignment_reflection/zipball/MOODLE_19_STABLE Download module for Moodle 1.9]

[[Category:Contributed code]]
