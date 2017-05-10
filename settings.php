<?php
defined('MOODLE_INTERNAL') || die;
if($hassiteconfig && isset($ADMIN)){
    include_once 'lib/base.php';
    moo_moodle_reportpoints::set_adminsettings($ADMIN);
}