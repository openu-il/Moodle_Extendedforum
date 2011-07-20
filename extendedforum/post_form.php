<?php  // $Id: post_form.php,v 1.21.2.8 2010/05/13 01:40:38 moodler Exp $

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class mod_extendedforum_post_form extends moodleform {

    function definition() {

        global $CFG;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
        $coursecontext = $this->_customdata['coursecontext'];
        $modcontext    = $this->_customdata['modcontext'];
        $extendedforum         = $this->_customdata['extendedforum'];
        $post          = $this->_customdata['post']; // hack alert
        

        // the upload manager is used directly in post precessing, moodleform::save_files() is not used yet
        if ($extendedforum->multiattach) {
                 $this->set_upload_manager(new upload_manager('', false, false, $course, true, $extendedforum->maxbytes, true, true, false));
         } else {
        $this->set_upload_manager(new upload_manager('attachment', true, false, $course, false, $extendedforum->maxbytes, true, true));
        }
        
        $mform->addElement('header', 'general', '');//fill in the data depending on page params
                                                    //later using set_data
        $mform->addElement('text', 'subject', get_string('subject', 'extendedforum'), 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client'); 

        $mform->addElement('htmleditor', 'message', get_string('message', 'extendedforum'), array('cols'=>50, 'rows'=>30));
        $mform->setType('message', PARAM_RAW);
     //   $mform->addRule('message', get_string('required'), 'required', null, 'client');
        $mform->setHelpButton('message', array('reading', 'writing', 'questions', 'richtext'), false, 'editorhelpbutton');

        $mform->addElement('format', 'format', get_string('format'));
        

        if (isset($extendedforum->id) && extendedforum_is_forcesubscribed($extendedforum)) {

            $mform->addElement('static', 'subscribemessage', get_string('subscription', 'extendedforum'), get_string('everyoneissubscribed', 'extendedforum'));
            $mform->addElement('hidden', 'subscribe');
            $mform->setType('subscribe', PARAM_INT);
            $mform->setHelpButton('subscribemessage', array('subscription', get_string('subscription', 'extendedforum'), 'extendedforum'));

        } else if (isset($extendedforum->forcesubscribe)&& $extendedforum->forcesubscribe != EXTENDEDFORUM_DISALLOWSUBSCRIBE ||
                    has_capability('moodle/course:manageactivities', $coursecontext)) {

            $options = array();
            $options[0] = get_string('subscribestop', 'extendedforum');
            $options[1] = get_string('subscribestart', 'extendedforum');

            $mform->addElement('select', 'subscribe', get_string('subscription', 'extendedforum'), $options);
            $mform->setHelpButton('subscribe', array('subscription', get_string('subscription', 'extendedforum'), 'extendedforum'));
        } else if ($extendedforum->forcesubscribe == EXTENDEDFORUM_DISALLOWSUBSCRIBE) {
            $mform->addElement('static', 'subscribemessage', get_string('subscription', 'extendedforum'), get_string('disallowsubscribe', 'extendedforum'));
            $mform->addElement('hidden', 'subscribe');
            $mform->setType('subscribe', PARAM_INT);
            $mform->setHelpButton('subscribemessage', array('subscription', get_string('subscription', 'extendedforum'), 'extendedforum'));
        }

   		 // If exists attachment - show checkbox in edit form
        if (isset($post->attachment) && !empty($post->attachment))
        {        	
        	extendedforum_print_edit_attachments($mform, $post);
        }
        
        if ($extendedforum->maxbytes != 1 && has_capability('mod/extendedforum:createattachment', $modcontext))  {  //  1 = No attachments at all
            	if ($extendedforum->multiattach) {

			// Multiattachment feature requires javascript on in order to add more file upload fields
			$mform->addElement('file', 'FILE_0', get_string('attachment', 'extendedforum'));
			$mform->setType('file', PARAM_TEXT);
			
			$mform->addElement('link', 'addinput','', '#', get_string('anotherfile', 'extendedforum') ,'onclick="addFileInput(\''.get_string('remove', 'extendedforum').'\','.$extendedforum->maxattach.');"' );

      $file_count_attributes = array('id'=>'file_countid') ;
      $mform->addElement('hidden', 'file_count', 0, $file_count_attributes )  ;
       $mform->setType('file_count', PARAM_INT);
			// rewrite form with the new elements
			foreach( $_FILES as $key=>$value) {
 				if ( substr($key, 0, strlen($key)-1) == 'FILE_' && !$mform->elementExists($key)) {
					$mform->addElement('file', $key, '', 'value="'.$value.'"');
				}
			}
 		} else {						
           
            $mform->addElement('file', 'attachment', get_string('attachment', 'extendedforum'));
            $mform->setHelpButton('attachment', array('attachment', get_string('attachment', 'extendedforum'), 'extendedforum'));
       }
        }

        if (empty($post->id) && has_capability('moodle/course:manageactivities', $coursecontext)) { // hack alert
            $mform->addElement('checkbox', 'mailnow', get_string('mailnow', 'extendedforum'));
             $mform->setType('mailnow', PARAM_INT);
        }

        if (!empty($CFG->extendedforum_enabletimedposts) && !$post->parent && has_capability('mod/extendedforum:viewhiddentimedposts', $coursecontext)) { // hack alert
            $mform->addElement('header', '', get_string('displayperiod', 'extendedforum'));

            $mform->addElement('date_selector', 'timestart', get_string('displaystart', 'extendedforum'), array('optional'=>true));
            $mform->setHelpButton('timestart', array('displayperiod', get_string('displayperiod', 'extendedforum'), 'extendedforum'));

            $mform->addElement('date_selector', 'timeend', get_string('displayend', 'extendedforum'), array('optional'=>true));
            $mform->setHelpButton('timeend', array('displayperiod', get_string('displayperiod', 'extendedforum'), 'extendedforum'));

        } else {
            $mform->addElement('hidden', 'timestart');
            $mform->setType('timestart', PARAM_INT);
            $mform->addElement('hidden', 'timeend');
            $mform->setType('timeend', PARAM_INT);
            $mform->setConstants(array('timestart'=> 0, 'timeend'=>0));
        }

        if (groups_get_activity_groupmode($cm, $course)) { // hack alert
            if (empty($post->groupid)) {
                $groupname = get_string('allparticipants');
            } else {
                $group = groups_get_group($post->groupid);
                $groupname = format_string($group->name);
            }
            $mform->addElement('static', 'groupinfo', get_string('group'), $groupname);
        }

//-------------------------------------------------------------------------------
        // buttons
        if (isset($post->edit)) { // hack alert
            $submit_string = get_string('savechanges');
        } else {
            $submit_string = get_string('posttoextendedforum', 'extendedforum');
        }
        $this->add_action_buttons(false, $submit_string);
       
       
        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'extendedforum');
        $mform->setType('extendedforum', PARAM_INT);

        $mform->addElement('hidden', 'discussion');
        $mform->setType('discussion', PARAM_INT);

        $mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'reply');
        $mform->setType('reply', PARAM_INT);

    }

    function validation($data, $files) {
      //remove input with empty file name
    foreach ($_FILES as $name => $file) {
       if($file['name'] == '') {
            unset($_FILES[$name]);   
        }
      }
        $errors = parent::validation($data, $files);
        if (($data['timeend']!=0) && ($data['timestart']!=0)
            && $data['timeend'] <= $data['timestart']) {
                $errors['timeend'] = get_string('timestartenderror', 'extendedforum');
            }
        return $errors;
    }

}
?>
