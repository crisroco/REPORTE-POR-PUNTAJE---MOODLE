<?php

global $DB, $PAGE, $OUTPUT;

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir . '/formslib.php');
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

  
//Porcentaje de cincronizacion de cursos padres e hijos

$prnt = "SELECT COUNT(sm.id) as cantidad_padres FROM {sync_main} sm";
$parent = $DB->get_records_sql($prnt);

$chld = "SELECT COUNT(rm.id) as cantidad_hijos FROM {sync_related} rm";
$child = $DB->get_records_sql($chld);

$snc = "SELECT * FROM {sync_user_history} suh
     ORDER BY suh.main_id ASC, suh.time_sync DESC ";
     
$sync = $DB->get_records_sql($snc);

$children = array();
$parents = array();
$temp = '';

foreach ($sync as $key => $value) {
   if ($temp == $value->main_id) {
    unset($sync[$key]);
   }
  $temp =  $value->main_id;
}

foreach ($sync as $key => $value) {
  array_push($parents, $value->main_id);

  $listchl = explode(',', $value->child_id);
  foreach ($listchl as $keys => $values) {
    if ($values == '') {
      continue;
    }
    array_push($children, $values);
  }
}
$hijos = key($child);
$padres = key($parent);
$child =  (count($children)/$hijos)*100;
$parent = (count($parents)/$padres)*100;
//FIN Porcentaje de cincronizacion de cursos padres e hijos

  print html_writer::empty_tag('br');

  $percent=60;
  $table2 = new html_table();
  $table2->head = array('','Total','Porcentaje de sincronización');
  $table2->data[] = array('Cursos padres',$padres, generate_progressbar(round($parent,2)));
  $table2->data[] = array('Cursos hijos', $hijos,generate_progressbar(round($child,2)));

  $table = new html_table();  
  $table->head = array('Curso Padre','');
  $courses = $DB->get_records_menu('course',array(),null,'id,fullname');  

  $records = $DB->get_records('sync_main');

  //combo de cusrsos padres

  $out = '<select onchange="window.location=this.options[this.selectedIndex].value" onmousedown="if(  this.options.length>8){this.size=10;}" onblur="this.size=0;" class="select2">    
    <option value="">Selecione curso padre</option>
    <option value="'.$main_url.'">Todos los cursos</option>';

 

  foreach ($records as $key => $value) {

    $out .=  '<option value="http://moodle.dev/blocks/sync/report.php?id='.$value->courseid.'">'.$courses[$value->courseid].'</option>';
  }

  $out .= '</select>';
  //FIN combo de cusrsos padres

 

  if (!isset($_GET['id']) || $_GET['id'] == '') {
      foreach($records as $r){
        $line = array();
        $line[] = $courses[$r->courseid];

        $links = '';
        $url = new moodle_url('/blocks/sync/dashboard.php',array('id'=>$r->id, 'courseid' => $r->courseid));
        $text = 'Explorar'; //Translate this
        $links .= html_writer::link($url,$text,array('class'=>'btn btn-default'));


        $line[] = $links;
        $table->data[] = $line;
       
    }
    
  }else{
    foreach($records as $r){
      if ($r->courseid == $_GET['id']) {
        $id = $r->id;
      }else{
        continue;
      }
    
      $line = array();
      $line[] = $courses[$_GET['id']];
      $links = '';
      $url = new moodle_url('/blocks/sync/dashboard.php',array('id'=>$id, 'courseid' => $_GET['id']));
      $text = 'Explorar'; //Translate this
      $links .= html_writer::link($url,$text,array('class'=>'btn btn-default'));


      $line[] = $links;
      $table->data[] = $line;
      break;
    }

  }


  print html_writer::tag('link','',array('href'=>$CFG->wwwroot.'/blocks/sync/assets/css/select2.css','rel'=>'stylesheet'));
   $PAGE->requires->js_call_amd('block_sync/module', 'init');
  

  echo html_writer::table($table2);
  //echo html_writer::select($course, 'choosenumber',array('class'=>'select2'));
  echo $out;
  print html_writer::empty_tag('br');
  print html_writer::empty_tag('br');
  echo html_writer::table($table);



print $OUTPUT->footer();
