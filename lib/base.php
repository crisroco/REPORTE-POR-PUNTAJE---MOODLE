<?php
//define('INTERNAL_ACCESS',1);
/**
 *
 * @package    reportpoints
 * @subpackage lib
 * @copyright  2015 
 * @author     Jair Revilla
 * @version    1.0
 */
class moo_moodle_reportpoints
{
    protected $user;
    protected $config;
    protected $course;
    
    public function __construct(array $configs = null)
    {
        if ($configs !== null) {
            $this->set_configs($configs);
        }
    }
    public function set_configs(array $configs)
    {
        foreach ($configs as $name => $value) {
            if (property_exists($this, $name)) {
                $this->{$name} = $value;
            }
        }
        return $this;
    }
    
    /**
     * setup administrator links and settings
     *
     * @param object $admin
     */
    public static function set_adminsettings($admin)
    {
        $displaylist = coursecat::make_categories_list('moodle/course:create');
        $me = new self();
        $me->grab_moodle_globals();
        $context = context_course::instance(SITEID);
        $role = $me->db->get_records('role',  array(),'','id,shortname');
        foreach ($role as $key => $value) {
            unset($role[$key]->id);
            $role[$key] = $role[$key]->shortname;
        }
        $admin->add('reports', new admin_category('reportes', $me->get_string('pluginname')));
		$admin->add('reportes',
		      new admin_externalpage('reportpoints2',$me->get_string('reporte_participacion'),
		      $me->config->wwwroot . "/report/reportpoints/index.php",'report/reportpoints:view'));
        $admin->add('reportes',
              new admin_externalpage('reportpoints',$me->get_string('reporte_encuesta'),
              $me->config->wwwroot . "/report/reportpoints/reporte_feedback.php",'report/reportpoints:view'));
        $temp = new admin_settingpage('reportpointssettings', $me->get_string('settings'));
        $temp->add(new admin_setting_configselect('reportpointsroleid', $me->get_string('insertidrole'),
                       $me->get_string('insertidroledesc'), null, $role));
        $temp->add(new admin_setting_configtext('reportpointscantsem', $me->get_string('cantsem'),
                       $me->get_string('cantsemdesc'), 14, PARAM_INT));
        
        $admin->add('reportes',$temp);
    }
    
    
    public function grab_moodle_globals()
    {
        global $CFG, $USER, $COURSE,$DB;
        $this->user = $USER;
        $this->course = $COURSE;
        $this->config = $CFG;
        $this->db = $DB;
        return $this;
    }
    
    public function get_string($name, $a = null)
    {
        return stripslashes(get_string($name, 'report_reportpoints', $a));
    }
    
    public function get_config($name = null)
    {
        if ($name !== null && isset($this->config->{$name})) {
            return $this->config->{$name};
        }
        return $this->config;
    }
    
    public function get_string_fromcore($name, $a = null)
    {
        return get_string($name, '', $a);
    }
}