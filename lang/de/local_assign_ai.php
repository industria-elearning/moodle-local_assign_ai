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
$string['aitaskdone'] = 'KI-Verarbeitung abgeschlossen. Insgesamt verarbeitete Einreichungen: {$a}';
$string['aitaskstart'] = 'KI-Einreichungen für den Kurs werden verarbeitet: {$a}';
$string['aitaskuserqueued'] = 'Einreichung in der Warteschlange für Benutzer mit ID {$a->id} ({$a->name})';
$string['altlogo'] = 'Datacurso-Logo';
$string['assign_ai:changestatus'] = 'KI-Genehmigungsstatus ändern';
$string['assign_ai:review'] = 'KI-Vorschläge für Aufgaben überprüfen';
$string['assign_ai:viewdetails'] = 'KI-Kommentardetails anzeigen';
$string['default_rubric_name'] = 'Rubrik';
$string['delayminutes'] = 'Wartezeit (Minuten)';
$string['delayminutes_help'] = 'Anzahl der Minuten, die nach dem Beitrag des Teilnehmers gewartet werden soll, bevor die KI-Überprüfung ausgeführt wird.';
$string['email'] = 'E-Mail';
$string['error_airequest'] = 'Fehler bei der Kommunikation mit dem KI-Dienst: {$a}';
$string['errorparsingrubric'] = 'Fehler beim Analysieren der Rubrik-Antwort: {$a}';
$string['feedbackcomments'] = 'Kommentare';
$string['fullname'] = 'Vollständiger Name';
$string['grade'] = 'Bewertung';
$string['gradesuccess'] = 'Bewertung erfolgreich eingefügt';
$string['lastmodified'] = 'Zuletzt geändert';
$string['manytasksreviewed'] = '{$a} Aufgaben überprüft';
$string['missingtaskparams'] = 'Aufgabenparameter fehlen. KI-Batchverarbeitung kann nicht gestartet werden.';
$string['modaltitle'] = 'KI-Feedback';
$string['norecords'] = 'Keine Datensätze gefunden';
$string['nostatus'] = 'Kein Feedback';
$string['nosubmissions'] = 'Keine Einreichungen zum Verarbeiten gefunden.';
$string['notasksfound'] = 'Keine Aufgaben zur Überprüfung gefunden';
$string['onetaskreviewed'] = '1 Aufgabe überprüft';
$string['pluginname'] = 'Assignment AI';
$string['privacy:metadata:local_assign_ai_pending'] = 'Speichert KI-generiertes Feedback, das auf Genehmigung wartet.';
$string['privacy:metadata:local_assign_ai_pending:approval_token'] = 'Einzigartiges Token zur Verfolgung von Genehmigungen.';
$string['privacy:metadata:local_assign_ai_pending:assignmentid'] = 'Die Aufgabe, zu der dieses KI-Feedback gehört.';
$string['privacy:metadata:local_assign_ai_pending:courseid'] = 'Der Kurs, der mit diesem Feedback verknüpft ist.';
$string['privacy:metadata:local_assign_ai_pending:grade'] = 'Die von der KI vorgeschlagene Bewertung.';
$string['privacy:metadata:local_assign_ai_pending:message'] = 'Die von der KI generierte Feedbacknachricht.';
$string['privacy:metadata:local_assign_ai_pending:rubric_response'] = 'Das von der KI generierte Rubrik-Feedback.';
$string['privacy:metadata:local_assign_ai_pending:status'] = 'Genehmigungsstatus des Feedbacks.';
$string['privacy:metadata:local_assign_ai_pending:title'] = 'Titel des generierten Feedbacks.';
$string['privacy:metadata:local_assign_ai_pending:userid'] = 'Der Benutzer, für den das KI-Feedback generiert wurde.';
$string['processed'] = '{$a} Einreichung(en) erfolgreich verarbeitet.';
$string['processing'] = 'Verarbeitung läuft';
$string['processingerror'] = 'Fehler bei der KI-Überprüfung aufgetreten.';
$string['qualify'] = 'Bewerten';
$string['queued'] = 'Alle Einreichungen wurden zur KI-Überprüfung in die Warteschlange gestellt. Sie werden in Kürze verarbeitet.';
$string['reloadpage'] = 'Seite neu laden, um die aktualisierten Ergebnisse zu sehen.';
$string['require_approval'] = 'KI-Antwort überprüfen';
$string['review'] = 'Überprüfen';
$string['reviewall'] = 'Alle überprüfen';
$string['reviewwithai'] = 'KI-Überprüfung';
$string['rubricfailed'] = 'Rubrik konnte nach 20 Versuchen nicht eingefügt werden';
$string['rubricmustarray'] = 'Die Rubrik-Antwort muss ein Array sein.';
$string['rubricsuccess'] = 'Rubrik erfolgreich eingefügt';
$string['save'] = 'Speichern';
$string['saveapprove'] = 'Speichern und genehmigen';
$string['status'] = 'Status';
$string['statusapprove'] = 'Genehmigt';
$string['statuspending'] = 'Ausstehend';
$string['statusrejected'] = 'Abgelehnt';
$string['submission_draft'] = 'Entwurf';
$string['submission_new'] = 'Neu';
$string['submission_none'] = 'Keine Einreichung';
$string['submission_submitted'] = 'Eingereicht';
$string['submittedfiles'] = 'Eingereichte Dateien';
$string['unexpectederror'] = 'Unerwarteter Fehler: {$a}';
$string['usedelay'] = 'Verzögerte Überprüfung verwenden';
$string['usedelay_help'] = 'Wenn aktiviert, wird die KI-Überprüfung nach einer konfigurierbaren Wartezeit ausgeführt, anstatt sofort zu starten.';
$string['viewdetails'] = 'Details anzeigen';
