<?php


?>

 
 /* view discussion */
 .generalbox {
  border-width:2px;
  border-style:solid;
  margin-bottom: 15px;
  padding:10px;
}
  .paging{
	/*  float: right;  */ /* yifatsh 2/2011  remark it cause problem with application paging*/
    margin-right: 5px;
    
  }

  /*Shimon #1823*/
 .posting{
	padding-top: 10px;
  }
  
  
  .extendedforumaddnew{
    float: right;
    margin-right: 5px;
    
  
  }
  
  div.editcommand{
     padding-top: 5px;
  }

  .discussion{
     font-size:0.9em;
  }
  .subtitle{
     font-size:0.75em;
     

  }
  div.username{
    padding-bottom:5px;
    padding-right:5px;
    padding-left:5px;
  }
  form.sameline{
     display:inline;
  
  }
  
  .subject{
   //padding-left:10px;
  // padding-right:10px;
  margin-left: auto ;
  margin-right: auto;
  }
  tr.postrow{
   //display:none ;
    font-size:0.9em;
  }
 img.userpicture {
  width:50px;
  height:50px;
  margin-left:4px;
} 
#mod-extendedforum-view .subscription{
    margin-left:10px;
    margin-right:10px;

}
.generalbox#intro {

/*  yifat remove 
margin-left:10px;
 margin-right:10px;
  padding-bottom:15px;*/
}
 .formheader{
   border-spacing: 0px;
   width:700px;
   margin-bottom:2px;
   margin-right:10px;
   margin-left:10px;
 }
 .extendedforumtable{
     border-spacing: 0px;
     width:700px;
     max-width:1000px;
     margin-right:10px;
     margin-left:10px;
	 margin-top: 4px;
 
 }
 
 td.extendedforummode{
  text-align: left;
 }
 .actionbutton{
   vertical-align:top;
   width:2%;
 }
 .tdsubject{
 
   width:60%;
   vertical-align:top;

 }
 
 
 .tdauthorname{
   width:15%;
   vertical-align:top;

 }
 
 .tddate{
   vertical-align:top;
    width:20%;
 }
 
 div.posts{
       display:none;
       margin-right:10px;
       margin-left:10px;
	   margin-top: -4px; /*Shimon #1823*/
       font-size:0.9em;
       max-width: 700px; /*Shimon #1823*/
 }
 
 div.postView{
	display:block;
	margin-right:10px;
    margin-left:10px;
	max-width:700px;
 }
 
 div.extendedforummessage{
   max-width:700px !important ;
   
   
 
 }
 div.threadhideme{
   display:none;
 
 }
 td.formumcontrols{
    text-wrap: none;
 
 }
 img.printimage{
  border:0px;
 
 }
 img.imageaction{
     cursor:pointer ;
 }
 
span.marked{
   display: inline;
}
span.unmarked{
     display: none  ;
}

fieldset.hiddenclass{
  display: none  ;

}

div.hiddenclass{
  display: none  ;

}
hr {
  border: none;
  background-color: #B4CBDF;
  color: #B4CBDF;
  height: 1px;
}


 .secondpagebar{
  margin-top:3px;
 }
table.pagebar
{
border-collapse:collapse;
display:inline-block;

}
 td.pagebar
{
border: 1px solid black;
padding:3px;
font-weight:bold;
font-size:0.8em;
} 
td.afterpaging{
   padding-right:10px;
   padding-left:10px;
 
}

.pagebarbutton{
  font-weight:bold;
font-size:0.8em;
}
div.newextendedforummessage{
  float: left;
}
 .extendedforumpost {
  margin-top: 5px !important;
  min-width:500px;
  width:700px;
  border-top: 2px solid white;
}
td.extendedforumpicture {
   white-space: nowrap !important;

}
  td.extendedforumcommands{
   white-space: nowrap !important;
   text-align: right; /*Shimon #1924*/
  }
/***hide images in printing ***/
@media print
{

  DIV.headeContainer{display :none;}
  div.printimage{display:none;}
  
}


.extendedforumicons{

   font-size:0.8em;
}
  .iconmap {
    padding-right:3px;
    padding-left:3px;
  }
 
 .extendedforummap{
    font-weight:bold;
 } 



/* ********** indent patch ********** */

html body#mod-extendedforum-view.mod-extendedforum div.indent {
	margin-left:20px;
	margin-right:0px;
}

html body#mod-extendedforum-view.mod-extendedforum.dir-rtl div.indent {
	margin-left:0px;
	margin-right:20px;
}
