<?php
  require_once('../../config.php');     
  require_once('lib.php');
   
 
    
    $discussionid = required_param('d', PARAM_INT) ;        //discussion id
    $action = required_param('action', PARAM_ALPHA)    ;    //action what to do add or delete
    $postid = optional_param('p', 0,PARAM_INT);                // Post ID  
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
    
    if(!$discussionid)
    {
       echo ('discussion id is missing')    ;
       return;
    }
    
     
    if($action == 'addflag')
    {
         if(!$postid)
        {
           echo ('message id is missing') ;
           return;  
         }
        add_post_flag($postid, $user_id);
        
    }
    else if($action == 'deleteflag')
    {
       
         if(!$postid)
         {
         echo ('message id is missing') ;
         return;  
         }  
        delete_flag($postid, $user_id)  ;
    } else if($action == 'deleteallflags')
    {
       delete_all_flags($discussionid, $user_id)     ;
      
       return;
    }
   
    //now count if the discussion has flagged messages
   $count = count_flaged_posts($discussionid) ;
    echo (  $count) ;
   
?>
