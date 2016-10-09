<?php

class block_sync extends block_base {

  function init() {
    $this->title = get_string('pluginname', 'block_sync');
  }

  function has_config() {
      return true;
  }

  function get_content() {
    global $OUTPUT, $USER , $DB , $COURSE;
      
    $courseid = $this->page->course->id;

    if ($this->content !== null) {
      return $this->content;
    }

    if (empty($this->instance)) {
      $this->content = '';
      return $this->content;
    }

    $this->content = new stdClass();
    $this->content->text = ''; //translate this

    $cContext = context_system::instance(); 
    /* $c = get_user_roles($cContext,$USER->id);
     print_r($c);
      foreach ($c as $k)
       {
       echo($k->shortname);

        
    }
    exit;
   */

    $admins = get_admins();
    
$coursecontext = context_course::instance($courseid);
    $courses = $DB->get_records('sync_main',array(),null,'courseid,id');
    

    $main = $DB->get_records('sync_main');

   
    $childs = $DB->get_records('sync_related',array(),null,'courseid,main_id');
    
    
    if(in_array($courseid,array_keys($courses))){
      $c = get_user_roles($coursecontext,$USER->id);
      $roles = array();
      foreach ($c as $k){
        $roles[] = $k->shortname;
      }
       if(in_array('coordinador',$roles) || in_array($USER->id,array_keys($admins))){
        
           $id = $DB->get_record('sync_main',array('courseid' => $courseid));
          
          //exit;
           $chijos = $DB->get_records('sync_related',array('main_id' => $id->id));
          
           //print_r($chijos);
           $this->content->text = '<div>Se encuentra en un curso Padre</div>'.'<div>A continuación se muestra sus cursos hijos:</div>';

           $url = new moodle_url('/blocks/sync/sync.php', array('id' =>$courseid));
          //$url1 = new moodle_url('', );
           $text = 'Actualizar Cursos Hijos'; //Translate this
           $this->content->text .= html_writer::link($url,$text,array('class'=>'btn btn-default'));
           $text = 'Mostrar Cursos Hijos';
           //$this->content->text .= html_writer::link($url1,$text,array('class'=>'btn btn-default'));
          
          $this->content->text .= self::generate_curse($chijos);
          
       }
      
    }elseif(in_array($courseid,array_keys($childs))){
      $this->content->text = '<div>Este es un curso hijo</div>';
    }else{

    }
  }

  function applicable_formats() {
      return array('course' => true);
  }


function generate_curse($data) {
    global $USER, $OUTPUT , $DB;
    $i = 1;
    if (empty($data)) {
        return 'No tiene cursos Hijos';
    }

    $table = new html_table();
    
    $table->head = array(
                        'Nro',
                        'Nombre Curso',
                        'Nombre Corto'
                    );
 
    foreach ($data as $k) {
       
       
      $courseh = $DB->record_exists("course", array("id" => $k->courseid));
      if($courseh){
        $courseh = $DB->get_record("course", array("id" => $k->courseid));
        $row = new html_table_row();


        $row->cells = array(
                        $i,
                        $courseh->fullname,
                        $courseh->shortname
                    );
        $table->data[] = $row;
        $i = $i +1;
    }else
      $table->data[] = '';
  }

    return html_writer::table($table);
}
}



?>
