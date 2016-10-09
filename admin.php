<?php
global $DB, $PAGE, $OUTPUT;

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
include('forms.php');
include('lib.php');
// Input params

admin_externalpage_setup('blocksync');

$context = context_system::instance();

require_login();

require_capability('block/sync:config',$context);

$main_url = new moodle_url('/blocks/sync/admin.php');

$PAGE->set_context($context);
$PAGE->set_url($main_url);
$title = 'Listado de Relaciones';
$PAGE->set_title($title);
$PAGE->set_heading($title);
print $OUTPUT->header();

  $url = new moodle_url('/blocks/sync/create_main.php');
  $text = 'Nueva Relación'; //Translate this
  print html_writer::link($url,$text,array('class'=>'btn btn-default'));
  print html_writer::empty_tag('br');

  $table = new html_table();
  $table->head = array('Curso Padre','Cursos Hijos','');
  $courses = $DB->get_records_menu('course',array(),null,'id,fullname');  

  $records = $DB->get_records('sync_main');
  $table->data = array();

  foreach($records as $r){
    $line = array();
    $line[] = $courses[$r->courseid];

    $childs =  $DB->get_records('sync_related',array('main_id'=>$r->id));

    $l = array();
    foreach($childs as $c){
      $l[] = html_writer::tag('p',$courses[$c->courseid]);
    }

    $line[] = implode('', $l);


    $links = '';
    $url = new moodle_url('/blocks/sync/edit_main.php',array('id'=>$r->id));
    $text = 'Editar'; //Translate this
    $links .= html_writer::link($url,$text,array('class'=>'btn btn-default'));

    $url = new moodle_url('/blocks/sync/delete_main.php',array('id'=>$r->id));
    $text = 'Eliminar'; //Translate this
    $links .= html_writer::link($url,$text,array('class'=>'btn btn-default'));

    $url = new moodle_url('/blocks/sync/clear_main.php',array('id'=>$r->id));
    $text = 'Limpiar Hijos'; //Translate this
    $links .= html_writer::link($url,$text,array('class'=>'btn btn-default'));

    $line[] = $links;
    $table->data[] = $line;
  }

  

  echo html_writer::table($table);

print $OUTPUT->footer();

