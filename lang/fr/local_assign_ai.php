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

$string['actions'] = 'Actions';
$string['aistatus'] = 'Statut IA';
$string['altlogo'] = 'Logo Datacurso';
$string['assign_ai:changestatus'] = 'Changer le statut d’approbation de l’IA';
$string['assign_ai:review'] = 'Examiner les suggestions d’IA pour les devoirs';
$string['assign_ai:viewdetails'] = 'Voir les détails des commentaires de l’IA';
$string['default_rubric_name'] = 'Rubrique';
$string['email'] = 'Courriel';
$string['error_airequest'] = 'Erreur de communication avec le service IA : {$a}';
$string['errorparsingrubric'] = 'Erreur lors de l’analyse de la réponse de la rubrique : {$a}';
$string['feedbackcomments'] = 'Commentaires';
$string['fullname'] = 'Nom complet';
$string['grade'] = 'Note';
$string['gradesuccess'] = 'Note injectée avec succès';
$string['lastmodified'] = 'Dernière modification';
$string['manytasksreviewed'] = '{$a} devoirs examinés';
$string['modaltitle'] = 'Rétroaction IA';
$string['norecords'] = 'Aucun enregistrement trouvé';
$string['nostatus'] = 'Aucune rétroaction';
$string['notasksfound'] = 'Aucun devoir à examiner';
$string['onetaskreviewed'] = '1 devoir examiné';
$string['pluginname'] = 'Assignment IA';
$string['privacy:metadata:local_assign_ai_pending'] = 'Stocke les rétroactions générées par l’IA en attente d’approbation.';
$string['privacy:metadata:local_assign_ai_pending:approval_token'] = 'Jeton unique utilisé pour le suivi des approbations.';
$string['privacy:metadata:local_assign_ai_pending:assignmentid'] = 'Le devoir correspondant à cette rétroaction IA.';
$string['privacy:metadata:local_assign_ai_pending:courseid'] = 'Le cours associé à cette rétroaction.';
$string['privacy:metadata:local_assign_ai_pending:grade'] = 'La note proposée générée par l’IA.';
$string['privacy:metadata:local_assign_ai_pending:message'] = 'Le message de rétroaction généré par l’IA.';
$string['privacy:metadata:local_assign_ai_pending:rubric_response'] = 'La rétroaction de rubrique générée par l’IA.';
$string['privacy:metadata:local_assign_ai_pending:status'] = 'Le statut d’approbation de la rétroaction.';
$string['privacy:metadata:local_assign_ai_pending:title'] = 'Le titre de la rétroaction générée.';
$string['privacy:metadata:local_assign_ai_pending:userid'] = 'L’utilisateur pour lequel la rétroaction IA a été générée.';
$string['qualify'] = 'Évaluer';
$string['review'] = 'Examiner';
$string['reviewall'] = 'Tout examiner';
$string['reviewwithai'] = 'Examen avec IA';
$string['rubricfailed'] = 'Échec de l’injection de la rubrique après 20 tentatives';
$string['rubricmustarray'] = 'La réponse à la rubrique doit être un tableau.';
$string['rubricsuccess'] = 'Rubrique injectée avec succès';
$string['save'] = 'Enregistrer';
$string['saveapprove'] = 'Enregistrer et approuver';
$string['status'] = 'Statut';
$string['statusapprove'] = 'Approuvé';
$string['statuspending'] = 'En attente';
$string['statusrejected'] = 'Rejeté';
$string['submission_draft'] = 'Brouillon';
$string['submission_new'] = 'Nouveau';
$string['submission_none'] = 'Aucune remise';
$string['submission_submitted'] = 'Soumis';
$string['submittedfiles'] = 'Fichiers soumis';
$string['unexpectederror'] = 'Une erreur inattendue est survenue : {$a}';
$string['viewdetails'] = 'Voir les détails';
