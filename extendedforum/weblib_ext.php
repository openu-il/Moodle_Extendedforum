<?php
if(!defined("EDITING_TEACHER"))
     define("EDITING_TEACHER", 12);
if(!defined("TEACHER"))
    define ("TEACHER", 17)    ;
if(!defined("SHOHAM"))
    define ("SHOHAM", 42)    ;

/**
 * Prints a single paging bar to provide access to other pages  (usually in a search)
 *
 * @param int $totalcount Thetotal number of entries available to be paged through
 * @param int $page The page you are currently viewing
 * @param int $perpage The number of entries that should be shown per page
 * @param mixed $baseurl If this  is a string then it is the url which will be appended with $pagevar, an equals sign and the page number.
 *                          If this is a moodle_url object then the pagevar param will be replaced by the page no, for each page.
 * @param string $pagevar This is the variable name that you use for the page number in your code (ie. 'tablepage', 'blogpage', etc)
 * @param bool $nocurr do not display the current page as a link
 * @param bool $return whether to return an output string or echo now
 * @return bool or string
 */
function extendedforum_print_formated_page_bar($totalcount, $page, $perpage, $baseurl, $pagevar='page',$nocurr=false, $return=false) {
   $maxdisplay = 7;
    $output = '';
    $left_span = 'last_paging_span' ;
   
    $lastpage = ceil($totalcount / $perpage);
   if ($totalcount > $perpage) {
      //  $output .= '<div class="paging">';
      $titlepage = $page + 1 ;
        $output .= '<td class="pagebar pagebarbutton">' . get_string('page') . ' ' . $titlepage  .' ' . get_string('from', 'forum' ) . ' ' . $lastpage . '</td>';
 
        if ($page > 0) {
            $pagenum = $page - 1;
            if (!is_a($baseurl, 'moodle_url')){
                $output .= '<td class="pagebar"><a class="previous" href="'. $baseurl . $pagevar .'='. $pagenum .'">&lt;</a></td>';
            } else {
                $output .= '&nbsp;(<a class="previous" href="'. $baseurl->out(false, array($pagevar => $pagenum)).'">'. get_string('previous') .'</a>)&nbsp;';
            }
        }
        if ($perpage > 0) {
            $lastpage = ceil($totalcount / $perpage);
        } else {
            $lastpage = 1;
        }
        if ($page > 15) {
            $startpage = $page - 10;
            if (!is_a($baseurl, 'moodle_url')){
                $output .= '<td class="pagebar"><a href="'. $baseurl . $pagevar .'=0">1</a>&nbsp;...</td>';
            } else {
                $output .= '&nbsp;<a href="'. $baseurl->out(false, array($pagevar => 0)).'">1</a>&nbsp;...';
            }
        } else {
            $startpage = 0;
        }
        $currpage = $startpage;
        $displaycount = $displaypage = 0;
        while ($displaycount < $maxdisplay and $currpage < $lastpage) {
            $displaypage = $currpage+1;
            if ($page == $currpage && empty($nocurr)) {
                    
                $output .= '<td class="pagebar pagebarselected">'. $displaypage . '</td>';
            } else {
                if (!is_a($baseurl, 'moodle_url')){
                  
                    $output .= '<td class="pagebar"><a href="'. $baseurl . $pagevar .'='. $currpage .'">'. $displaypage .'</a></td>';
                } else {
                    $output .= '&nbsp;&nbsp;<a href="'. $baseurl->out(false, array($pagevar => $currpage)).'">'. $displaypage .'</a>';
                }

            }
            $displaycount++;
            $currpage++;
        }
        if ($currpage < $lastpage) {
            $selectedclass= '';
            $thispage = $page + 1;
            if($thispage > $maxdisplay)
            {
              $selectedclass="pagebarselected"      ;
            }
            
            $lastpageactual = $lastpage - 1;
            if (!is_a($baseurl, 'moodle_url')){
                $output .= '<td class="pagebar ' .  $selectedclass .' ">...<a href="'. $baseurl . $pagevar .'='. $lastpageactual .'">'. $lastpage .'</a></td>';
            } else {
                $output .= '&nbsp;...<a href="'. $baseurl->out(false, array($pagevar => $lastpageactual)).'">'. $lastpage .'</a>&nbsp;';
            }
        }
        $pagenum = $page + 1;
      // if ($pagenum != $displaypage) {
       if($pagenum < $lastpage)     {
            if (!is_a($baseurl, 'moodle_url')){
                $output .= '<td class="pagebar"><a href="'. $baseurl . $pagevar .'='. $pagenum .'">&nbsp;&gt;&nbsp;</a></td>';
            } else {
                $output .= '&nbsp;&nbsp;(<a class="next" href="'. $baseurl->out(false, array($pagevar => $pagenum)) .'">'. get_string('next') .'</a>)';
            }
        }
      //  $output .= '</div>';
  
    }

    if ($return) {
        return $output;
    }
     
    echo $output;
    return true;
   }
   
   /****
    *   given a rolename it returned the appropriate img
    *   or an empty img if it does not have an image    
    *
    */
               
   function extendedforum_get_teacher_img($role, $background = 'white')
   {
     global $CFG ;
     
     $img = '';
      if($role == EDITING_TEACHER)       
      {
       $text = get_string('merakez_published', 'forum')   ;
       $ext='.png'; //Shimon #1823
       $src = 'merakez_'  . $background  .$ext     ;
       if (!file_exists($CFG->wwwroot . '/mod/extendedforum/pix/' . $src)) {
       	  $ext='.jpg';
       }

       $img =    '&nbsp;<img border="0" width="21" height="12" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/' . $src . '" alt = "'. $text . '" />' ;
         
      }else if( $role == TEACHER ) {  
         $text = get_string('manche_published', 'forum')    ;
         $ext='.png'; //Shimon #1823
         $src =   'manche_'  . $background  . $ext      ;
         if (!file_exists($CFG->wwwroot . '/mod/extendedforum/pix/' . $src)) {
       	  $ext='.jpg';
         }

         $img =    '&nbsp;<img border="0" width="21" height="12" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/' . $src . '" alt = "'. $text . '" />' ;
      }
   
    
     return $img;
   }

