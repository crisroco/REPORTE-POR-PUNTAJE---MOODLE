<?php
global $DB, $PAGE, $OUTPUT;

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
include('forms.php');
include('lib.php');

$id = required_param('id', PARAM_INT);

admin_externalpage_setup('blocksync');

$context = context_system::instance();
require_login();
require_capability('block/sync:config',$context);

$main_url = new moodle_url('/blocks/sync/delete_main.php',array('id'=>$id));


$mform = new sync_delete_main($main_url,array('id'=>$id));

//Form processing and displaying is done here
if ($mform->is_cancelled()) {
  $returnurl = new moodle_url('/blocks/sync/admin.php');
  redirect($returnurl);
} else if ($data = $mform->get_data()) {
  $DB->delete_records('sync_main', array('id'=>$id));  
  $DB->delete_records('sync_related', array('main_id'=>$id));  
  $returnurl = new moodle_url('/blocks/sync/admin.php');
  redirect($returnurl);
}


$PAGE->set_url($main_url);
$title = 'Eliminar Relación';
$PAGE->set_title($title);
print $OUTPUT->header();
    $mform->display();
print $OUTPUT->footer();

