<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php'); // Para usar la clase assign

require_login();

$cmid = required_param('id', PARAM_INT);

// Obtener el coursemodule y el curso
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_capability('local/assign_ai:review', $context);

// Instanciar el objeto assign
$assign = new assign($context, $cm, $course);

// Configuración de la página
$PAGE->set_url(new moodle_url('/local/assign_ai/review.php', ['id' => $cmid]));
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewwithai', 'local_assign_ai'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd('local_assign_ai/review', 'init'); // JS solo para el modal

$PAGE->activityheader->disable();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewwithai', 'local_assign_ai'));

// 🔹 Botón Revisar todos
$reviewallurl = new moodle_url('/local/assign_ai/review_submission.php', [
    'id' => $cmid,
    'all' => 1
]);
echo html_writer::link(
    $reviewallurl,
    get_string('reviewall', 'local_assign_ai'),
    ['class' => 'btn btn-warning mb-3']
);

// Obtener lista de usuarios matriculados en el curso con capacidad de entregar
$students = get_enrolled_users($context, 'mod/assign:submit');

// Armar tabla
$table = new html_table();
$table->head = [
    get_string('fullname', 'local_assign_ai'),
    get_string('email', 'local_assign_ai'),
    get_string('status', 'local_assign_ai'),
    get_string('feedbackcomments', 'local_assign_ai'),
    get_string('aistatus', 'local_assign_ai'),
    get_string('actions', 'local_assign_ai')
];

foreach ($students as $student) {
    // Obtener la entrega usando la API
    $submission = $assign->get_user_submission($student->id, false);

    if ($submission) {
        switch ($submission->status) {
            case 'submitted':
                $status = get_string('submission_submitted', 'local_assign_ai');
                break;
            case 'draft':
                $status = get_string('submission_draft', 'local_assign_ai');
                break;
            case 'new':
                $status = get_string('submission_new', 'local_assign_ai');
                break;
            default:
                $status = get_string('submission_none', 'local_assign_ai');
        }
    } else {
        $status = get_string('submission_none', 'local_assign_ai');
    }

    // Recuperar retroalimentación del profesor
    $grade = $assign->get_user_grade($student->id, true);
    $feedbacktext = '-';
    if ($grade && isset($grade->feedbacktext) && !empty($grade->feedbacktext)) {
        $feedbacktext = $grade->feedbacktext;
    }

    // Estado y botón de retroalimentación IA
    $record = $DB->get_record('local_assign_ai_pending', [
        'courseid' => $course->id,
        'assignmentid' => $cm->id,
        'userid' => $student->id
    ]);

    if ($record) {
        switch ($record->status) {
            case 'approve':
                $aistatus = get_string('statusapprove', 'local_assign_ai');
                break;
            case 'rejected':
                $aistatus = get_string('statusrejected', 'local_assign_ai');
                break;
            case 'pending':
            default:
                $aistatus = get_string('statuspending', 'local_assign_ai');
        }

        // Botón verde para abrir modal
        $aibutton = html_writer::tag(
            'button',
            get_string('viewdetails', 'local_assign_ai'),
            [
                'class' => 'btn btn-success view-details',
                'data-token' => $record->approval_token
            ]
        );
    } else {
        $aistatus = get_string('nostatus', 'local_assign_ai');
        $aibutton = html_writer::tag(
            'button',
            get_string('viewdetails', 'local_assign_ai'),
            [
                'class' => 'btn btn-success view-details',
                'disabled' => 'disabled'
            ]
        );
    }

    // 🔹 Botón azul → primero procesa IA y luego abre grader
    $viewurl = new moodle_url('/local/assign_ai/review_submission.php', [
        'id' => $cmid,
        'userid' => $student->id,
        'goto' => 'grader'
    ]);
    $button = html_writer::link(
        $viewurl,
        get_string('ver', 'local_assign_ai'),
        ['class' => 'btn btn-primary']
    );

    // Botón gris → revisar IA por usuario
    $reviewurl = new moodle_url('/local/assign_ai/review_submission.php', [
        'id' => $cmid,
        'userid' => $student->id
    ]);
    $reviewbtn = html_writer::link(
        $reviewurl,
        get_string('review', 'local_assign_ai'),
        ['class' => 'btn btn-secondary']
    );

    $table->data[] = [
        fullname($student),
        $student->email,
        $status,
        $feedbacktext,
        $aistatus,
        $button . ' ' . $aibutton . ' ' . $reviewbtn
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