/**
 * Print a nicely formatted table.
 *
 * @param array $table is an object with several properties.
 * <ul>
 *     <li>$table->head - An array of heading names.
 *     <li>$table->align - An array of column alignments
 *     <li>$table->size  - An array of column sizes
 *     <li>$table->wrap - An array of "nowrap"s or nothing
 *     <li>$table->data[] - An array of arrays containing the data.
 *     <li>$table->width  - A percentage of the page
 *     <li>$table->tablealign  - Align the whole table
 *     <li>$table->cellpadding  - Padding on each cell
 *     <li>$table->cellspacing  - Spacing between cells
 *     <li>$table->class - class attribute to put on the table
 *     <li>$table->id - id attribute to put on the table.
 *     <li>$table->rowclass[] - classes to add to particular rows.
 *     <li>$table->summary - Description of the contents for screen readers.
 * </ul>
 * @param bool $return whether to return an output string or echo now
 * @return boolean or $string
 * @todo Finish documenting this function
 */
   function extendedforum_print_ouil_table ($table, $return=false){
   
     $output = '';

    if (isset($table->align)) {
        foreach ($table->align as $key => $aa) {
            if ($aa) {
                $align[$key] = ' text-align:'. fix_align_rtl($aa) .';';  // Fix for RTL languages
            } else {
                $align[$key] = '';
            }
        }
    }
    if (isset($table->size)) {
        foreach ($table->size as $key => $ss) {
            if ($ss) {
                $size[$key] = ' width:'. $ss .';';
            } else {
                $size[$key] = '';
            }
        }
    }
    if (isset($table->wrap)) {
        foreach ($table->wrap as $key => $ww) {
            if ($ww) {
                $wrap[$key] = ' white-space:nowrap;';
            } else {
                $wrap[$key] = '';
            }
        }
    }

    if (empty($table->width)) {
        $table->width = '80%';
    }

    if (empty($table->tablealign)) {
        $table->tablealign = 'center';
    }

    if (!isset($table->cellpadding)) {
        $table->cellpadding = '5';
    }

    if (!isset($table->cellspacing)) {
        $table->cellspacing = '1';
    }

    if (empty($table->class)) {
        $table->class = 'generaltable';
    }

    $tableid = empty($table->id) ? '' : 'id="'.$table->id.'"';

    $output .= '<table width="'.$table->width.'" ';
    if (!empty($table->summary)) {
        $output .= " summary=\"$table->summary\"";
    }
    $output .= " cellpadding=\"$table->cellpadding\" cellspacing=\"$table->cellspacing\" class=\"$table->class boxalign$table->tablealign\" $tableid>\n";

    $countcols = 0;
    
    if (!empty($table->head)) {
        $countcols = count($table->head);
        $output .= '<tr>';
        $keys=array_keys($table->head);
        $lastkey = end($keys);
        foreach ($table->head as $key => $heading) {

            if (!isset($size[$key])) {
                $size[$key] = '';
            }
            if (!isset($align[$key])) {
                $align[$key] = '';
            }
            if ($key == $lastkey) {
                $extraclass = ' lastcol';
            } else {
                $extraclass = '';
            }

            $output .= '<th style="vertical-align:top;'. $align[$key].$size[$key] .';white-space:nowrap;" class=" opuiltabletitle c'.$key.$extraclass.'" scope="col">'. $heading .'</th>';
        }
        $output .= '</tr>'."\n";
    }

    if (!empty($table->data)) {
        $oddeven = 1;
        $keys=array_keys($table->data);
        $lastrowkey = end($keys);
        foreach ($table->data as $key => $row) {
            $oddeven = $oddeven ? 0 : 1;
            if (!isset($table->rowclass[$key])) {
                $table->rowclass[$key] = '';
            }
            if ($key == $lastrowkey) {
                $table->rowclass[$key] .= ' lastrow';
            }
            $output .= '<tr class="r'.$oddeven.' '.$table->rowclass[$key].'">'."\n";
            if ($row == 'hr' and $countcols) {
                $output .= '<td colspan="'. $countcols .'"><div class="tabledivider"></div></td>';
            } else {  /// it's a normal row of data
                $keys2=array_keys($row);
                $lastkey = end($keys2);
                foreach ($row as $key => $item) {
                    if (!isset($size[$key])) {
                        $size[$key] = '';
                    }
                    if (!isset($align[$key])) {
                        $align[$key] = '';
                    }
                    if (!isset($wrap[$key])) {
                        $wrap[$key] = '';
                    }
                    if ($key == $lastkey) {
                      $extraclass = ' lastcol';
                    } else {
                      $extraclass = '';
                    }
                    $output .= '<td style="'. $align[$key].$size[$key].$wrap[$key] .'" class="openutable c'.$key.$extraclass.'">'. $item .'</td>';
                }
            }
            $output .= '</tr>'."\n";
        }
    }
    $output .= '</table>'."\n";

    if ($return) {
        return $output;
    }

    echo $output;
    return true;
   }

/**
 *  @param int userid
 *  @param int courseid
 *
 *  return the most important role of a user in a course
 *
 **/
 function extendedforum_get_user_main_role($userid, $courseid)
 {
 global $CFG;
 $sql = "SELECT r.name AS role
            FROM {$CFG->prefix}role_assignments ra, {$CFG->prefix}context c, {$CFG->prefix}role r
            WHERE ra.userid = $userid
           AND ra.contextid = c.id
           AND c.instanceid
           IN ( $courseid, 0 )
           AND c.contextlevel IN ( 50, 10 )
           AND ra.roleid = r.id
           ORDER BY c.contextlevel DESC, r.sortorder ASC
          LIMIT 1  ";

      if ($rows = get_records_sql($sql) )
           {
              foreach ($rows as $s) {
                    $role = $s->role;
                   }

              return $role;
           }
           else
           {
              return "";
           }
 }
?>
