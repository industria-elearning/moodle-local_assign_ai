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

$string['actions'] = 'Ações';
$string['aistatus'] = 'Status da IA';
$string['assign_ai:changestatus'] = 'Alterar o status de aprovação da IA';
$string['assign_ai:review'] = 'Revisar sugestões de IA para as tarefas';
$string['assign_ai:viewdetails'] = 'Ver detalhes dos comentários da IA';
$string['default_rubric_name'] = 'Rubrica';
$string['email'] = 'E-mail';
$string['error_airequest'] = 'Erro ao se comunicar com o serviço de IA: {$a}';
$string['errorparsingrubric'] = 'Erro ao analisar a resposta da rubrica: {$a}';
$string['feedbackcomments'] = 'Comentários';
$string['fullname'] = 'Nome completo';
$string['grade'] = 'Nota';
$string['gradesuccess'] = 'Nota inserida com sucesso';
$string['lastmodified'] = 'Última modificação';
$string['manytasksreviewed'] = '{$a} tarefas revisadas';
$string['modaltitle'] = 'Feedback da IA';
$string['norecords'] = 'Nenhum registro encontrado';
$string['nostatus'] = 'Sem feedback';
$string['notasksfound'] = 'Nenhuma tarefa para revisar';
$string['onetaskreviewed'] = '1 tarefa revisada';
$string['pluginname'] = 'Assignment IA';
$string['privacy:metadata:local_assign_ai_pending'] = 'Armazena os feedbacks gerados pela IA que aguardam aprovação.';
$string['privacy:metadata:local_assign_ai_pending:approval_token'] = 'Token único usado para rastrear aprovações.';
$string['privacy:metadata:local_assign_ai_pending:assignmentid'] = 'A tarefa à qual este feedback da IA corresponde.';
$string['privacy:metadata:local_assign_ai_pending:courseid'] = 'O curso associado a este feedback.';
$string['privacy:metadata:local_assign_ai_pending:grade'] = 'A nota proposta gerada pela IA.';
$string['privacy:metadata:local_assign_ai_pending:message'] = 'A mensagem de feedback gerada pela IA.';
$string['privacy:metadata:local_assign_ai_pending:rubric_response'] = 'O feedback da rubrica gerado pela IA.';
$string['privacy:metadata:local_assign_ai_pending:status'] = 'O status de aprovação do feedback.';
$string['privacy:metadata:local_assign_ai_pending:title'] = 'O título do feedback gerado.';
$string['privacy:metadata:local_assign_ai_pending:userid'] = 'O usuário para quem o feedback da IA foi gerado.';
$string['qualify'] = 'Avaliar';
$string['review'] = 'Revisar';
$string['reviewall'] = 'Revisar todos';
$string['reviewwithai'] = 'Revisar com IA';
$string['rubricfailed'] = 'Falha ao injetar rubrica após 20 tentativas';
$string['rubricmustarray'] = 'A resposta da rubrica deve ser um array.';
$string['rubricsuccess'] = 'Rubrica injetada com sucesso';
$string['save'] = 'Salvar';
$string['saveapprove'] = 'Salvar e Aprovar';
$string['status'] = 'Status';
$string['statusapprove'] = 'Aprovado';
$string['statuspending'] = 'Pendente';
$string['statusrejected'] = 'Rejeitado';
$string['submission_draft'] = 'Rascunho';
$string['submission_new'] = 'Novo';
$string['submission_none'] = 'Sem envio';
$string['submission_submitted'] = 'Enviado';
$string['submittedfiles'] = 'Arquivos enviados';
$string['unexpectederror'] = 'Ocorreu um erro inesperado: {$a}';
$string['viewdetails'] = 'Ver detalhes';
