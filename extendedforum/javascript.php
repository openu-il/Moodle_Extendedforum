// The maxattachment limit should be enforced both in javascript
// and in php.
// Taken from email sendmail.php js code with a few modifications


//Global var
var thewwwroot ; 
var upload_number = 1;
var globaltitleplus;
var globaltitleminus;
var global_title_clear_flag;
var global_title_set_flag;
var global_alt_flag_on;
var global_alt_flag_off;
var global_movemessage_error
var global_theme_path;
var global_mark_on;
var global_mark_off;


function movemessage_init(movemessage_error)
{
    global_movemessage_error =    movemessage_error;
}
function  extendedforum_init (wwwroot, titlePlus, titleMinus, title_clear_flag,
title_set_flag, alt_flag_on, alt_flag_off, title_mark_on, title_mark_off, theme_path)
{

 //Init var
 thewwwroot = wwwroot;
 globaltitleplus =  titlePlus;
 globaltitleminus = titleMinus ;
 global_title_clear_flag = title_clear_flag;
 global_title_set_flag =  title_set_flag;

 global_alt_flag_on =    alt_flag_on;
 global_alt_flag_off =     alt_flag_off;
 
 global_mark_on = title_mark_on;
 global_mark_off = title_mark_off ;
 global_theme_path =      theme_path;
 
 
}


function addFileInput(txt,max) {
	
	if (upload_number != max ) {
    	var d = document.createElement("div");
    	d.setAttribute("id", "id_FILE_"+upload_number);
    	var file = document.createElement("input");
    	file.setAttribute("type", "file");
    	file.setAttribute("name", "FILE_"+upload_number);
    	file.setAttribute("id", "FILE_"+upload_number);

    	d.appendChild(file);
    	var a = document.createElement("a");
    	a.setAttribute("href", "javascript:removeFileInput('id_FILE_"+upload_number+"');");
    	a.appendChild(document.createTextNode(txt));
    	d.appendChild(a);
    	document.getElementById("id_FILE_"+(upload_number-1)).parentNode.appendChild(d);
    	upload_number++;
	} else {
		alert("You are at your max attachment size of " + max);	
	}
	
	hiddencounter = document.getElementById('file_countid') ;
	 hiddencounter.value =  upload_number;
}

function removeFileInput(i) {
    var elm = document.getElementById(i);
    document.getElementById(i).parentNode.removeChild(elm);
    upload_number--;
    
    	hiddencounter = document.getElementById('file_countid') ;
	   hiddencounter.value =  upload_number;
}


