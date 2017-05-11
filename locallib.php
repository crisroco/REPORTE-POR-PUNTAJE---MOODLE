<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');
require_once($CFG->libdir.'/formslib.php');

global $DB, $CFG, $PAGE, $OUTPUT, $USER;

require_login();

//$categoria = required_param('categoria', PARAM_INT);
//$section_course = required_param('section_course', PARAM_INT);
 
function reporte_grafico($categoria,$section_course){
  global $DB;


   /**
    RETORNA LOS CURSOS DE LA CATEGORIA ELEGIDA Y CANTIDAD DE ALUMNOS MATRICULADOS
  */
    $role_config = $DB->get_record('config',  array('name' => 'reportpointsroleid'))->value;
   $sql_cursos = "SELECT course.id as Course_id,course.fullname AS course
   ,context.id AS context
   , COUNT(course.id) AS students
   ,category.name
   ,category.path
   FROM {role_assignments} AS asg
   JOIN {context} AS context ON asg.contextid = context.id AND context.contextlevel = 50
   JOIN {user} AS USER ON USER.id = asg.userid
   JOIN {course} AS course ON context.instanceid = course.id
   JOIN {course_categories} AS category ON course.category = category.id
   WHERE asg.roleid = " . $role_config . " 
   AND category.id =".$categoria."  
   GROUP BY course.id
   ORDER BY COUNT(course.id) DESC";

   $cursos = $DB->get_records_sql($sql_cursos);

   echo "<pre>";
   print_r($cursos);
   echo "</pre>";

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
         die();
      }
   }   

   
     // REPORTE DE CURSO
   
   $now = microtime(true);
   $actividades = array();

   $datos_all = array();
   foreach ($cursos as $key => $value) {  
        $nameCourse = $value->course;

      //RETORNA LAS ENCUESTAS VENCIDAS DE UNA SECCION Y CURSO ESPECIFICO 
      $sql_feedback = "SELECT fb.id,  fb.name, fb.course, cs.section as semana , cm.module, c.fullname as crname 
                        from {feedback} as fb  
                        join {course_modules} as cm ON fb.id = cm.instance
                        join {course_sections} as cs ON cm.section = cs.id
                        join {course} as c ON cm.course = c.id
                        where fb.course= $value->course_id AND fb.timeclose < $now AND cs.section = $section_course" ;

      $actividad = $DB->get_records_sql($sql_feedback);

   echo $sql_feedback . "<pre>";
   print_r($actividad);
   echo "</pre>";

      //PROCESAR CADA ENCUESTA
      
      //if ($actividad != array()) {       
      
        foreach ($actividad as $ke => $valu) {
           
           $mdid = $valu->id;     


           //RETORNA LOS USUARIOS QUE REALIZARON LA ENCUESTA Y RESPUESTAS QUE MARCARON 
           $sql = "SELECT fv.id, fc.userid, f.timemodified, u.firstname, c.fullname, u.lastname, u.username, fi.presentation, fv.value, fi.typ,f.name as encuesta, fi.name as pregunta, fi.id as pregunta_id
              FROM {user} u
              INNER JOIN {feedback_completed} fc ON fc.userid = u.id
              INNER JOIN {feedback} f ON f.id = fc.feedback
              INNER JOIN {course} c ON f.course = c.id 
              INNER JOIN {feedback_value} fv ON  fc.id = fv.completed
              INNER JOIN {feedback_item} fi ON  fi.id = fv.item
              WHERE f.id IN (?)                      
              ORDER BY fc.userid ASC";

              $preguta_usuario = $DB->get_records_sql($sql, array($mdid));
          



        }
      //}


      //HOJA 2 REPORTE
      //cantida de veces marcado
      $respuestas = array();
      foreach ($preguta_usuario as $ky => $val) {
         if ($val->typ != 'multichoice') {
            continue;
         }
         $nresp = count(explode('|',$val->presentation));
         array_push($respuestas, $val->value);
         
      }  

      $encuestas = array();
      $encuestas[] = $preguta_usuario;

      $dato = array();
      $dato2 = array();
      $nresptemp = array();

      //foreach ($preguta_usuario as $encuesta) {                     
             //veces marcada
              foreach ($preguta_usuario as $index => $encuestado) {
                if ($encuestado->typ != 'multichoice') {
                 continue;
                }

                if(!isset($dato[$encuestado->encuesta])){
                    $dato[$encuestado->encuesta] = array();
                    
                }
                
                if(!is_null($dato[$encuestado->encuesta])){
                    if(!isset($dato[$encuestado->encuesta][$encuestado->pregunta])){
                        $dato[$encuestado->encuesta][$encuestado->pregunta] = array();
                        

                        $numAns = explode('|', $encuestado->presentation);
                        foreach ($numAns as $key => $value) {
                            $dato[$encuestado->encuesta][$encuestado->pregunta][$key + 1] = 0;
                            
                        }
                    }
                    
                    if(!is_null($dato[$encuestado->encuesta][$encuestado->pregunta])){
                        $dato[$encuestado->encuesta][$encuestado->pregunta][$encuestado->value]++;
                        
                    }
                }
                $nResp = count(explode('|', $encuestado->presentation));
              }
        //}   
            //porcentaje

            foreach ($dato as $dt => $datos) {               
                foreach ($datos as $enc => $encu) {
                  foreach ($encu as $ans => $answer) {
                     $nresptemp[$dt][$enc]['cantidad'] += $answer;
                     
                  }
                      
                }   
             }

             foreach ($dato as $dt => $datos) {               
                foreach ($datos as $enc => $encu) {
                  foreach ($encu as $ans => $answer) {
                     $dato2[$dt][$enc][$ans] = round(($answer/$nresptemp[$dt][$enc]['cantidad'] )*100,2);
                     
                  }
                      
                }   
             }               

            if ($dato != array() || $dato2 != array()) {
                # code...
               $datos_all[$nameCourse] = array('dato1' => $dato, 'dato2' => $dato2);
             } 

      
      //FIN - cantida de veces marcado
     
       if ($actividad != array()) {
         //foreach ($actividad as $llave => $valor) {
            array_push($actividades, $actividad);
         //}
       }
  }

  //########################################################################
