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

$main_url = new moodle_url('/blocks/sync/edit_main.php',array('id'=>$id));

$sync = $DB->get_record('sync_main',array('id'=>$id));

$mform = new sync_edit_main($main_url,array('courseid'=>$sync->courseid,'id'=>$id));

//Form processing and displaying is done here
if ($mform->is_cancelled()) {
  $returnurl = new moodle_url('/blocks/sync/admin.php');
  redirect($returnurl);
} else if ($data = $mform->get_data()) {


  $DB->delete_records('sync_related', array('main_id'=>$id));  


  if(is_array($data->courses)){
    foreach($data->courses as $c){
      $DB->insert_record('sync_related', array('courseid'=>$c,'main_id'=>$id));  
    }
  }

  $returnurl = new moodle_url('/blocks/sync/admin.php');
  redirect($returnurl);
}


$PAGE->set_url($main_url);
$title = 'Edición Curso Padre - Hijo';
$PAGE->set_title($title);
print $OUTPUT->header();
   //   print html_writer::tag('link','',array('href'=>$CFG->wwwroot.'/blocks/sync/assets/css/select2.min.css','rel'=>'stylesheet'));
    //print html_writer::tag('link','',array('href'=>$CFG->wwwroot.'/blocks/sync/assets/css/select2.css','rel'=>'stylesheet'));
    //$PAGE->requires->js_call_amd('block_sync/module', 'init');
    $mform->display();
    //print html_writer::tag('script','',array('src'=>$CFG->wwwroot.'/blocks/sync/assets/js/custom.js'));
   // print html_writer::tag('script','',array('src'=>$CFG->wwwroot.'/blocks/sync/assets/js/select2.min.js'));

print $OUTPUT->footer();