function openCloseThread(postId, containerId, imageId, disucssionId, flagclass )
{

     var toOpen =  openCloseImage(imageId) ;
     var containerBody = document.getElementById(containerId) ;
     var markedspan = "markedbox" + disucssionId ;
     var allPosts = '';
    
         
       if( toOpen)
        {
         
             containerBody.style.display = 'block'  ;  
            
          var  allClassess =   YAHOO.util.Dom.getElementsByClassName('threadhideme' + disucssionId, 'div');  
            var counter = allClassess.length - 1;
          //show the message
           for (var i=0; i<allClassess.length; i++) 
           { 
                allClassess[i].style.display = 'block'  ;
                var id = allClassess[i].id.substring(10)  ;
              
                 var hidden_read = document.getElementById ('post_' + id) ;
                
                if(hidden_read != null) 
                {  
                //this is the first time the user is reading, mark that the
                 //use read this post
                
                   allPosts = allPosts + 'ps[]='  + id;
                    if (i < counter)
                    {
                     allPosts = allPosts +  '&'; 
                    }
                }
                
           
           }
            
             //in addition to thread hide me - check if the first message is hidden   
               var hidden_read = document.getElementById ('post_' + postId) ;
                  if(hidden_read != null) 
                  {
                      if(allPosts != '')
                      {
                        allPosts = allPosts + '&';
                      }
                      allPosts = allPosts + 'ps[]='  + postId;
                  }
           //hide the subject link
           
          var  allClassess =   YAHOO.util.Dom.getElementsByClassName('threadhidemesubject' + disucssionId, 'div');  
           for (var i=0; i<allClassess.length; i++) 
           { 
                allClassess[i].style.display = 'none'  ;
           }
           
           //show all box icons
           var allBoxClasses =   YAHOO.util.Dom.getElementsByClassName('boxicons'  , 'span' );
             for (var i=0; i<allBoxClasses.length; i++) 
           { 
                allBoxClasses[i].style.display = 'inline'  ;
           }
           //show the flag input
           
             var allFlagClassess =   YAHOO.util.Dom.getElementsByClassName(flagclass, 'img');  
           for (var i=0; i<allFlagClassess.length; i++) 
           { 
                allFlagClassess[i].style.visibility = 'visible'  ;
           }
           
            //show the marked messages
          var  allMarkedClasses =  YAHOO.util.Dom.getElementsByClassName(markedspan, 'span');
            for (var i=0; i<allMarkedClasses.length; i++) 
           { 
                allMarkedClasses[i].style.visibility = 'visible'  ;
                allMarkedClasses[i].style.display = 'inline'       ;
           }
             
           //now add a read to each post that it is the first time we read it
          
           if(allPosts != '')
           {
            var hidden_extendedforum = document.getElementById('extendedforum_id')  ;
            
             var extendedforum_id =  hidden_extendedforum.value  ;
             
                YAHOO.util.Connect.asyncRequest('POST', 'extendedforum_ajax.php', {
                success:function(oResponse) {
                     var response =  oResponse.responseText  ;
                     if(response != '')
                     {
                         alert(response)  ;
                     }
                
                 },
                   failure:function(oResponse) {
                    alert("error: " + oResponse.statusText);
               }},
                allPosts + '&f=' + extendedforum_id + '&action=multipleread');
           
           }
           
         }
         else
        {
             containerBody.style.display = 'none'  ;
         
           
      
        }
        
        
}
function openCloseDiscussion(containerId, postid, discussionid )
{

  var containerBody = document.getElementById(containerId) ;
  var style  =  containerBody.style.display;
   
 
  var display =    containerBody.style.display ;
    if(display == '' || display == 'none')
    {
     containerBody.style.display = 'block'  ;
        
       //hide the message table
        var allClassess =   YAHOO.util.Dom.getElementsByClassName('threadhideme' + discussionid, 'div');  
           for (var i=0; i<allClassess.length; i++) 
           { 
                allClassess[i].style.display = 'none'  ;
           }
           //show the subject title
        var   allClassess =   YAHOO.util.Dom.getElementsByClassName('threadhidemesubject' + discussionid, 'div');  
            
           for (var i=0; i<allClassess.length; i++) 
           { 
                allClassess[i].style.display = 'block'  ;
               
           }
         //mark the first message as read if needed
          var hidden_read = document.getElementById ('post_' + postid) ;
           if(hidden_read != null)    //this is the first time the user is reading, mark that the
                                      //use read this post
        {
           mark_message_as_read(postid);  
        }
       
    }
    
    else
    {
      containerBody.style.display = 'none'  ;
        
	     
    }
  
  
}

function openCloseImage(imageId)
{
   var image =  document.getElementById(imageId)   ;
   var src = image.src ;
   var indexOf = src.indexOf('plus.gif');
   
    if( indexOf > 0 )
   {
     
     image.src =  thewwwroot +  "/mod/extendedforum/pix/minus.gif";
		 image.title = globaltitleminus;
		 
      return true;
    }
    else
    {
      
      image.src = thewwwroot +  "/mod/extendedforum/pix/plus.gif";
	    image.title = globaltitleplus;
	    
      return false;
    }

}


function getPost(elementId, flagId, postId)
{
      
       var post = document.getElementById(elementId);
        var postSubject = document.getElementById('divpostsubject' + postId)
      
       style = post.style.display;
        
       if(style == 'block')
       {
             post.style.display = 'none'  ;
             postSubject.style.display = 'block' ;
             return;
       }
       else
       {
          post.style.display = 'block'      ;
          //hide post subject
          postSubject.style.display = 'none'   ;
        
          //hide all box icon  
          var boxicons = document.getElementById ('boxicons' + postId) ;
          if(boxicons != null)
          {
             boxicons.style.display = 'none'  ;
          }
          
          
           var hidden_read = document.getElementById ('post_' + postId) ;
           if(hidden_read != null)    //this is the first time the user is reading, mark that the
                                      //use read this post
        {
           mark_message_as_read(postId);  
        }
        
       }
        
        
     

}
 
  
function mark_message_as_read( postId)
{

             
              //get extendedforum id
               var hidden_extendedforum = document.getElementById('extendedforum_id')  ;
               var extendedforum_id =  hidden_extendedforum.value  ;
               
               //now make an ajax request to mark this post as read post
                YAHOO.util.Connect.asyncRequest('POST', 'extendedforum_ajax.php', {
                success:function(oResponse) {
                     var response =  oResponse.responseText  ;
                     if(response != '')
                     {
                         alert(response)  ;
                     }
                
                 },
                   failure:function(oResponse) {
                    alert("error: " + oResponse.statusText);
               }},
                'f=' + extendedforum_id + '&p=' + postId + '&ajax=1&action=singleread');


}  

