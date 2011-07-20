<?php    

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class mod_extendedforum_forward_form extends moodleform {
     function definition() {

        global $CFG, $USER;
        $mform    =& $this->_form;
 
         //Email address
          $mform->addElement('text', 'email', get_string('forward_email_address', 'extendedforum'),
          array('size'=>48));
          $mform->setType('email', PARAM_RAW); 
           $mform->setHelpButton('email', array('forward_email', 
            get_string('forward_email_address', 'extendedforum'), 'extendedforum'));
            $mform->addRule('email', get_string('required'), 'required', null, 
            'client');
            $mform->addRule('email', get_string('invalidemail'), 'email', null, 'client')   ;
            
            
         // CC me
         $mform->addElement('checkbox', 'ccme', get_string('forward_ccme', 'extendedforum'));
         
         // Email subject
        $mform->addElement('text', 'subject', get_string('subject', 'extendedforum'),
            array('size'=>48));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('maximumchars', '', 255),
            'maxlength', 255, 'client');
        $mform->addRule('subject', get_string('required'),
            'required', null, 'client');
        $mform->setDefault('subject', $this->_customdata->subject);
        
        
        // Special field just to tell javascript that we're trying to use the
        // html editor
        $mform->addElement('hidden', 'tryinghtmleditor',
            can_use_html_editor() ? 1 : 0);

        // Email message
        $mform->addElement('htmleditor', 'message',
            get_string('forward_intro', 'extendedforum'), array('cols'=>50, 'rows'=> 15));
        $mform->setType('message', PARAM_RAW);
        $mform->setHelpButton('message', array('reading', 'writing',
            'questions', 'richtext'), false, 'editorhelpbutton');

        // Message format
        $mform->addElement('format', 'format', get_string('format'));
        
        //Hidden fields
        $mform->addElement('hidden', 'forward' , $this->_customdata->postid);
        $mform->addElement('hidden', 'f' , $this->_customdata->extendedforumid);
        $mform->addElement('hidden', 'page' , $this->_customdata->page);    
        
        $this->add_action_buttons(true, get_string('forwardbymail', 'extendedforum'));
     }
  
  /// perform some extra moodle validation
    function validation($data, $files) {
        global $CFG;

        $errors = parent::validation($data, $files);
    }  
}
?>
