<?php  //$Id: settings.php,v 1.1.2.5 2009/01/17 19:30:08 stronk7 Exp $

require_once($CFG->dirroot.'/mod/extendedforum/lib.php');

$settings->add(new admin_setting_configselect('extendedforum_displaymode', get_string('displaymode', 'extendedforum'),
                   get_string('configdisplaymode', 'extendedforum'), EXTENDEDFORUM_MODE_ONLY_DISCUSSION, extendedforum_get_layout_modes()));

$settings->add(new admin_setting_configcheckbox('extendedforum_replytouser', get_string('replytouser', 'extendedforum'),
                   get_string('configreplytouser', 'extendedforum'), 1));

// Less non-HTML characters than this is short
$settings->add(new admin_setting_configtext('extendedforum_shortpost', get_string('shortpost', 'extendedforum'),
                   get_string('configshortpost', 'extendedforum'), 300, PARAM_INT));

// More non-HTML characters than this is long
$settings->add(new admin_setting_configtext('extendedforum_longpost', get_string('longpost', 'extendedforum'),
                   get_string('configlongpost', 'extendedforum'), 600, PARAM_INT));

// Number of discussions on a page
$settings->add(new admin_setting_configtext('extendedforum_manydiscussions', get_string('manydiscussions', 'extendedforum'),
                   get_string('configmanydiscussions', 'extendedforum'), 15, PARAM_INT));

 //Number of attachments per post
 $options = array();
 for ($i=1; $i<=20; $i++) {
     $options[$i] = $i;
 }
 
 $settings->add(new admin_setting_configselect('extendedforum_maxattachments', get_string('maxattachments', 'extendedforum'),
                    get_string('configmaxattachments', 'extendedforum'), 4, $options));
 
$settings->add(new admin_setting_configselect('extendedforum_maxbytes', get_string('maxattachmentsize', 'extendedforum'),
                   get_string('configmaxbytes', 'extendedforum'), 512000, get_max_upload_sizes($CFG->maxbytes)));

// Default whether user needs to mark a post as read
$settings->add(new admin_setting_configcheckbox('extendedforum_trackreadposts', get_string('trackextendedforum', 'extendedforum'),
                   get_string('configtrackreadposts', 'extendedforum'), 1));

// Default number of days that a post is considered old
$settings->add(new admin_setting_configtext('extendedforum_oldpostdays', get_string('oldpostdays', 'extendedforum'),
                   get_string('configoldpostdays', 'extendedforum'), 14, PARAM_INT));


// Default whether user needs to mark a post as read
$settings->add(new admin_setting_configcheckbox('extendedforum_usermarksread', get_string('usermarksread', 'extendedforum'),
                  get_string('configusermarksread', 'extendedforum'), 0));

// Default time (hour) to execute 'clean_read_records' cron
$options = array();
for ($i=0; $i<24; $i++) {
    $options[$i] = $i;
}
$settings->add(new admin_setting_configselect('extendedforum_cleanreadtime', get_string('cleanreadtime', 'extendedforum'),
                   get_string('configcleanreadtime', 'extendedforum'), 2, $options));


if (empty($CFG->enablerssfeeds)) {
    $options = array(0 => get_string('rssglobaldisabled', 'admin'));
    $str = get_string('configenablerssfeeds', 'extendedforum').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

} else {
    $options = array(0=>get_string('no'), 1=>get_string('yes'));
    $str = get_string('configenablerssfeeds', 'extendedforum');
}
$settings->add(new admin_setting_configselect('extendedforum_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                   $str, 0, $options));

$settings->add(new admin_setting_configcheckbox('extendedforum_enabletimedposts', get_string('timedposts', 'extendedforum'),
                   get_string('configenabletimedposts', 'extendedforum'), 0));

$settings->add(new admin_setting_configcheckbox('extendedforum_logblocked', get_string('logblocked', 'extendedforum'),
                   get_string('configlogblocked', 'extendedforum'), 1));

$settings->add(new admin_setting_configcheckbox('extendedforum_ajaxrating', get_string('ajaxrating', 'extendedforum'),
                   get_string('configajaxrating', 'extendedforum'), 0));

?>
