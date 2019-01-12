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
 * @copyright  2019 Matías Queirolo (mqueirolo@alumnos.uai.cl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//Pertenece al plugin PaperAttendance
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/paperattendance/locallib.php');
require_once ($CFG->dirroot . "/repository/lib.php");
require_once ($CFG->libdir . '/pdflib.php');
require_once ($CFG->dirroot . '/mod/assign/feedback/editpdf/fpdi/fpdi.php');
require_once ($CFG->dirroot . "/mod/assign/feedback/editpdf/fpdi/fpdi_bridge.php");
require_once ($CFG->dirroot . "/mod/assign/feedback/editpdf/fpdi/fpdi.php");
require_once ($CFG->dirroot . "/local/paperattendance/forms/attendance_form.php");
global $CFG, $DB, $OUTPUT, $USER, $PAGE;
// User must be logged in.
require_login();
if (isguestuser()) {
	print_error(get_string("usernotloggedin", "local_paperattendance"));
	die();
}

//Possible actions -> view, save . Standard is view mode
$action = optional_param("action", "view", PARAM_TEXT);
$courseid = required_param('courseid', PARAM_INT);

//Context definition
$context = context_course::instance($COURSE->id);

//Page settings
$urlpage = new moodle_url("/local/paperattendance/attendance.php", array('courseid' => $courseid));
$pagetitle = get_string('printtitle', 'local_paperattendance');
$PAGE->set_url($urlpage);
$PAGE->set_context($context);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout("standard");
$PAGE->set_heading($pagetitle);
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin ( 'ui' );
$PAGE->requires->jquery_plugin ( 'ui-css' );

//url back to the course
$backtocourse = new moodle_url("/course/view.php", array('id' => $courseid));

$isteacher = paperattendance_getteacherfromcourse($courseid, $USER->id);
if( !$isteacher && !is_siteadmin($USER) ){
	print_error(get_string('notallowedprint', 'local_paperattendance'));
}

//for navbar
$course = $DB->get_record("course",array("id" => $courseid));

//breadcrumb for navigation
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('courses', 'local_paperattendance'), new moodle_url('/course/index.php'));
$PAGE->navbar->add($course->shortname, new moodle_url('/course/view.php', array("id" => $courseid)));
$PAGE->navbar->add(get_string('pluginname', 'local_paperattendance'));
$PAGE->navbar->add(get_string('historytitle', 'local_paperattendance'), new moodle_url("/local/paperattendance/history.php", array("courseid" => $courseid)));

