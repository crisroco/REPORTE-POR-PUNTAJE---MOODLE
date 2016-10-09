<?php

global $DB, $PAGE, $OUTPUT;

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
include('lib.php');
// Input params

admin_externalpage_setup('dashblocksync');

$context = context_system::instance();

require_login();

require_capability('block/sync:config',$context);

$main_url = new moodle_url('/blocks/sync/report.php');

$PAGE->set_context($context);
$PAGE->set_url($main_url);
$title = get_string('adminname','block_sync');
$PAGE->set_title($title);
$PAGE->set_heading($title);
print $OUTPUT->header();


  print html_writer::empty_tag('br');

  $table = new html_table();
  $table->head = array('Curso Padre','');
  $courses = $DB->get_records_menu('course',array(),null,'id,fullname');  

  $records = $DB->get_records('sync_main');
  $table->data = array();

  foreach($records as $r){
    $line = array();
    $line[] = $courses[$r->courseid];

    /*$childs =  $DB->get_records('sync_related',array('main_id'=>$r->id));

    $l = array();
    foreach($childs as $c){
      $l[] = html_writer::tag('p',$courses[$c->courseid]);
    }

    $line[] = implode('', $l);*/


    $links = '';
    $url = new moodle_url('/blocks/sync/dashboard.php',array('id'=>$r->id, 'courseid' => $r->courseid));
    $text = 'Explorar'; //Translate this
    $links .= html_writer::link($url,$text,array('class'=>'btn btn-default'));


    $line[] = $links;
    $table->data[] = $line;
  }

  

  echo html_writer::table($table);



print $OUTPUT->footer();