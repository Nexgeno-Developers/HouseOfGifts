<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_119 extends App_module_migration
{
	public function up()
	{    
		$CI = &get_instance();
		if(!$CI->db->field_exists('is_default',db_prefix() . 'si_lead_filter')) {
			$CI->db->query('ALTER TABLE `' . db_prefix() . 'si_lead_filter` 
							 ADD `is_default` int(11) NOT NULL DEFAULT "0" AFTER `filter_parameters`');
		}
	}
}