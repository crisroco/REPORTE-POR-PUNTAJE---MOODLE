<?php // content="text/plain; charset=utf-8"

   require_once(dirname(__FILE__) . '/../../config.php');
   require_once($CFG->libdir.'/adminlib.php');
   require_once($CFG->libdir.'/modinfolib.php');
   require_once($CFG->libdir.'/formslib.php');

   global $DB, $CFG, $PAGE, $OUTPUT, $USER;

   require_login();

   $categoryid = required_param('categoryid', PARAM_INT);
   $section_course = required_param('section_course', PARAM_INT);

   $categoria = array();

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
         die();
      }
   } 
   $now = microtime(true);
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


      //PROCESAR CADA ENCUESTA

       
      
      foreach ($actividad as $ke => $valu) {
            
            $mdid = $valu->id;
            $title1 = $valu->crname . ' - Semana ' . $section_course;



         //RETORNA LOS USUARIOS QUE REALIZARON LA ENCUESTA Y RESPUESTAS QUE MARCARON 
         $sql = "SELECT fv.id, fc.userid, f.course as cursoID,f.timemodified, u.firstname, c.fullname, u.lastname, u.username, fi.presentation, fv.value, fi.typ,f.name as encuesta, fi.name as pregunta, fi.id as pregunta_id
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
      //echo json_encode($cursos);
      $encuestas = array();
      $encuestas[] = $preguta_usuario;

      $dato = array();
      $dato2 = array();

      //foreach ($preguta_usuario as $encuesta) {                     
             
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
                            $dato[$encuestado->encuesta][$encuestado->pregunta][$key + 1];
                            
                        }
                    }
                    
                    if(!is_null($dato[$encuestado->encuesta][$encuestado->pregunta])){
                        $numAns = explode('|', $encuestado->presentation);
                        $dato[$encuestado->encuesta][$encuestado->pregunta][$encuestado->value]++;
                        
                    }
                }
            }
      //}   
            /*echo "<pre>";
                    print_r($preguta_usuario);
                    echo "</pre>";*/
            foreach ($preguta_usuario as $index => $encuestado) {
                if ($encuestado->typ != 'multichoice') {
                 continue;
                }

                if(!isset($dato2[$encuestado->encuesta])){
                    $dato2[$encuestado->encuesta] = array();
                }
                
                if(!is_null($dato2[$encuestado->encuesta])){
                    if(!isset($dato2[$encuestado->encuesta][$encuestado->pregunta])){
                        $dato2[$encuestado->encuesta][$encuestado->pregunta] = array();

                        $numAns = explode('|', $encuestado->presentation);
                        foreach ($numAns as $key => $value) {
                            $dato2[$encuestado->encuesta][$encuestado->pregunta][$key + 1] = 0/count($numAns) *100;
                        }
                    }
                    
                    if(!is_null($dato2[$encuestado->encuesta][$encuestado->pregunta])){
                        $numAns = explode('|', $encuestado->presentation);
                        $dato2[$encuestado->encuesta][$encuestado->pregunta][$encuestado->value] = ($dato2[$encuestado->encuesta][$encuestado->pregunta][$encuestado->value]+1)/count($numAns) *100;
                    }
                }
            }

         $datos_all[$nameCourse] = array('dato1' => $dato, 'dato2' => $dato2);
     
  }


echo "<pre>";
                    print_r($datos_all);
                    echo "</pre>";