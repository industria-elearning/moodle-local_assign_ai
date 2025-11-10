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
$string['aitaskdone'] = 'Traitement IA terminé. Total des soumissions traitées : {$a}';
$string['aitaskstart'] = 'Traitement des soumissions IA pour le cours : {$a}';
$string['aitaskuserqueued'] = 'Soumission en file d’attente pour l’utilisateur avec ID {$a->id} ({$a->name})';
$string['altlogo'] = 'Logo Datacurso';
$string['assign_ai:changestatus'] = 'Modifier le statut d’approbation de l’IA';
$string['assign_ai:review'] = 'Examiner les suggestions IA pour les devoirs';
$string['assign_ai:viewdetails'] = 'Voir les détails des commentaires IA';
$string['default_rubric_name'] = 'Grille';
$string['email'] = 'E-mail';
$string['error_airequest'] = 'Erreur de communication avec le service IA : {$a}';
$string['errorparsingrubric'] = 'Erreur lors de l’analyse de la réponse de la grille : {$a}';
$string['feedbackcomments'] = 'Commentaires';
$string['fullname'] = 'Nom complet';
$string['grade'] = 'Note';
$string['gradesuccess'] = 'Note injectée avec succès';
$string['lastmodified'] = 'Dernière modification';
$string['manytasksreviewed'] = '{$a} tâches examinées';
$string['missingtaskparams'] = 'Paramètres de tâche manquants. Impossible de démarrer le traitement IA en lot.';
$string['modaltitle'] = 'Retour IA';
$string['norecords'] = 'Aucun enregistrement trouvé';
$string['nostatus'] = 'Aucun retour';
$string['nosubmissions'] = 'Aucune soumission trouvée à traiter.';
$string['notasksfound'] = 'Aucune tâche à examiner';
$string['onetaskreviewed'] = '1 tâche examinée';
$string['pluginname'] = 'Assignment IA';
$string['privacy:metadata:local_assign_ai_pending'] = 'Stocke les retours IA en attente d’approbation.';
$string['privacy:metadata:local_assign_ai_pending:approval_token'] = 'Jeton unique utilisé pour le suivi des approbations.';
$string['privacy:metadata:local_assign_ai_pending:assignmentid'] = 'Le devoir auquel ce retour IA correspond.';
$string['privacy:metadata:local_assign_ai_pending:courseid'] = 'Le cours associé à ce retour.';
$string['privacy:metadata:local_assign_ai_pending:grade'] = 'La note proposée générée par l’IA.';
$string['privacy:metadata:local_assign_ai_pending:message'] = 'Le message de retour généré par l’IA.';
$string['privacy:metadata:local_assign_ai_pending:rubric_response'] = 'Le retour de grille généré par l’IA.';
$string['privacy:metadata:local_assign_ai_pending:status'] = 'Statut d’approbation du retour.';
$string['privacy:metadata:local_assign_ai_pending:title'] = 'Titre du retour généré.';
$string['privacy:metadata:local_assign_ai_pending:userid'] = 'L’utilisateur pour lequel le retour IA a été généré.';
$string['processed'] = '{$a} soumission(s) traitée(s) avec succès.';
$string['processing'] = 'Traitement en cours';
$string['processingerror'] = 'Une erreur s’est produite lors du traitement IA.';
$string['qualify'] = 'Noter';
$string['queued'] = 'Toutes les soumissions ont été placées en file d’attente pour révision IA. Elles seront traitées sous peu.';
$string['reloadpage'] = 'Rechargez la page pour voir les résultats mis à jour.';
$string['review'] = 'Examiner';
$string['reviewall'] = 'Tout examiner';
$string['reviewwithai'] = 'Révision IA';
$string['rubricfailed'] = 'Impossible d’injecter la grille après 20 tentatives';
$string['rubricmustarray'] = 'La réponse de la grille doit être un tableau.';
$string['rubricsuccess'] = 'Grille injectée avec succès';
$string['save'] = 'Enregistrer';
$string['saveapprove'] = 'Enregistrer et approuver';
$string['status'] = 'Statut';
$string['statusapprove'] = 'Approuvé';
$string['statuspending'] = 'En attente';
$string['statusrejected'] = 'Rejeté';
$string['submission_draft'] = 'Brouillon';
$string['submission_new'] = 'Nouveau';
$string['submission_none'] = 'Aucune soumission';
$string['submission_submitted'] = 'Soumis';
$string['submittedfiles'] = 'Fichiers soumis';
$string['unexpectederror'] = 'Erreur inattendue : {$a}';
$string['viewdetails'] = 'Voir les détails';
