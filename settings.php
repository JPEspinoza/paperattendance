<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
*
*
* @package    local
* @subpackage paperattendance
* @copyright  2016 Jorge Cabané (jcabane@alumnos.uai.cl) 
* @copyright  2016 Hans Jeria (hansjeria@gmail.com) 					
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
//Belongs to plugin PaperAttendance

defined('MOODLE_INTERNAL') || die;
if ($hassiteconfig) {

	$settings = new admin_settingpage('local_paperattendance', 'PaperAttendance');

	$ADMIN->add('localplugins', $settings);
	/*
	 $settings->add(new admin_setting_configtext(
	 name for $CFG - example $CFG->appname,
	 Text for field,
	 Description text,
	 Default value,
	 Type value - example PARAM_TEXT
	 ));
	 */

	// Basic Settings
	$settings->add(
			new admin_setting_heading(
					'paperattendance_basicsettings',
					get_string('settings', 'local_paperattendance'),
					''
					)
			);
	// Enrol method
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_enrolmethod',
					get_string('enrolmethod', 'local_paperattendance'),
					get_string('enrolmethodpro', 'local_paperattendance'),
					'manual',
					PARAM_TEXT
			)
	);
	//minuteslate
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_minuteslate',
					get_string('minuteslate', 'local_paperattendance'),
					get_string('minuteslatetext', 'local_paperattendance'),
					'20',
					PARAM_INT
					)
			);
	//max file size
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_maxfilesize',
					get_string('maxfilesize', 'local_paperattendance'),
					get_string('maxfilesizetext', 'local_paperattendance'),
					'8388608',
					PARAM_INT
			)
	);
	//omega's token
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_omegatoken',
					get_string('token', 'local_paperattendance'),
					get_string('tokentext', 'local_paperattendance'),
					null,
					PARAM_TEXT
					)
			);
	//omega's create attendance url
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_omegacreateattendanceurl',
					get_string('omegacreateattendance', 'local_paperattendance'),
					get_string('omegacreateattendancetext', 'local_paperattendance'),
					null,
					PARAM_TEXT
					)
			);
	//omega's create attendance url
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_omegaupdateattendanceurl',
					get_string('omegaupdateattendance', 'local_paperattendance'),
					get_string('omegaupdateattendancetext', 'local_paperattendance'),
					null,
					PARAM_TEXT
					)
			);
	//omega's get módulos horarios url
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_omegagetmoduloshorariosurl',
					get_string('omegagetmoduloshorarios', 'local_paperattendance'),
					get_string('omegagetmoduloshorariostext', 'local_paperattendance'),
					null,
					PARAM_TEXT
					)
			);
	//paperattendance send notification mail to the teacher
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_sendmail',
					get_string('sendmail', 'local_paperattendance'),
					get_string('sendmailtext', 'local_paperattendance'),
					1,
					PARAM_INT
					)
			);
	//max amount of files
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_maxfiles',
					get_string('maxfiles', 'local_paperattendance'),
					get_string('maxfilestext', 'local_paperattendance'),
					10,
					PARAM_INT
					)
			);
	//max time to discuss an attendance
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_discusstimelimit',
					get_string('discusstimelimit', 'local_paperattendance'),
					get_string('discusstimelimittext', 'local_paperattendance'),
					30*6,
					PARAM_INT
					)
			);
	//Categoryid
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_categoryid',
					get_string('categoryid', 'local_paperattendance'),
					get_string('categoryidtext', 'local_paperattendance'),
					1,
					PARAM_INT
					)
			);
	//Minutes delay for the digital attendance
	$settings->add(
			new admin_setting_configtext(
					'paperattendance_minutesdelay',
					get_string('minutesdelay', 'local_paperattendance'),
					get_string('minutesdelaytext', 'local_paperattendance'),
					0,
					PARAM_INT
					)
			);
    //Student role Parameter
    $settings->add(
        new admin_setting_configtext(
            'paperattendance_studentrole',
            get_string('studentrole', 'local_paperattendance'),
            get_string('studentroletext', 'local_paperattendance'),
            5,
            PARAM_INT
        )
    );

    //ProfesorEditor role Parameter
    $settings->add(
        new admin_setting_configtext(
            'paperattendance_profesoreditorrole',
            get_string('professoreditor', 'local_paperattendance'),
            get_string('professoreditortext', 'local_paperattendance'),
            3,
            PARAM_INT
        )
    );

    //ProfesorNoEditor role Parameter
    $settings->add(
        new admin_setting_configtext(
            'paperattendance_profesornoeditorrole',
            get_string('professornoeditor', 'local_paperattendance'),
            get_string('professornoeditortext', 'local_paperattendance'),
            4,
            PARAM_INT
        )
	);
	$settings->add(
		new admin_setting_configtext(
			'paperattendance_processpdflogpath',
			get_string('processpdf_log_path', 'local_paperattendance'),
			get_string('processpdf_log_path_description', 'local_paperattendance'),
			'/var/log/moodle/processpdf.log',
			PARAM_TEXT
		)
	);
}