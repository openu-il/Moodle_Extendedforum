<?php
   require_once('../../config.php');     
   require_once('lib.php');
  
   
   $extendedforumid =  required_param('f',PARAM_INT);                // extendedforum ID
   $action = required_param('action', PARAM_ALPHA)    ;    //action what to do 
   $postid = optional_param('p',0,PARAM_INT);                // Post ID 
    
    
    if(!$extendedforumid )
    {
        echo('extendedforum is missing')    ;
        return;
        
    }
    
   
    if($action == 'singleread')
    {
       if(!$postid)
       {
          echo('message is missing')    ;
          return;
        
       }
    
       if (!$post = get_record('extendedforum_posts', 'id', $postid)) {
        error('post id is not correct');
        return;
      }
    
   
       extendedforum_tp_mark_post_read($USER->id, $post, $extendedforumid);
    }
    else if($action == 'multipleread')
    {
      @$multiplepost = optional_param('ps', 0, PARAM_INT)  ;
     if( is_array($multiplepost)){
       while (list ($key, $val) = each ($multiplepost)) {
           
            if ($post = get_record('extendedforum_posts', 'id', $val)) { 
                   extendedforum_tp_mark_post_read($USER->id, $post, $extendedforumid); 
   
            }
          //  else
         //   {
              // echo ("error, no message for post $val")  ;
           // }
           
        }
     } 
     //else
     //{
      // echo ("not array") ;
     //}
   }

    
   
    
  
  
?>