function viewpost_ajax(extendedforumid, postid, discussionid)
{
 var element  = document.getElementById("post" + postid)  ;
 if(element.innerHTML == "")
 {
   YAHOO.util.Connect.asyncRequest('POST', 'postactions.php' , {
      success:function(oResponse) {
          
            if(element != null)
            {
              var response =  oResponse.responseText  ;
               element.innerHTML = response;
           }
      
    },
      failure:function(oResponse) {
                alert("error: " + oResponse.statusText);
            }},
            'f=' + extendedforumid + '&p=' + postid + '&d=' + discussionid + '&action=printpost'); 
  }
  else
  {
    element.innerHTML = "";
  }   

}
function remove_discussion_flag_ajax(discussionid, post_classname, flag_divid)
{

 var action = 'deleteallflags';

 //first remove all posts flag
    YAHOO.util.Connect.asyncRequest('POST', 'postactions.php', {
       success:function(oResponse) {
           var response =  oResponse.responseText  ;
           //probably an error
           if(response.length > 0 )
           {
             alert("Error:" + response);
           }
           else //remove the flags
           {
             //the div element
             var divElement = document.getElementById(flag_divid)   ;
             divElement.innerHTML = "";
             
             //all the posts
               allClassess =   YAHOO.util.Dom.getElementsByClassName(post_classname, 'img');  
              for (var i=0; i<allClassess.length; i++) 
              {
                   allClassess[i].src = thewwwroot +  "/mod/extendedforum/pix/flag_off.png";
                   
                   allClassess[i].title =global_alt_flag_off;
                   allClassess[i].alt = global_alt_flag_off;
              }
            }
        },
       failure:function(oResponse) {
                alert("error: " + oResponse.statusText);
            }},
            'd=' + discussionid + '&ajax=1&action=' + action); 
}


function remove_discussion_flag(discussionid, post_classname, flag_divid, withajax)
{
  if (withajax == 1)
  {
  
   remove_discussion_flag_ajax(discussionid, post_classname, flag_divid)
   }
   else
   {
      //
   }
  
       
}

