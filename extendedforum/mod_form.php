<?php
/* deleted and created from trunk */
require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_extendedforum_mod_form extends moodleform_mod {

    function definition() {

        global $CFG, $COURSE;
        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('extendedforumname', 'extendedforum'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $extendedforum_types = extendedforum_get_extendedforum_types();

        asort($extendedforum_types);
        $mform->addElement('select', 'type', get_string('extendedforumtype', 'extendedforum'), $extendedforum_types);
        $mform->setHelpButton('type', array('extendedforumtype', get_string('extendedforumtype', 'extendedforum'), 'extendedforum'));
        $mform->setDefault('type', 'general');
        $mform->setType('type', PARAM_TEXT)  ;
         
          //Hide author settings 
 	       $mform->addElement('checkbox', 'hideauthor', get_string('hideauthor','extendedforum'), get_string('hideauthorcomment','extendedforum') );
         $mform->setHelpButton( 'hideauthor'   , array('hideauthor', get_string('hideauthor', 'extendedforum') , 'extendedforum' ));
         $mform->setDefault('hideauthor', 0);
         $mform->setType('hideauthor', PARAM_INT )     ;
 
        $mform->addElement('htmleditor', 'intro', get_string('extendedforumintro', 'extendedforum'));
        $mform->setType('intro', PARAM_RAW);
        $mform->addRule('intro', get_string('required'), 'required', null, 'client');
        $mform->setHelpButton('intro', array('writing', 'questions', 'richtext'), false, 'editorhelpbutton');

        $options = array();
        $options[0] = get_string('no');
        $options[1] = get_string('yesforever', 'extendedforum');
        $options[EXTENDEDFORUM_INITIALSUBSCRIBE] = get_string('yesinitially', 'extendedforum');
        $options[EXTENDEDFORUM_DISALLOWSUBSCRIBE] = get_string('disallowsubscribe','extendedforum');
        $mform->addElement('select', 'forcesubscribe', get_string('forcesubscribeq', 'extendedforum'), $options);
        $mform->setHelpButton('forcesubscribe', array('subscription2', get_string('forcesubscribeq', 'extendedforum'), 'extendedforum'));
        $mform->setType('forcesubscribe',    PARAM_INT);
        
        
        $options = array();
        $options[EXTENDEDFORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'extendedforum');
        $options[EXTENDEDFORUM_TRACKING_OFF] = get_string('trackingoff', 'extendedforum');
        $options[EXTENDEDFORUM_TRACKING_ON] = get_string('trackingon', 'extendedforum');
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'extendedforum'), $options);
        $mform->setHelpButton('trackingtype', array('trackingtype', get_string('trackingtype', 'extendedforum'), 'extendedforum'));
        $mform->setType('trackingtype', PARAM_INT)       ;
         
         // Multi Attachment settings added by Moodlerooms
 	       $mform->addElement('checkbox', 'multiattach', get_string('allowmultiattach','extendedforum') );
         $mform->setHelpButton('multiattach', array('multiattach', get_string('multiattach','extendedforum'), 'extendedforum'));
         $mform->setDefault('multiattach', 1);
         $mform->setType('multiattach', PARAM_INT )     ;
 
 	      $choices = array();
         for($i = 1; $i < ($CFG->extendedforum_maxattachments+1); $i++){
             $choices[$i] = $i;
        }
         $mform->addElement('select','maxattach',get_string('maxattachnum','extendedforum'),$choices);
         $mform->disabledIf('maxattach', 'multiattach');
         $mform->setHelpButton('maxattach', array('maxattach', get_string('maxnum','extendedforum'), 'extendedforum'));
         $mform->setDefault('maxattach', $CFG->extendedforum_maxattachments);
         $mform->setType('maxattach', PARAM_INT )     ;
        //End Multi Attachment settings
 
       // $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
       // $choices[1] = get_string('uploadnotallowed');
       // $choices[0] = get_string('courseuploadlimit') . ' ('.display_size($COURSE->maxbytes).')';
       // $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'extendedforum'), $choices);
       // $mform->setHelpButton('maxbytes', array('maxattachmentsize', get_string('maxattachmentsize', 'extendedforum'), 'extendedforum'));
       //  $mform->setDefault('maxbytes', $CFG->extendedforum_maxbytes);
       
       $mform->addElement('hidden', 'maxbytes' , $CFG->extendedforum_maxbytes);
       $mform->setType('maxbytes', PARAM_INT )     ;
       
        if ($CFG->enablerssfeeds && isset($CFG->extendedforum_enablerssfeeds) && $CFG->extendedforum_enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', '', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('discussions', 'extendedforum');
            $choices[2] = get_string('posts', 'extendedforum');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->setHelpButton('rsstype', array('rsstype', get_string('rsstype'), 'extendedforum'));

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->setType('rsstype', PARAM_INT )     ;
            
            $mform->setHelpButton('rssarticles', array('rssarticles', get_string('rssarticles'), 'extendedforum'));
        }

//-------------------------------------------------------------------------------
        $mform->addElement('header', '', get_string('grade'));

        $mform->addElement('select', 'assessed', get_string('aggregatetype', 'extendedforum') , extendedforum_get_aggregate_types());
        $mform->setDefault('assessed', 0);
        $mform->setHelpButton('assessed', array('assessaggregate', get_string('aggregatetype', 'extendedforum'), 'extendedforum'));
         $mform->setType('assessed', PARAM_INT )     ;
         
        $mform->addElement('modgrade', 'scale', get_string('grade'), false);
        $mform->disabledIf('scale', 'assessed', 'eq', 0);
         $mform->setType('scale', PARAM_INT )     ;
         
        $mform->addElement('checkbox', 'ratingtime', get_string('ratingtime', 'extendedforum'));
        $mform->disabledIf('ratingtime', 'assessed', 'eq', 0);
        $mform->setType('ratingtime', PARAM_INT )     ;
          
        $mform->addElement('date_time_selector', 'assesstimestart', get_string('from'));
        $mform->disabledIf('assesstimestart', 'assessed', 'eq', 0);
        $mform->disabledIf('assesstimestart', 'ratingtime');
         $mform->setType('assesstimestart', PARAM_INT )     ;
         
        $mform->addElement('date_time_selector', 'assesstimefinish', get_string('to'));
        $mform->disabledIf('assesstimefinish', 'assessed', 'eq', 0);
        $mform->disabledIf('assesstimefinish', 'ratingtime');
        $mform->setType('assesstimefinish', PARAM_INT )     ;

//-------------------------------------------------------------------------------
        $mform->addElement('header', '', get_string('blockafter', 'extendedforum'));
        $options = array();
        $options[0] = get_string('blockperioddisabled','extendedforum');
        $options[60*60*24]   = '1 '.get_string('day');
        $options[60*60*24*2] = '2 '.get_string('days');
        $options[60*60*24*3] = '3 '.get_string('days');
        $options[60*60*24*4] = '4 '.get_string('days');
        $options[60*60*24*5] = '5 '.get_string('days');
        $options[60*60*24*6] = '6 '.get_string('days');
        $options[60*60*24*7] = '1 '.get_string('week');
        $mform->addElement('select', 'blockperiod', get_string("blockperiod", "extendedforum") , $options);
        $mform->setHelpButton('blockperiod', array('manageposts', get_string('blockperiod', 'extendedforum'),'extendedforum'));

        $mform->addElement('text', 'blockafter', get_string('blockafter', 'extendedforum'));
        $mform->setType('blockafter', PARAM_INT);
        $mform->setDefault('blockafter', '0');
        $mform->addRule('blockafter', null, 'numeric', null, 'client');
        $mform->setHelpButton('blockafter', array('manageposts', get_string('blockafter', 'extendedforum'),'extendedforum'));
        $mform->disabledIf('blockafter', 'blockperiod', 'eq', 0);


        $mform->addElement('text', 'warnafter', get_string('warnafter', 'extendedforum'));
        $mform->setType('warnafter', PARAM_INT);
        $mform->setDefault('warnafter', '0');
        $mform->addRule('warnafter', null, 'numeric', null, 'client');
        $mform->setHelpButton('warnafter', array('manageposts', get_string('warnafter', 'extendedforum'),'extendedforum'));
        $mform->disabledIf('warnafter', 'blockperiod', 'eq', 0);

//-------------------------------------------------------------------------------
        $features = new stdClass;
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $this->standard_coursemodule_elements($features);
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();

    }

    function definition_after_data(){
        parent::definition_after_data();
       
        $mform     =& $this->_form;
        $type      =& $mform->getElement('type');
        $typevalue = $mform->getElementValue('type');

        //we don't want to have these appear as possible selections in the form but
        //we want the form to display them if they are set.
        if ($typevalue[0]=='news'){
            $type->addOption(get_string('namenews', 'extendedforum'), 'news');
            $type->setHelpButton(array('extendedforumtypenews', get_string('extendedforumtype', 'extendedforum'), 'extendedforum'));
            $type->freeze();
            $type->setPersistantFreeze(true);
            
            //remove the hide author  from the news extendedforum
            $mform->removeElement('hideauthor');
           
        }
        if ($typevalue[0]=='social'){
            $type->addOption(get_string('namesocial', 'extendedforum'), 'social');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }

    }

    function data_preprocessing(&$default_values){
        if (empty($default_values['scale'])){
            $default_values['assessed'] = 0;
        }

        if (empty($default_values['assessed'])){
            $default_values['ratingtime'] = 0;
        } else {
            $default_values['ratingtime']=
                ($default_values['assesstimestart'] && $default_values['assesstimefinish']) ? 1 : 0;
        }
    }

}
?>
