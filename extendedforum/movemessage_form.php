<?php


 if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

 require_once($CFG->libdir.'/formslib.php');

class mod_extendedforum_movemessage_form extends moodleform {

   function definition() {
     global $CFG, $USER;
     $mform    =& $this->_form;
     
    $currentextendedforum = $this->_customdata->currentextendedforum;
    $cm = $this->_customdata->cm;
    $course = $this-> _customdata->course;
    $postid = $this-> _customdata->postid;
    $postparent = $this->_customdata->postparent;
     
      $alldiscussions  = extendedforum_get_discussions($cm)  ;  //get top discussion list in a post format
     
     //if there is more then one discussion, choose between moving the message to a different extendedforum
    //or this extendedforum
    $selectclass = '';
      if (count($alldiscussions  ) > 1 )
      {
         $selectclass = 'hiddenclass';
      //fieldset
       $mform->addElement('header', 'movemessageaction', get_string('movemessage_action', 'extendedforum'));
       
      //radio buttons
      $radioarray=array();
      $new_extendedforum_attributes = array('onClick'=>'changestyle("divextendedforumlist", "block"); changestyle("divdiscussionlist", "none"); changestyle("id_copybutton", "inline")');
      $this_extendedforum_attributes = array('onClick'=>'changestyle("divdiscussionlist", "block");   changestyle("divextendedforumlist", "none"); changestyle("id_copybutton", "none")');
      
      
         $radioarray[] =  $mform->createElement('radio', 'extendedforumordis', '', get_string('movemessage_newextendedforum', 'extendedforum'), 1, $new_extendedforum_attributes);
        $radioarray[] =  $mform->createElement('radio', 'extendedforumordis', '', get_string('movemessage_thisextendedforum', 'extendedforum'), 0, $this_extendedforum_attributes);
     
        $mform->addGroup($radioarray, 'radioar', '', array('<br/> '), false);
        $mform->setDefault('extendedforumordis', -1); //non is selected
        $mform->addRule('radioar', null, 'required');
        $mform->setType('radioar', PARAM_INT)  ;
      
       }
         //form select a extendedforum elements
      $group_attributes = array('class'=>$selectclass)    ;
      $extendedforum_list_obj = get_extendedforums_in_course($course)     ;
      
      $extendedforum_list = array();
      
      foreach ($extendedforum_list_obj as $extendedforum)
      {
          $id = $extendedforum->id     ;
          $name = $extendedforum->name ;
            if($extendedforum->id != $currentextendedforum->id)
            {
               $extendedforum_list[$id]   = $name;
             }
      
      }
      
      $mform->addElement('header', 'divextendedforumlist', get_string('selectalldiscussions', 'extendedforum'));    
      $mform->addElement('select', 'extendedforumlist', get_string('extendedforumlist', 'extendedforum') , $extendedforum_list, $group_attributes);
       $mform->setType('extendedforumlist', PARAM_INT)  ;
     
     
    //discussions radio button selection
      $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
      $sort = "p.created ASC"; 
      
       if (count($alldiscussions  ) > 1 )
      {
      $mform->addElement('header', 'divdiscussionlist', get_string('selectalldiscussions', 'extendedforum'));
    
        foreach($alldiscussions as $topdiscussion) {
        
         //print discussion header
         $discussion = get_record('extendedforum_discussions', 'id', $topdiscussion->discussion);
          //cannot move discussion to itself
          if($postparent == 0 )
          {
             if($postid == $discussion->firstpost)
             {
             
              continue;
             }
          }
         $discussionheaderobj =  new stdClass;
           extendedforum_get_discussion_header_obj($topdiscussion, $currentextendedforum, $cm, $modcontext,  $discussionheaderobj)   ;
          
          
           $discussion_final = '<a href="javascript:void(viewpost_ajax(' . $currentextendedforum->id .  ','.   $topdiscussion->id   . ',' .  $discussion->id . '))">'
                             . $discussionheaderobj->subject . '</a>'   ;
             
          $topid = $topdiscussion->id   ;
          
         
          $posts=  extendedforum_get_all_discussion_posts ($discussion->id, $sort, false, $currentextendedforum->hideauthor)  ;
          $all_threaded = array();
          
           //create radio buttons to all posts
          $mform->addElement('radio', 'postradio', '',  $discussion_final, $topid);
          
           //hidden div to show the post by ajax
          $mform->addElement('html', '<div id="post' . $topdiscussion->id . '" ></div>') ; 
           extendedforum_get_posts_threaded($cm,  $modcontext, $currentextendedforum, $discussion, $topdiscussion, 0, $posts, $all_threaded);
                
           foreach($all_threaded as $key_name => $key_value)
                  {
                   
                    if( isset($key_value->text) )
                     {
                        
                        if ($key_value->id == $postid)
                          {
                              //cannot select the same post
                                $html = $key_value->html . '<div class="fitem"><div class="fitemtitle"></div><div class="felement fradio">' .  $key_value->text . $key_value->nameanddate . "</div></div>"; 
                                 $mform->addElement('html'  ,  $html)  ;
                           }
                           else
                           {
                           $mform->addElement('html'  , $key_value->html )  ;
                            $mform->addElement('radio', 'postradio', '', $key_value->text . $key_value->nameanddate, $key_name  );
                           }
                           $mform->addElement('html', '<div id="post' . $key_value->id . '" ></div>') ;   
                          
                     }
                     else
                     {
                      $mform->addElement('html', '</div>');
                     }
                     
                    
                  }
                  
          $mform->addElement('html' , '<div class="hr"> <hr /></div>')   ;
             
       }
       
      } 
       $mform->closeHeaderBefore('buttonar');
    
    
        //Hidden fields
       $mform->addElement('hidden', 'p' , $postid);
       $mform->setType('p', PARAM_INT)      ;
       $mform->addElement('hidden', 'f' , $currentextendedforum->id);
       $mform->setType('f', PARAM_INT)   ;
       
       $hidden_attrib = array('id' =>'hiddenaction')  ;
        $mform->addElement('hidden', 'action' , 'move',  $hidden_attrib);
     
        
        $buttonarray=array();
        
         $move_attributes = array('onClick'=>  'submitmoveform("id_movebutton", "hiddenaction", "mform1")'  );
         //$copy_attributes = array('onClick'=>  'submitmoveform("id_copybutton", "hiddenaction", "mform1")'  );
         $cancel_attributes = array('onClick' => 'cancelform("mform1")')  ;
          
        //$buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
       $buttonarray[] = &$mform->createElement('button', 'movebutton', get_string('movebutton', 'extendedforum'), $move_attributes);
       
     
         if (count($alldiscussions  ) > 1 )  {
       $buttonarray[] = &$mform->createElement('button', 'cancel', get_string('resetmove', 'extendedforum'),  $cancel_attributes);
        }
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        
     
  }

/// perform some extra moodle validation
    function validation($data,  $file) {
        global $CFG;

        $errors = parent::validation($data, $file);
         return $errors;
    }  
}
?>