$datos_all2 = array();
$canti_temp = array();
foreach ($datos_all as $key => $cursos) {  
          
  foreach ($cursos as $dat => $enc) {

    foreach ($enc as $encu => $encuest) {      
      
      if ($dat == 'dato1') {      
                  
        foreach ($encuest as $preg => $pregunt) {
                    
          foreach ($pregunt as $alter => $alternat) {
            $datos_all2['global']['dato1'][$encu][$preg][$alter] += $alternat;
            $canti_temp['global']['dato1'][$encu][$preg] += $datos_all[$key]['dato1'][$encu][$preg][$alter];
            
          }
                      
        }

      }

        
    }
  }  
}

foreach ($datos_all as $key => $cursos) {  
          
  foreach ($cursos as $dat => $enc) {

    foreach ($enc as $encu => $encuest) {      
      
      if ($dat == 'dato2') {      
                  
        foreach ($encuest as $preg => $pregunt) {
                    
          foreach ($pregunt as $alter => $alternat) {
            $datos_all2['global']['dato2'][$encu][$preg][$alter] = round(($datos_all2['global']['dato1'][$encu][$preg][$alter]/$canti_temp['global']['dato1'][$encu][$preg])*100,2);
            
            
          }
                      
        }

      }

        
    }
  }  
}
//########################################################################

  if ($datos_all == array()) {
    return '<div class="alert alert-info">En esta Semana no se han registrado encuestas</div>';       
  }else{    

  echo "<script src='//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js'></script>";
  echo "<link rel='stylesheet' type='text/css' href='//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css'>";
  echo "<link rel='stylesheet' type='text/css' href='assets/style.css'>";
  $html = '';
  foreach ($datos_all2 as $key => $cursos) {
    $html .= '<div class="course">';
      $html .= '<h3 class="coursetitle">';
        $html .= 'REPORTE GLOBAL DE RESPUESTAS POR ENCUESTA';
      $html .= '</h3>';  
    foreach ($cursos as $dat => $enc) {

      foreach ($enc as $encu => $encuest) {      
        $html .= '<div class="encuesta">';
        if ($dat == 'dato1') {
          $html .= '<h4 class="encuestatitle">';
            $html .= $encu;
          $html .= '</h4>';
          foreach ($encuest as $preg => $pregunt) {
            $html .= '<div class="pregunta">';
              $html .='<p class="preguntatitle"/>'; 
                $html .= $preg;
            foreach ($pregunt as $alter => $alternat) {
              $percent = $cursos['dato2'][$encu][$preg][$alter];

                  $html .= '<div class="alternativa">';
                    $html .= '<div class="alttitle">';
                      $html .= 'Alternativa '.$alter;
                    $html .= '</div>';
                    $html .= '<div class="cantpercent">';
                      $html .= '<div class="altcant">';
                        $html .= '<p class="vecestitle">';
                        $html .= 'Veces marcado </p>';
                        $html .= $alternat;
                      $html .= '</div>';
                      $html .= '<div class="altpercent">';
                        $html .= '<p class="vecestitle">';
                        $html .= 'Porcentaje </p>';
                        $html .= '<div class="progress">
                          <div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar"
                          aria-valuenow="'.$percent.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$percent.'%">
                            '.$percent.'% Marcados
                          </div>
                        </div>';
                        //$html .= $percent;
                      $html .= '</div>';
                    $html .= '</div>';
                  $html .= '</div>';

            }
              
            $html .= '</div>';
          }

        }

        if ($dat == 'dato2') {
          foreach ($encuest as $preg => $pregunt) {

            foreach ($pregunt as $alter => $alternat) {

            }

          }
        }
        $html .= '</div>';

      }


    }
    $html .= '</div>';
  }    

  return $html;
  }   

}






