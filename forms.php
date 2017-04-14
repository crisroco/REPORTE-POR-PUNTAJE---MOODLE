<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->libdir.'/formslib.php');

class reportpoints_form extends moodleform {

    public function definition() {
        global $DB;
        $mform = &$this->_form;

        //delete sub categories from array
        $categorias = $DB->get_records_menu('course_categories', array(),'id', 'id,name');
        $categorias2 = $DB->get_records_menu('course_categories', array(),'id', 'id,path');
        foreach ($categorias as $key => $value) {
           if (strlen($categorias2[$key]) >2) {
               unset($value);
           }
           //echo strlen($categorias2[$key]);
        }


        $semana = array('Seleccionar');
        $nsemana = 10;
        for ($i=1; $i <= $nsemana; $i++) { 
            $nomsemana  = 'Semana '. $i;
            array_push($semana, $nomsemana);
        }

        //$mform->addElement('select', 'categoria', get_string('course_category','report_reportpoints'), $categorias, array('class'=>'select2'));

        $mform->addElement('select', 'categoria', get_string('course_category','report_reportpoints'), $categorias);

        $mform->addElement('select', 'section_course', get_string('section_course','report_reportpoints'), $semana);

        $this->add_action_buttons(false, 'exportar');

    }

}