if($action == "view"){
	
	if (paperattendance_checktoken($CFG->paperattendance_omegatoken)){
		//var_dump("tokenyes");
		//CURL get modulos horario
		
		$curl = curl_init();
		
		$url = $CFG->paperattendance_omegagetmoduloshorariosurl;
		$token = $CFG->paperattendance_omegatoken;
		
		$fields = array (
				"diaSemana" => date('w'),
				"seccionId" => $course -> idnumber,
				"token" => $token
		);
		// 0 (para domingo) hasta 6 (para sábado)
		//$fields = array("diaSemana" => 3, "seccionId"=> 59169, "token" => $token);
		
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($fields));
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		$result = curl_exec ($curl);
		curl_close ($curl);
		
		$omegamodules = json_decode($result);
		//var_dump($omegamodules);
		
		$actualdate = getdate();
		$actualhour = $actualdate["hours"];
		$actualminutes = $actualdate["minutes"];
		$actualseconds = $actualdate["seconds"];
		$actualmodule = $actualhour.":".$actualminutes.":".$actualseconds;
		
		//$actualmodule = "12:25:1";
		$actualmoduleunix = strtotime($actualmodule);
		$noexistmodule = true;
		$betweenmodules = true;
		if(count($omegamodules) != 0){ // then exist omegamodules from omega
			var_dump("hay modulos omega");
			foreach ($omegamodules as $module){
				
				$modinicial = $module->horaInicio;
				$modfinal = $module->horaFin;
				
				//Check if exist some module in the actual time
				if ( (strtotime($modinicial) <= $actualmoduleunix) && ($actualmoduleunix <= strtotime($modfinal) )){
					$mod = explode(":", $module->horaInicio);
					$moduleinicio = $mod[0].":".$mod[1];
					$modfin = explode(":", $module->horaFin);
					$modulefin = $modfin[0].":".$modfin[1];
					$modquery = $DB->get_record("paperattendance_module", array("initialtime" => $moduleinicio));
					$moduleid = $modquery -> id;
					$noexistmodule = false;
					$betweenmodules = false;
					var_dump("actual esta en omega");
				}
			}
		}
		if (count($omegamodules) == 0 || $noexistmodule){ //no exist actual omegamodules from omega today
			//geting all modules from moodle
			var_dump("no hay modulos omega o no existe actual en omga");
			$getmodules = "SELECT *
						   FROM {paperattendance_module} 
						   ORDER BY name DESC";
			$modules = $DB->get_records_sql($getmodules);
			
			foreach ($modules as $module){
				
				$modinicial = $module->initialtime;
				$modfinal = $module->endtime;
				
				//Check what module is inside the actual time
				if ( (strtotime($modinicial) <= $actualmoduleunix) && ($actualmoduleunix <= strtotime($modfinal) )){
					$modquery = $module; //set the actual module to the modquery variable that we use after
					$moduleid = $modquery -> id;
					$moduleinicio = $modinicial;
					$modulefin = $modfinal;
					$betweenmodules = false;
					var_dump("modulo actual existe en modulos moodle");
				}
			}
		}
		
		// if not in between modules ->
		if (!$betweenmodules) {
			var_dump("no entremodulos");
			
			//session date from today in unix
			$sessiondate = strtotime(date('Y-m-d'));
			
			//check of the session
			$sessdoesntexist = paperattendance_check_session_modules($moduleid, $courseid, $sessiondate);
			
			if( $sessdoesntexist == "perfect"){
				//name of requestor
				$sessionrequestor = $DB->get_record("user", array("id" => $USER->id));
				$requestorname = ($sessionrequestor->firstname.' '.$sessionrequestor->lastname);
				
				//nº of sessions
				$sessions = $DB->count_records("paperattendance_session", array ("courseid" => $courseid));
				$actualsession = $sessions +1;
				
				//Info in the title
				$sessinfo = html_writer::nonempty_tag("div", paperattendance_convertdate($sessiondate), array("align" => "left"));
				$sessinfo .= html_writer::nonempty_tag("div", get_string("requestor","local_paperattendance").": ".$requestorname, array("align" => "left"));
				$sessinfo .= html_writer::nonempty_tag("div", get_string("description","local_paperattendance").": ".get_string('class', 'local_paperattendance'), array("align" => "left"));
				$sessinfo .= html_writer::nonempty_tag("div", get_string("module","local_paperattendance").": ".$moduleinicio." - ".$modulefin, array("align" => "left"));
				$sessinfo .= html_writer::nonempty_tag("div", get_string("session","local_paperattendance")." ".$actualsession, array("align" => "left"));
				$sessinfo .= html_writer::nonempty_tag("div","<br>", array("align" => "left"));
				if ($noexistmodule){
					$sessinfo .= html_writer::div("Estimador profesor(a), para poder tomar asistencia se creará una sesión extra que no proviene de omega.","alert alert-info", array("role"=>"alert"));
				}
				
				//get students for the form
				$enrolincludes = explode("," ,$CFG->paperattendance_enrolmethod);
				list ( $sqlin, $param1 ) = $DB->get_in_or_equal ( $enrolincludes );
				$param2 = array(
						$courseid
				);
				$param = array_merge($param2,$param1);
				//Query to get the actual enrolled students in the course
				$getstudents = "SELECT
				u.id AS userid,
				u.lastname,
				u.firstname,
				u.email AS email
				FROM {user} AS u
				INNER JOIN {user_enrolments} ue ON ue.userid = u.id
				INNER JOIN {enrol} e ON e.id = ue.enrolid
				INNER JOIN {role_assignments} ra ON ra.userid = u.id
				INNER JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
				INNER JOIN {course} c ON c.id=? AND c.id = ct.instanceid AND e.courseid = c.id
				INNER JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
				WHERE e.enrol $sqlin AND e.status = 0 AND u.suspended = 0 AND u.deleted = 0
				ORDER BY u.lastname ASC";
				$nstudents = count($DB->get_records_sql($getstudents, $param));
				///////if no students add condition print error
				$enrolledstudents = $DB->get_records_sql($getstudents, $param);
				
				//Instantiate form
				$mform = new paperattendance_attendance_form(null, array("courseid" => $courseid, "enrolledstudents" => $enrolledstudents));
				
				//Form processing and displaying is done here
				if ($mform->is_cancelled()) {
					//Handle form cancel operation, if cancel button is present on form
					redirect($backtocourse);
				} else if ($data = $mform->get_data()) {
					//In this case you process validated data. $mform->get_data() returns data posted in form.
					//var_dump($data);
					
					//Session creation
					$description = 0; //0 -> Indicates normal class
					$requestorid = $USER->id;
					//type = 1 for digital, 0 for paper
					$type = 1;
					// (courseid, teacherid, uploaderid, pdfname, description)
					$sessid = paperattendance_insert_session($courseid, $requestorid, $requestorid, NULL, $description, $type);
					paperattendance_insert_session_module($moduleid, $sessid, $sessiondate);
					
					$arrayalumnos = array();
					foreach ($enrolledstudents as $student){
						$email = $student->email;
						$userid = $student->userid;
						$key = "key".$userid;
						$checkboxname = $data->$key;
						//var_dump($userid);
						//var_dump($checkboxname);
						
						if ($checkboxname == 0){
							$asistencia = "false";
							$attendance = '0';
						}
						else{
							$asistencia = "true";
							$attendance = '1';
						}
						$line = array();
						$line['emailAlumno'] = $email;
						$line['resultado'] = "true";
						$line['asistencia'] = $asistencia;
						
						//Save student attendance in database
						paperattendance_save_student_presence($sessid, $student -> userid, $attendance, NULL);
						$arrayalumnos[] = $line;
					}
					/*
					//save attendance in omega
					$update = new stdClass();
					$update->id = $sessid;
					if(paperattendance_omegacreateattendance($courseid, $arrayalumnos, $sessid)){
						$update->status = 2;
					}else{
						$update->status = 1;
					}
					$DB->update_record("paperattendance_session", $update);
					/*
					//send mail of confirmation
					if($CFG->paperattendance_sendmail == 1){
						$sessdate = date("d-m-Y", $time).", ".$modquery->name. ": ". $modquery->initialtime. " - " .$modquery->endtime;
						paperattendance_sendMail($sessid, $courseid, $requestorid, $requestorid, $sessdate, $course->fullname, "processpdf", null);
					}
					*/
					
					$action = "save";
					
				}
			}
			else {
				var_dump("sesion ya existe");
				$sessinfo = html_writer::div("Estimador profesor(a), ya se ha tomado asistencia en el modulo actual.","alert alert-error", array("role"=>"alert"));
				$viewbacktocoursebutton = html_writer::nonempty_tag(
						"div",
						$OUTPUT->single_button($backtocourse, get_string('back', 'local_paperattendance')),
						array("align" => "left"
						));
			}
		}
		// if actual hour is in between modules ->
		else {
			var_dump(" entre modulos");
			$sessinfo = html_writer::div("Estimador profesor(a), para poder tomar asistencia debe esperar a que comience el módulo siguiente.","alert alert-error", array("role"=>"alert"));
			$viewbacktocoursebutton = html_writer::nonempty_tag(
					"div",
					$OUTPUT->single_button($backtocourse, get_string('back', 'local_paperattendance')),
					array("align" => "left"
					));
		}
		
		/*
		var_dump($actualmodule);
		var_dump($actualhour);
		var_dump($actualdate);
		var_dump(strtotime("8:9:10"));
		var_dump("-----------");
		var_dump(strtotime("08:14:15"));
		var_dump($moduleid);
		var_dump(gettimeofday(true));
		var_dump($actualmoduleunix);
		var_dump(time());
		*/
			
	}
	// if the token not accepted ->
	else{
		var_dump("tokenno");
		print_error(get_string("usernotloggedin", "local_paperattendance"));
	}
}

if($action == "save"){
	$backurl = new moodle_url("/local/paperattendance/history.php", array(
			"action" => "studentsattendance",
			"attendanceid" => $sessid,
			"courseid" => $courseid
	));
	$viewbackbutton = html_writer::nonempty_tag(
			"div",
			$OUTPUT->single_button($backurl, "Ver Historial"),
			array("align" => "left"
			));
}

echo $OUTPUT->header();
//echo $OUTPUT->heading(get_string("missingpagestitle", "local_paperattendance"));
if($action == "view"){
	
	echo html_writer::nonempty_tag("h3", $course->shortname." - ".$course->fullname);
	echo html_writer::nonempty_tag("div", $sessinfo);
	if (isset($mform)){
		//displays the form
		$mform->display();
	}
	else{
		echo $viewbacktocoursebutton;
	}
}

if($action == "save"){
	echo html_writer::div("Asistencia guardada correctamente","alert alert-success", array("role"=>"alert"));
	echo $viewbackbutton;
}

echo $OUTPUT->footer();
?>

