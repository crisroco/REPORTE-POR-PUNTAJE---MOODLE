<?php
include_once 'Classes/PHPExcel.php';
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');
require_once($CFG->libdir.'/formslib.php');

global $DB, $CFG, $PAGE, $OUTPUT, $USER;



require_login();

//$categoryid = $_GET['categoryid'];
//$section_course = $_GET['section_course'];
$categoryid = required_param('categoryid', PARAM_INT);
$section_course = required_param('section_course', PARAM_INT);




/**
   Retorna los cursos de la categoria elegida y cantidad de alumnos matriculados
*/

   $sql_cursos = "SELECT course.id as Course_id,course.fullname AS Course
   ,context.id AS Context
   , COUNT(course.id) AS Students
   ,category.name
   ,category.path
   FROM {role_assignments} AS asg
   JOIN {context} AS context ON asg.contextid = context.id AND context.contextlevel = 50
   JOIN {user} AS USER ON USER.id = asg.userid
   JOIN {course} AS course ON context.instanceid = course.id
   JOIN {course_categories} AS category ON course.category = category.id
   WHERE asg.roleid = 5 
   AND category.id =".$categoryid."  
   GROUP BY course.id
   ORDER BY COUNT(course.id) DESC";

   $cursos = $DB->get_records_sql($sql_cursos);

   if ($cursos == array()) {
      //echo 'Verifique la categoria seleccionda';
      die();
   }else{

      foreach ($cursos as $key => $value) {
         if (strlen($value->path)>2) {
            unset($cursos[$key]);
         }
      }
      if ($cursos == array()) {
         echo 'No existen cursos ';
      }
      //echo dirname(__FILE__) . '<br>';

      echo "<pre>";
      print_r($cursos);
      echo "</pre>";
   }   




/**
   devuelve las encuestas vencidas y de una semana especifica
*/


$now = microtime(true);
$feedback_id = array();

   foreach ($cursos as $key => $value) {
       $sql_feedback = "SELECT fb.id, fb.name, fb.course, cs.section as semana 
                        from {feedback} as fb  
                        join {course_modules} as cm ON fb.id = cm.instance
                        join {course_sections} as cs ON cm.section = cs.id
                        where fb.course= $value->course_id AND fb.timeclose < $now AND cs.section = $section_course" ;

       $encuestas = $DB->get_records_sql($sql_feedback);
       if ($encuestas != array()) {
         foreach ($encuestas as $llave => $valor) {
            array_push($feedback_id, $valor);
         }
       }
   }
      echo "<pre>";
      print_r($feedback_id);
      echo "</pre>";    

/**
   calcular puntaje 
*/
   $all_data = array();
   foreach ($feedback_id as $key => $value) {
      $puntaje = 0;
      $sql_feedback_cantidad = "SELECT count(fc.id) as cantidad_participante from {feedback_completed} as fc where fc.feedback = $value->id";
      $encuestas_cantidad = $DB->get_records_sql($sql_feedback_cantidad);
      foreach ($encuestas_cantidad as $llave => $valor) {        
        
         $puntaje = ($valor->cantidad_participante/$cursos[$value->course]->students)*100;
      }
      $fb_name = $value->name;

      $datos_feedback = new stdClass();
      $datos_feedback->activity_name = $fb_name;
      $datos_feedback->tipo = 'Feedback';
      $datos_feedback->puntaje = $puntaje;
      array_push($all_data, $datos_feedback);
   }

   echo "<pre>";
   print_r($all_data);
   echo "</pre>";

 