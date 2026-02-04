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

namespace local_assign_ai;

use local_assign_ai\api\client;
use stdClass;

/**
 * Class assign_submission
 *
 * @package    local_assign_ai
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission {
    /** Initial status before processing with AI. */
    public const STATUS_INITIAL = 'initial';
    /** Pending status awaiting human review. */
    public const STATUS_PENDING = 'pending';
    /** Queued status when review-all has been scheduled. */
    public const STATUS_QUEUED = 'queued';
    /** Processing status when ad-hoc task is handling the record. */
    public const STATUS_PROCESSING = 'processing';
    /** Approved status after human review or AI grading. */
    public const STATUS_APPROVED = 'approve';
    /** Rejected status after human review. */
    public const STATUS_REJECTED = 'rejected';

    /** @var stdClass User ID of the author of the submission. */
    private stdClass $user;

    /** @var stdClass Submission record from {assign_submission}. */
    private stdClass|false $submission;

    /** @var \assign Assign instance */
    private \assign $assign;

    /** @var stdClass Assignment instance */
    private stdClass $assigninstance;

    /** @var stdClass Course instance */
    private stdClass $course;

    /**
     * Constructor.
     *
     * @param int $userid User ID of the author of the submission.
     * @param \assign $assign Assig instance.
     */
    public function __construct(int $userid, \assign $assign) {
        global $DB;
        $this->assign = $assign;
        $this->user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
        $this->submission = $assign->get_user_submission($userid, false);
        $this->assigninstance = $assign->get_instance();
        $this->course = $assign->get_course();
    }

    /**
     * Processes a submission against AI logic depending on autograde configuration.
     *
     * When autograde is enabled, sends the payload to the AI provider, stores the
     * pending record with the AI response, and applies the feedback (grade/comments).
     * Otherwise, only creates a pending record for later manual review.
     *
     * @return void
     */
    public function process_submission_ai(): void {
        global $DB;

        if (!$this->submission || !$this->user) {
            return;
        }

        // Only process submitted attempts.
        if ($this->submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return;
        }

        $assignment = $this->assigninstance;
        $cmid = $this->assign->get_course_module()->id;

        if (!local_assign_ai_is_autograde_enabled($this->assign)) {
            // Autograde disabled: create a basic pending record for teacher review later.
            $record = (object) [
                'courseid' => $this->course->id,
                'assignmentid' => $cmid,
                'userid' => $this->user->id,
                'title' => $assignment->name,
                'message' => null,
                'grade' => null,
                'rubric_response' => null,
                'status' => self::STATUS_INITIAL,
            ];
            self::create_pending_submission($record);
            return;
        }

        $payload = $this->build_payload();
        $response = client::send_to_ai($payload);

        $message = $response['reply'] ?? null;
        $grade = isset($response['grade']) ? (is_numeric($response['grade']) ? (float) $response['grade'] : null) : null;

        // Determine correct advanced grading response (rubric or assessment_guide).
        $rawadvanced = !empty($response['rubric']) ? $response['rubric'] : ($response['assessment_guide'] ?? null);
        $rubricresponse = null;
        $assessmentguideresponse = null;

        if ($rawadvanced) {
            $advanceddata = $rawadvanced;
            if (is_array($rawadvanced) && isset($rawadvanced['criteria'])) {
                $advanceddata = $rawadvanced['criteria'];
            }
            $jsonresponse = json_encode($advanceddata, JSON_UNESCAPED_UNICODE);

            if (!empty($response['rubric'])) {
                $rubricresponse = $jsonresponse;
            } else {
                $assessmentguideresponse = $jsonresponse;
            }
        }

        $record = (object) [
            'courseid' => $this->course->id,
            'assignmentid' => $cmid,
            'userid' => $this->user->id,
            'title' => $assignment->name,
            'message' => $message,
            'grade' => $grade !== null ? (int) round($grade) : null,
            'rubric_response' => $rubricresponse,
            'assessment_guide_response' => $assessmentguideresponse,
            'status' => self::STATUS_APPROVED,
        ];
        $recordid = self::create_pending_submission($record);

        $record = $DB->get_record('local_assign_ai_pending', ['id' => $recordid]);
        $config = local_assign_ai_get_assignment_config($this->assigninstance->id);
        if ($record && !empty($config) && !empty($config->graderid)) {
            local_assign_ai_apply_ai_feedback($this->assign, $record, $config->graderid);
        }
    }

    /**
     * Processes a submission for the "Review with AI" action.
     *
     * Always sends the submission payload to the AI provider (regardless of
     * autograde setting) and stores the AI response in the pending table with
     * status set to STATUS_PENDING for manual review/approval.
     *
     * Requirements:
     *  - The user must have a submission with status ASSIGN_SUBMISSION_STATUS_SUBMITTED.
     *
     * @param int $pendingid Pending record ID to update in local_assign_ai_pending.
     * @return void
     */
    public function process_submission_ai_review(int $pendingid): void {
        global $DB;
        if (!$this->submission || !$this->user) {
            return;
        }
        if ($this->submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return;
        }

        // Find existing pending record to update.
        $existing = $DB->get_record('local_assign_ai_pending', ['id' => $pendingid], '*', MUST_EXIST);

        $payload = $this->build_payload();
        $response = client::send_to_ai($payload);

        $message = $response['reply'] ?? null;
        $grade = isset($response['grade']) ? (is_numeric($response['grade']) ? (float) $response['grade'] : null) : null;

        // Determine correct advanced grading response (rubric or assessment_guide).
        $rawadvanced = !empty($response['rubric']) ? $response['rubric'] : ($response['assessment_guide'] ?? null);
        $rubricresponse = null;
        $assessmentguideresponse = null;

        if ($rawadvanced) {
            $advanceddata = $rawadvanced;
            if (is_array($rawadvanced) && isset($rawadvanced['criteria'])) {
                $advanceddata = $rawadvanced['criteria'];
            }
            $jsonresponse = json_encode($advanceddata, JSON_UNESCAPED_UNICODE);

            if (!empty($response['rubric'])) {
                $rubricresponse = $jsonresponse;
            } else {
                $assessmentguideresponse = $jsonresponse;
            }
        }

        $data = [
            'message' => $message,
            'grade' => $grade !== null ? (int) round($grade) : null,
            'rubric_response' => $rubricresponse,
            'assessment_guide_response' => $assessmentguideresponse,
            'status' => self::STATUS_PENDING,
        ];
        self::update_pending_submission($existing->id, $data);
    }

    /**
     * Create a pending AI submission record.
     *
     * Expects a record object with the following fields:
     *  - courseid (int): Course ID.
     *  - assignmentid (int): Assignment identifier. For consistency in this plugin this is the course module id (cmid).
     *  - userid (int): Author user ID.
     *  - title (string): Record title.
     *  - message (string|null): Optional message or AI feedback.
     *  - grade (int|null): Optional grade suggested by AI.
     *  - rubric_response (string|null): Optional rubric response JSON/text.
     *  - status (string|null): Optional status. If omitted, defaults to STATUS_PENDING.
     *
     * Additional fields are set automatically:
     *  - usermodified (int): ID of the user performing the operation.
     *  - timecreated (int): Creation timestamp.
     *  - timemodified (int): Modification timestamp.
     *
     * @param stdClass $record Pending record payload with the fields described above.
     * @return int New record ID.
     */
    public static function create_pending_submission(stdClass $record): int {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();
        try {
            $now = time();
            if (empty($record->approval_token)) {
                $record->approval_token = random_string(10);
            }
            $record->status = $record->status ?? self::STATUS_PENDING;
            $record->usermodified = $USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;

            $id = $DB->insert_record('local_assign_ai_pending', $record);
            $transaction->allow_commit();
            return $id;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Update an existing pending AI submission record.
     *
     * @param int $id Record ID.
     * @param array $data Data to update.
     * @return bool True on success, false on failure.
     */
    public static function update_pending_submission(int $id, array $data): bool {
        global $DB, $USER;

        $record = $DB->get_record('local_assign_ai_pending', ['id' => $id]);
        if (!$record) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($data as $key => $value) {
                $record->$key = $value;
            }
            $record->timemodified = time();
            if (!isset($record->usermodified)) {
                $record->usermodified = $USER->id ?? null;
            }

            $ok = $DB->update_record('local_assign_ai_pending', $record);
            $transaction->allow_commit();
            return $ok;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Retrieves the onlinetext content for a given submission.
     *
     * @param stdClass $submission Submission record from {assign_submission}.
     * @return string The submission text or empty string if none.
     */
    public static function get_submission_text(stdClass $submission): string {
        global $DB;
        $submissioncontent = '';
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submission->id]);
        if ($onlinetext && !empty($onlinetext->onlinetext)) {
            $submissioncontent = $onlinetext->onlinetext;
        }
        return $submissioncontent;
    }

    /**
     * Build payload for AI service.
     *
     * @return array The payload array.
     */
    private function build_payload(): array {
        $course = $this->assign->get_course();
        $assignment = $this->assigninstance;
        $cmid = $this->assign->get_course_module()->id;

        $advancedgrading = local_assign_ai_get_advanced_grading_json($this->assign);
        $rubric = null;
        $assessmentguide = null;

        if ($advancedgrading) {
            if ($advancedgrading['method'] === 'rubric') {
                $rubric = $advancedgrading['data'];
            } else if ($advancedgrading['method'] === 'guide') {
                $assessmentguide = $advancedgrading['data'];
            }
        }

        return [
            'course_id' => $course->id,
            'course' => $course->fullname,
            'assignment_id' => $assignment->id,
            'cmi_id' => $cmid,
            'assignment_title' => $assignment->name,
            'assignment_description' => $assignment->intro,
            'rubric' => $rubric,
            'assessment_guide' => $assessmentguide,
            'userid' => $this->user->id,
            'student_name' => fullname($this->user),
            'submission_assign' => self::get_submission_text($this->submission),
            'maximum_grade' => $assignment->grade,
        ];
    }
}
