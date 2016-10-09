<?php
global $DB, $PAGE, $OUTPUT,$CFG,$USER;

require_once("../../config.php");
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/course/modlib.php');
include('forms.php');
include('lib.php');
// Input params
$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record("course", array("id" => $courseid), '*', MUST_EXIST);
require_course_login($course);

$context = context_course::instance($course->id);
//require_capability('mod/evaluation:viewallassessments', $context);

$main_url = new moodle_url('/blocks/sync/sync.php', array('id' => $courseid));

$mform = new sync_sync($main_url,array('id'=>$courseid));

$sync = $DB->get_record('sync_main',array('courseid'=>$courseid));
$childs = $DB->get_records('sync_related',array('main_id'=>$sync->id));
$courses = $DB->get_records_menu('course',array(),null,'id,fullname');
sync_main_modules($sync->courseid,$sync->id);
$main_modules = $DB->get_records('sync_modules',array("main_id"=>$sync->id));
$copy_course = $main_course = $DB->get_record('course',array('id'=>$sync->courseid));
$main_sections = sync_get_sections($sync->courseid);
unset($copy_course->id); //LIMPIA CURSO COPIA
unset($copy_course->category);
unset($copy_course->sortorder);
unset($copy_course->fullname);
unset($copy_course->shortname);
unset($copy_course->idnumber);


//Form processing and displaying is done here
if ($mform->is_cancelled()) {
  $returnurl = new moodle_url('/course/view.php', array('id'=>$courseid));
  redirect($returnurl);
}
$PAGE->set_url($main_url);
$title = 'Sincronizar Cursos';
$PAGE->set_title($title);


$output = '';

$data = $mform->get_data();

$dataobject = new stdClass();
    $dataobject->user_id = $USER->id;
    $dataobject->main_id = $sync->courseid;
    //$dataobject->child_id = $c->courseid;
    

    $childs_print = '';

  foreach($childs as $c) { //BARRIDO HIJOS

    
    $childs_print .=$c->courseid . ','; 

    $course = $DB->get_record('course',array('id'=>$c->courseid));//HIJO ACTUAL
    $output .= html_writer::tag('h3',$courses[$c->courseid]);
    $changed = false;

    if($data){

      if($data->sections == 1){

        //Update format of course//COPIA FORMATO DEL 
        $copy_course->id = $course->id;
        $copy_course->category = $course->category;
        $copy_course->sortorder = $course->sortorder;
        $copy_course->fullname = $course->fullname;
        $copy_course->shortname = $course->shortname;
        $copy_course->idnumber = $course->idnumber;

       

        $DB->update_record('course',$copy_course);
        //ojo
        $fullname = $copy_course->fullname;
        $shortname = $copy_course->shortname;
        $categoryid = $copy_course->category;
        $visibility = 1;
        $enrolmentcopy = 0;
        $newcourseid = $copy_course->id;//curso hijo
        course_template_duplicate_course($newcourseid,$courseid, $fullname, $shortname, $categoryid, (int)$visibility, (int)$enrolmentcopy);
   
        //ojo
        $child_sections = sync_get_sections($c->courseid);

        
           }//fin de if data = section ==> 1 (primer boton de formato)
    } //fin de data enviada del formulario (dos botones)


    foreach($main_modules as $m){
      if($object = sync_check_status($m,$c->courseid)){
        if ($data) {
          switch ($object->type) {
            case 1:
              sync_create_module($object,$m,$c->courseid);
              break;
            case 2:
             //echo 'hol2';
              sync_update_module($object,$m,$c->courseid);
              break;
            case 3:
            //echo 'elimina';
              if($data->delete == 1){
                sync_delete_module($object,$m,$c->courseid);
              }
              break;
            default:
              break;
          }

        }else{
          $output .= $object->message;
        }
        $changed = true;
      }
    }

    if($data){
      //POSITIONS
      if($data->position == 1){
        $child_sections = sync_get_sections($c->courseid);

        $sql = "SELECT m.module_id as main,c.module_id as course
                FROM {sync_modules} m
                INNER JOIN {sync_modules_course} c
                ON c.smodule_id = m.id
                WHERE c.course_id = ?
                ";

        $related_modules = $DB->get_records_sql_menu($sql, array($c->courseid));


        foreach($main_sections as $key => $ms){
          $order = explode(',',$ms->sequence);
          $corder =  explode(',',$child_sections[$key]->sequence);
          foreach($order as $a => $b){
            if(isset($related_modules[$b])){
              $order[$a] = $related_modules[$b];
              $pos = array_search('verde', $corder);
              if($pos !==FALSE){
                unset($corder[$pos]);
              }
            }else{
              unset($order[$a]);
            }
          }

          if(!empty($corder)){
            foreach ($corder as $co) {
              $order[] = $co;
            }
          }

          $order = implode(',', $order);

          $child_sections[$key]->sequence  = $order;
          $DB->update_record('course_sections',$child_sections[$key]);
        }

      }

      rebuild_course_cache($c->courseid);
    }

    if(!$changed){
      $output .= html_writer::tag('p','Sin cambios en ninguna actividad. Si oculto alguna sección recuerde dar click en la Opción Sobreescribir secciones y formato');
    }

    $output .= html_writer::empty_tag('br');
  }





print $OUTPUT->header();
  if (!$data) {
    $mform->display();
  }else{

    $dataobject->child_id = $childs_print;
    $dataobject->time_sync = time();

    $DB->insert_record('sync_user_history',  $dataobject);

    $url = new moodle_url('/course/view.php', array('id' => $courseid));
    $text = 'Continuar'; //Translate this
    print html_writer::link($url,$text,array('class'=>'btn btn-default'));

  }


echo $output;


print $OUTPUT->footer();
