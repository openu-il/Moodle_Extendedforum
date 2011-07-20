<?php
  require_once('../../config.php');     
  require_once('lib.php');
   
 
    
    $discussionid = optional_param('d', 0 , PARAM_INT) ;        //discussion id
    $action = required_param('action', PARAM_ALPHA)    ;    //action what to do add or delete
    $postid = optional_param('p', 0,PARAM_INT);             // Post ID 
    $extendedforumid = optional_param('f', 0, PARAM_INT)      ;       //extendedforum id
    $ajax = optional_param('ajax', 0, PARAM_INT)       ;    //is this a ajax request
   
    
       
    $user_id = $USER->id;
    
       
    
    if(!$action)
    {
        echo('flag action is missing')    ;
        return;
        
    }
    
    if(!$user_id)
    {
        echo(get_string('flag_error_no_user', 'extendedforum'))  ;
        return ;
    }
    
    
    if($action == 'addflag')
    {
         if(!$postid)
        {
           echo ('message id is missing') ;
           return;  
         }
         //make sure post exists
         if (! $post = get_record('extendedforum_posts', 'id', $postid)) {
                error('The post number was incorrect');
                return;
          }
        add_post_flag($post, $user_id);
        
         //return 1 to indicate that we have at least one flagged message
        echo 1; 
    
       return;
        
    }
    else if($action == 'deleteflag')
    {
       
         if(!$postid)
         {
         echo ('message id is missing') ;
         return;  
         } 
           if (! $post = get_record('extendedforum_posts', 'id', $postid)) {
                error('The post number was incorrect');
                return;
          } 
        delete_flag($post, $user_id)  ;
       
        //now count if the discussion has flagged messages
        $count = count_flaged_posts($discussionid) ;
       echo (  $count) ;
    
       return;
    } else if($action == 'deleteallflags')
    {
       if(!$discussionid)
       {
          echo ('discussion id is missing') ;
          return;
       
       }
       if (! $discussion = get_record('extendedforum_discussions', 'id', $discussionid)) {
                error('The discussion number was incorrect');
                return;
          } 
       
       delete_all_flags($discussion, $user_id)     ;
      
       return;
    } else if($action == 'addrecommend')
    {
      if (! $post = get_record('extendedforum_posts', 'id', $postid)) {
                error('The post number was incorrect');
                return;
          }
      recommend_post($post, $user_id)   ; 
      //echo 1 to show that we have it least one recommanded message
      echo 1; 
       return;
    }  else if($action == 'deleterecommend')
    {
      if (! $post = get_record('extendedforum_posts', 'id', $postid)) {
                error('The post number was incorrect');
                return;
          }
       undorecommend_post($post, $user_id)     ;
       
       //now count if the discussion has recommaned messages
       $count = count_recommanded_posts($discussionid)    ;
       echo ($count) ;
       return;
    } else if ($action == 'printpost')
    {
          if (! $extendedforum = get_record("extendedforum", "id", $extendedforumid)) {
            error("Forum ID was incorrect or no longer exists");
            return;
        }
        
         if (!$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $extendedforum->course)) {
            error("Course Module missing");
             return;
        }
        
         if (! $post = extendedforum_get_post_full($postid, 0 , $extendedforum->hideauthor)) {
                error('Post ID was incorrect');
                 return;
        }
        
        if (! $course = get_record("course", "id", $extendedforum->course)) {
            error("Forum is misconfigured - don't know what course it's from");
             return;
        }
        //make sure user can see post
      if (!$discussion = get_record('extendedforum_discussions','id',$discussionid))
      {
        error("Discussion ID was incorrect or no longer exists");
            return;
      
      }
       if( !extendedforum_user_can_see_post($extendedforum, $discussion, $post, null, $cm  ))
       {
        //user cannot view post
        debugging('user cannot view post', DEBUG_DEVELOPER);
         return;
      }
      $userfrom = get_record('user', 'id', $post->userid);
      
      $ratings = null;
       
            if ($extendedforum->assessed) {
                if ($scale = make_grades_menu($extendedforum->scale)) {
                    $ratings =new object();
                    $ratings->scale = $scale;
                    $ratings->assesstimestart = $extendedforum->assesstimestart;
                    $ratings->assesstimefinish = $extendedforum->assesstimefinish;
                    $ratings->allow = false;
                }
            }
       extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, false, false, false, $ratings, "", "", null, true, 
                      null , false, 0 , 0);
    
    }
   
  
?>
