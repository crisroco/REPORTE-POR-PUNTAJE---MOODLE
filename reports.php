<?php
include_once 'Classes/PHPExcel.php';
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');
require_once($CFG->libdir.'/formslib.php');

global $DB, $CFG, $PAGE, $OUTPUT, $USER;



require_login();

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
      devuelve datos de las actividades(encuesta y tareas)
   */

      //cm.instance -> es el id del modulo
   $now = microtime(true);
   $actividades = array();

      foreach ($cursos as $key => $value) {
         $type_activity = "SELECT cm.instance, cm.module
                           from {course_modules} as cm
                           INNER JOIN {course_sections} as cs ON cm.section = cs.id
                           where cs.course = $value->course_id AND cs.section = $section_course AND (cm.module = 7 or cm.module = 1)";
         //solo debe haber una encuesta o una tarea por semana
         $tipo_actividad = $DB->get_record_sql($type_activity);
        
         /*echo "<pre> #########";
         print_r($tipo_actividad);
         echo "</pre>#########"; */
         
         if (is_object($tipo_actividad)) {
            $activ = $tipo_actividad->module;
         }
         //FLUJO DE FEEDBACK
         if ($activ == 7) {
            
             $sql_feedback = "SELECT fb.id,  fb.name, fb.course, cs.section as semana , cm.module 
                              from {feedback} as fb  
                              join {course_modules} as cm ON fb.id = cm.instance
                              join {course_sections} as cs ON cm.section = cs.id
                              where fb.course= $value->course_id AND fb.timeclose < $now AND cs.section = $section_course" ;

             $actividad = $DB->get_records_sql($sql_feedback);
             if ($actividad != array()) {
               foreach ($actividad as $llave => $valor) {
                  array_push($actividades, $valor);
               }
             }
         //FIN - FLUJO DE FEEDBACK
         //FLUJO DE TAREA 
         }elseif ($activ == 1) {
            $sql_assign = "SELECT ass.id,  ass.name, ass.course, cs.section as semana , cm.module 
                              from {assign} as ass  
                              join {course_modules} as cm ON ass.id = cm.instance
                              join {course_sections} as cs ON cm.section = cs.id
                              where ass.course= $value->course_id  AND cs.section = $section_course" ;

             $actividad = $DB->get_records_sql($sql_assign);
             if ($actividad != array()) {
               foreach ($actividad as $llave => $valor) {
                  array_push($actividades, $valor);
               }
             }
         //FIN - FLUJO DE TAREA 
         }
         
      }

         echo "<pre>";
         print_r($actividades);
         echo "</pre>";    

   /**
      calcular puntaje 
   */
      $all_data = array();
      foreach ($actividades as $key => $value) {
         if ($value->module == 7) {
            $puntaje = 0;
            $sql_feedback_cantidad = "SELECT count(fc.id) as cantidad_participante from {feedback_completed} as fc where fc.feedback = $value->id";
            $encuestas_cantidad = $DB->get_records_sql($sql_feedback_cantidad);
            foreach ($encuestas_cantidad as $llave => $valor) {        
              
               $puntaje = ($valor->cantidad_participante/$cursos[$value->course]->students)*100;
            }
            $fb_name = $value->name;

            $datos_feedback = new stdClass();
            $datos_feedback->activity_name = $fb_name;
            $datos_feedback->tipo = 'Encuesta';
            $datos_feedback->puntaje = $puntaje.'%';
            array_push($all_data, $datos_feedback);

         }elseif ($value->module == 1) {
            $puntaje = 100;
            $now = microtime(true);
            $sql_tarea_verification = "SELECT subfl.submission, ass.id, ass.name, ass.allowsubmissionsfromdate  
                                       from {assign} as ass
                                       join {assignsubmission_file} as subfl ON ass.id = subfl.assignment 
                                       where ass.id = $value->id";
            $tarea_verification = $DB->get_records_sql($sql_tarea_verification);

            while ( count($tarea_verification) > 1) {
               array_pop($tarea_verification);
            }

            foreach ($tarea_verification as $key => $value) {

               $time_upload = $value->allowsubmissionsfromdate;
               $one_day = $time_upload+86400;
               $two_day = $time_upload+86400;
               $three_day = $time_upload+86400;
               $four_day = $time_upload+86400;
               $five_day = $time_upload+86400;

               if ($time_upload < $now) {
                  $puntaje = 100;
               }elseif ($now == $one_day) {
                  $puntaje = 80;
               }elseif ($now == $one_day) {
                  $puntaje = 60;                  
               }elseif ($now == $one_day) {
                  $puntaje = 40;                  
               }elseif ($now == $one_day) {
                  $puntaje = 20;                  
               }elseif ($now == $one_day) {
                  $puntaje = 0;                  
               }   
            

               $ass_name = $value->name;

               $datos_tarea = new stdClass();
               $datos_tarea->activity_name = $ass_name;
               $datos_tarea->tipo = 'Tarea';
               $datos_tarea->puntaje = $puntaje;
               array_push($all_data, $datos_tarea);
            }
            
            //array_push($all_data, $tarea_verification);
            
         }
      }

      echo "<pre>";
      print_r($all_data);
      echo "</pre>";

      $fecha = date_create();


date_timestamp_set($fecha, 1491886800+86400+86400);
echo date_format($fecha, 'U = Y-m-d H:i:s') . "<br>";

date_timestamp_set($fecha, 1491886800);
echo date_format($fecha, 'U = Y-m-d H:i:s') . "\n";

 