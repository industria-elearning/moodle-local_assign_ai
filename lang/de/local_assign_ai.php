<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_assign_ai
 * @category    string
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Aktionen';
$string['aistatus'] = 'KI-Status';
$string['assign_ai:changestatus'] = 'Genehmigungsstatus der KI ändern';
$string['assign_ai:review'] = 'KI-Vorschläge für Aufgaben überprüfen';
$string['assign_ai:viewdetails'] = 'KI-Kommentardetails anzeigen';
$string['default_rubric_name'] = 'Rubrik';
$string['email'] = 'E-Mail';
$string['error_airequest'] = 'Fehler bei der Kommunikation mit dem KI-Dienst: {$a}';
$string['errorparsingrubric'] = 'Fehler beim Analysieren der Rubrikantwort: {$a}';
$string['feedbackcomments'] = 'Kommentare';
$string['fullname'] = 'Vollständiger Name';
$string['grade'] = 'Bewertung';
$string['gradesuccess'] = 'Bewertung erfolgreich übertragen';
$string['lastmodified'] = 'Zuletzt geändert';
$string['manytasksreviewed'] = '{$a} Aufgaben überprüft';
$string['modaltitle'] = 'KI-Feedback';
$string['norecords'] = 'Keine Einträge gefunden';
$string['nostatus'] = 'Kein Feedback';
$string['notasksfound'] = 'Keine Aufgaben zur Überprüfung';
$string['onetaskreviewed'] = '1 Aufgabe überprüft';
$string['pluginname'] = 'Assignment KI';
$string['privacy:metadata:local_assign_ai_pending'] = 'Speichert von der KI generiertes Feedback, das auf Genehmigung wartet.';
$string['privacy:metadata:local_assign_ai_pending:approval_token'] = 'Einzigartiges Token zur Nachverfolgung von Genehmigungen.';
$string['privacy:metadata:local_assign_ai_pending:assignmentid'] = 'Die Aufgabe, auf die sich das KI-Feedback bezieht.';
$string['privacy:metadata:local_assign_ai_pending:courseid'] = 'Der Kurs, dem das Feedback zugeordnet ist.';
$string['privacy:metadata:local_assign_ai_pending:grade'] = 'Von der KI vorgeschlagene Bewertung.';
$string['privacy:metadata:local_assign_ai_pending:message'] = 'Von der KI generierte Feedback-Nachricht.';
$string['privacy:metadata:local_assign_ai_pending:rubric_response'] = 'Von der KI generiertes Rubrik-Feedback.';
$string['privacy:metadata:local_assign_ai_pending:status'] = 'Genehmigungsstatus des Feedbacks.';
$string['privacy:metadata:local_assign_ai_pending:title'] = 'Titel des generierten Feedbacks.';
$string['privacy:metadata:local_assign_ai_pending:userid'] = 'Der Benutzer, für den das KI-Feedback generiert wurde.';
$string['qualify'] = 'Bewerten';
$string['review'] = 'Überprüfen';
$string['reviewall'] = 'Alle überprüfen';
$string['reviewwithai'] = 'Überprüfung mit KI';
$string['rubricfailed'] = 'Rubrik konnte nach 20 Versuchen nicht übertragen werden';
$string['rubricmustarray'] = 'Rubrikantwort muss ein Array sein.';
$string['rubricsuccess'] = 'Rubrik erfolgreich übertragen';
$string['save'] = 'Speichern';
$string['saveapprove'] = 'Speichern und genehmigen';
$string['status'] = 'Status';
$string['statusapprove'] = 'Genehmigt';
$string['statuspending'] = 'Ausstehend';
$string['statusrejected'] = 'Abgelehnt';
$string['submission_draft'] = 'Entwurf';
$string['submission_new'] = 'Neu';
$string['submission_none'] = 'Keine Abgabe';
$string['submission_submitted'] = 'Eingereicht';
$string['submittedfiles'] = 'Eingereichte Dateien';
$string['unexpectederror'] = 'Unerwarteter Fehler aufgetreten: {$a}';
$string['viewdetails'] = 'Details anzeigen';
