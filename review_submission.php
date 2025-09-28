<?php
require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once(__DIR__.'/locallib.php'); // helper para la rúbrica

use local_assign_ai\api\client;

require_login();

$cmid   = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$all    = optional_param('all', 0, PARAM_BOOL);
$goto   = optional_param('goto', '', PARAM_ALPHA);

$cm     = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_capability('local/assign_ai:review', $context);

$assign = new assign($context, $cm, $course);

$token = null;

if ($all) {
    $students = get_enrolled_users($context, 'mod/assign:submit');
    foreach ($students as $student) {
        $token = process_submission_ai($assign, $course, $student, $DB);
    }
} else if ($userid) {
    $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $token = process_submission_ai($assign, $course, $student, $DB);
}

// 🔹 Redirección
if ($goto === 'grader' && $userid && $token) {
    redirect(new moodle_url('/mod/assign/view.php', [
        'id' => $cmid,
        'action' => 'grader',
        'userid' => $userid,
        'aitoken' => $token // el js inyecta el mensaje
    ]));
} else {
    redirect(new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]));
}

/**
 * Procesa la entrega de un usuario con IA y guarda como pendiente en local_assign_ai_pending.
 *
 * @return string|null El token generado para la retroalimentación pendiente, o null si no hay envío válido
 */
function process_submission_ai(assign $assign, $course, $student, $DB) {
    global $CFG;

    // Obtener entrega
    $submission = $assign->get_user_submission($student->id, false);
    if (!$submission || $submission->status !== 'submitted') {
        return null;
    }

    // Obtener archivo de la entrega
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $assign->get_context()->id,
        'assignsubmission_file',
        'submission_files',
        $submission->id,
        'id',
        false
    );

    if (empty($files)) {
        return null;
    }

    // Tomar el primer archivo válido
    $file = reset($files);
    $filepath = $CFG->dataroot . '/temp/assign_ai/' . $file->get_filename();
    @mkdir(dirname($filepath), 0777, true);
    $file->copy_content_to($filepath);

    // 🔹 Mock de conversión a texto
    $textcontent = "Contenido convertido del archivo: " . $file->get_filename();

    // Construir JSON
    $assignmentdata = [
        'id' => $assign->get_course_module()->id,
        'title' => $assign->get_instance()->name,
        'description' => $assign->get_instance()->intro,
    ];
    $rubric = build_rubric_json($assign);

    $payload = [
        'course' => $course->fullname,
        'assignment' => $assignmentdata,
        'rubric' => $rubric,
        'student' => [
            'id' => $student->id,
            'name' => fullname($student),
            'submission_assign' => $textcontent
        ]
    ];

    // IA mock
    $data = client::send_to_ai($payload);

    // Buscar si ya existe
    $existing = $DB->get_record('local_assign_ai_pending', [
        'courseid' => $course->id,
        'assignmentid' => $assign->get_course_module()->id,
        'userid' => $student->id
    ]);

    if ($existing) {
        // 🔹 Si está aprobado o rechazado, respetar su estado y token
        if ($existing->status === 'approve' || $existing->status === 'rejected') {
            return $existing->approval_token;
        }

        // 🔹 Si está pendiente, actualizar mensaje y mantener token
        $existing->message = $data['reply'];
        $existing->timemodified = time();
        $DB->update_record('local_assign_ai_pending', $existing);
        return $existing->approval_token;

    } else {
        // 🔹 Si no existe, crear nuevo en estado pending
        $token = bin2hex(random_bytes(16));
        $record = (object)[
            'courseid'      => $course->id,
            'assignmentid'  => $assign->get_course_module()->id,
            'title'         => $assign->get_instance()->name,
            'userid'        => $student->id,
            'message'       => $data['reply'],
            'status'        => 'pending',
            'approval_token'=> $token,
            'timecreated'   => time(),
            'timemodified'  => time()
        ];
        $DB->insert_record('local_assign_ai_pending', $record);
        return $token;
    }
}