function  change_flag_ajax(post_id, discussion_id , image_element_id)
{
   
   image_element  = document.getElementById(image_element_id);
   
   //the link to the command
    href_flag_element = document.getElementById('aflag' + post_id) ;
    
    href_flag_element_img = '<img boder="0" width="13" height="16"  src="'  +  thewwwroot +  "/mod/extendedforum/pix/simun-small.gif"  alt = "" title = ""   />';
    
    //span where we have the image in the post message
     span_element_box =   document.getElementById('spanflag' + post_id  + '_box') ;
      
      //span where we have the image next to the subject
      span_element =   document.getElementById('spanflag' + post_id ) ;
    
    var action =  'addflag' ;
    
    if (span_element_box.className == 'marked' )
    {
        action  = 'deleteflag'      ;
         span_element_box.className = 'unmarked' ;
         span_element_box.style.display  = 'none'   ;
         
         if(span_element != null)
         {
            span_element.className = 'unmarked' ;
             span_element.style.display  = 'none'   ;
         }
         
         hrefTitle = href_flag_element_img +  global_title_set_flag  ; 
    }
    else
    {
       span_element_box.className = 'marked'   ;
       span_element_box.style.display = 'inline' ;
       
       if(span_element != null)
         {
          span_element.className = 'marked'   ;
          span_element.style.display = 'inline' ;
         }
         
       hrefTitle =   href_flag_element_img +  global_title_clear_flag;
    }
    
    
  
   YAHOO.util.Connect.asyncRequest('POST', 'postactions.php', {
      success:function(oResponse) {
    
     if(href_flag_element != null)
     {
          href_flag_element.innerHTML = hrefTitle;
      
      }
       var response =  oResponse.responseText  ;
       //make sure response text is a number and  not an error message
       if (isNaN(response) )
       {
          alert(response)  ;
       }
       
       var flagelement = document.getElementById('flag' + discussion_id );
      
      if(response == 0 )
      {
         //remove the flag image
          flagelement.innerHTML = "";
      }
      else
      {
        //add the flag image
         
          flagelement.innerHTML =
           '<img  src="' + thewwwroot +  "/mod/extendedforum/pix/simun.gif"   alt = "' + global_alt_flag_on +  '" />';
                     
                          // '" onClick="remove_discussion_flag(' + discussion_id +  ', \'flag_post' + discussion_id +  '\', \'flag' + discussion_id +  '\', 1 )">';
      }
      },
            failure:function(oResponse) {
                alert("error: " + oResponse.statusText);
            }},
            'p='+post_id+'&d=' + discussion_id + '&ajax=1&action=' + action);  


}
function change_flag(post_id, discussion_id , image_element_id, withajax)
{

   if (withajax == 1)
  {
     change_flag_ajax(post_id, discussion_id , image_element_id)
  }
  else
  {
   //not supported
  }
  
}
 

 function recommand_with_ajax(post_id, isrecommend, discussion)
 {
    //we have two image elements we need to updated
    //one in the message title and one in the message box   
     
   var spanid = document.getElementById("marked" + post_id)  ;
   var spanidbox =     document.getElementById("markedbox" + post_id)  ;
   
   var action = '';
   if(isrecommend == 1)
   {
      action = "addrecommend"    ;
   }
   else
   {
      action = "deleterecommend"
   }
   
  
   YAHOO.util.Connect.asyncRequest('POST', 'postactions.php', {
       success:function(oResponse) {
       
        var anchor = document.getElementById("anchor_recommend_" + post_id)  ;
       if(isrecommend == 1)
      {
           anchor.innerHTML = global_mark_off ;
          if(spanid != null)
          {
            spanid.style.display = 'inline'   ;
          }
          
         if(spanidbox != null )
          {
            
            spanidbox.style.display = 'inline'   ;
         
           //change the spanidbox class name
           spanidbox.className = "markedbox"   + discussion;
         }
       }
        else
        {
        
           anchor.innerHTML = global_mark_on ;
          
         if(spanid != null)
         {
           spanid.style.display = 'none'   ;
            spanid.className = 'unmarked'   ;
         }
          if(spanidbox != null)
         {
              spanidbox.style.display = 'none'   ;
           spanidbox.className = 'unmarkedbox';
         }
       }
       //mark the discussion row, if needed
        var response =  oResponse.responseText  ;
      
       //make sure response text is a number and  not an error message
       if (isNaN(response) )
       {
          alert(response)  ;
       }
       
        var recommandelement = document.getElementById('recommand' + discussion );
      
      if(response == 0 )
      {
         //remove the flag image
          recommandelement.innerHTML = "";
      }
      else
      {
        //add the flag image
         
          recommandelement.innerHTML =
           '<img  src="' + thewwwroot +  "/mod/extendedforum/pix/hamlaza.gif"   alt = "' + global_mark_on +  '" />';
                     
       }                  
       
    },//end success
  failure:function(oResponse) {
                alert("error: " + oResponse.statusText);
            }},
            'p='+post_id+'&ajax=1&action=' + action + '&d=' + discussion);  
 
 }
 function recommend (post_id, withajax, discussion)
 {
      //check if the recommand image is displayed
       var anchor = document.getElementById("anchor_recommend_" + post_id)  ;
      
      //from the anchor html we will know if we recommand this post or not
      isrecommend = 0;
      
      if ( anchor.innerHTML == global_mark_on)
      {
         isrecommend = 1;
      } 
     if(withajax == 1)
     {
       recommand_with_ajax(post_id, isrecommend, discussion)
     }
     else
     {
       //not supported
     }
 
 }
 function printObject(url)
 {
 
  if(url!= '')
  {
   var newWind = window.open(url,"" ,"WIDTH=700 ,HEIGHT =450,scrollbars=yes ,directories=yes,status=yes,toolbar=yes,titlebar=yes, menubar =yes,resizable=yes ");
		newWind.moveTo(10,5);
		}
 
 }
 
 function changestyle(elementId, styletodo)
 {
      
      var element = document.getElementById(elementId);
     
      if(element != null)
      {
             element.style.display = styletodo ;
       }
 
 }
 
 
 function submitmoveform(elementId, hiddenId, formid)
 {
    actionElement = document.getElementById(elementId)    ;
    var name =   actionElement.name;
    
    hiddenAction = document.getElementById(hiddenId)     ;
    hiddenAction.value = name;
    
     var formelement = document.getElementById(formid) ;
      
      //validate if the user selected to move a post
      //in same extendedforum that a destination post is selected
      
      var move_type_radio = formelement.elements["extendedforumordis"];
      
      //check if we have radio button selection
       //in a single discussion extendedforum we do not have radio button selection
     if(move_type_radio) 
     {
       for(var i = 0; i < move_type_radio.length; i++)
       {
         if(move_type_radio[i].checked) {
           if (move_type_radio[i].value == 0 ) { //move to a discussion
             //make sure a discussion is checked
             var post_radio = formelement.elements["postradio"];
             var checkcount = 0;
             if(post_radio.length) //for multiple discussions
             {
               for (var j=0; j< post_radio.length ; j++)
               {
                 if(post_radio[j].checked)
                 {
                    checkcount++;
                 }
              }
             }
             else
             {
               if   (post_radio.checked)
               {
                  checkcount++;
               }
             }
             if (checkcount == 0 )
             {
                  
                  alert(global_movemessage_error);
                  return false;
             }
           }
         }
      
      }
      }
      
      formelement.submit();

    
 }
 
 function cancelform(formid)
 {
   var formelement = document.getElementById(formid) ;
      changestyle('divdiscussionlist', 'none') ;
      changestyle('divextendedforumlist', 'none') ;
      formelement.reset();
 
 }
 
