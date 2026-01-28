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

$string['actions'] = 'Действия';
$string['aistatus'] = 'Статус ИИ';
$string['aitaskdone'] = 'Обработка ИИ завершена. Всего обработано отправок: {$a}';
$string['aitaskstart'] = 'Обработка отправок ИИ для курса: {$a}';
$string['aitaskuserqueued'] = 'Отправка в очереди для пользователя с ID {$a->id} ({$a->name})';
$string['altlogo'] = 'Логотип Datacurso';
$string['assign_ai:changestatus'] = 'Изменить статус утверждения ИИ';
$string['assign_ai:review'] = 'Проверить предложения ИИ для заданий';
$string['assign_ai:viewdetails'] = 'Просмотреть детали комментариев ИИ';
$string['default_rubric_name'] = 'Рубрика';
$string['delayminutes'] = 'Время ожидания (в минутах)';
$string['delayminutes_help'] = 'Количество минут, которое нужно подождать после публикации ответа студентом перед запуском проверки с помощью ИИ.';
$string['email'] = 'Электронная почта';
$string['error_airequest'] = 'Ошибка при связи со службой ИИ: {$a}';
$string['errorparsingrubric'] = 'Ошибка при разборе ответа рубрики: {$a}';
$string['feedbackcomments'] = 'Комментарии';
$string['fullname'] = 'Полное имя';
$string['grade'] = 'Оценка';
$string['gradesuccess'] = 'Оценка успешно добавлена';
$string['lastmodified'] = 'Последнее изменение';
$string['manytasksreviewed'] = 'Проверено заданий: {$a}';
$string['missingtaskparams'] = 'Отсутствуют параметры задания. Невозможно начать пакетную обработку ИИ.';
$string['modaltitle'] = 'Обратная связь ИИ';
$string['norecords'] = 'Записей не найдено';
$string['nostatus'] = 'Нет обратной связи';
$string['nosubmissions'] = 'Не найдено отправок для обработки.';
$string['notasksfound'] = 'Нет заданий для проверки';
$string['onetaskreviewed'] = 'Проверено 1 задание';
$string['pluginname'] = 'Assignment AI';
$string['privacy:metadata:local_assign_ai_pending'] = 'Хранит обратную связь, сгенерированную ИИ, ожидающую утверждения.';
$string['privacy:metadata:local_assign_ai_pending:approval_token'] = 'Уникальный токен для отслеживания утверждений.';
$string['privacy:metadata:local_assign_ai_pending:assignmentid'] = 'Задание, к которому относится эта обратная связь ИИ.';
$string['privacy:metadata:local_assign_ai_pending:courseid'] = 'Курс, связанный с этой обратной связью.';
$string['privacy:metadata:local_assign_ai_pending:grade'] = 'Предложенная оценка, созданная ИИ.';
$string['privacy:metadata:local_assign_ai_pending:message'] = 'Сообщение обратной связи, созданное ИИ.';
$string['privacy:metadata:local_assign_ai_pending:rubric_response'] = 'Обратная связь по рубрике, созданная ИИ.';
$string['privacy:metadata:local_assign_ai_pending:status'] = 'Статус утверждения обратной связи.';
$string['privacy:metadata:local_assign_ai_pending:title'] = 'Заголовок сгенерированной обратной связи.';
$string['privacy:metadata:local_assign_ai_pending:userid'] = 'Пользователь, для которого была создана обратная связь ИИ.';
$string['processed'] = 'Успешно обработано отправок: {$a}.';
$string['processing'] = 'Обработка';
$string['processingerror'] = 'Произошла ошибка при обработке проверки ИИ.';
$string['qualify'] = 'Оценить';
$string['queued'] = 'Все отправки помещены в очередь для проверки ИИ. Они будут обработаны в ближайшее время.';
$string['reloadpage'] = 'Перезагрузите страницу, чтобы увидеть обновленные результаты.';
$string['require_approval'] = 'Проверить ответ ИИ';
$string['review'] = 'Проверить';
$string['reviewall'] = 'Проверить все';
$string['reviewwithai'] = 'Проверка с ИИ';
$string['rubricfailed'] = 'Не удалось вставить рубрику после 20 попыток';
$string['rubricmustarray'] = 'Ответ рубрики должен быть массивом.';
$string['rubricsuccess'] = 'Рубрика успешно вставлена';
$string['save'] = 'Сохранить';
$string['saveapprove'] = 'Сохранить и утвердить';
$string['status'] = 'Статус';
$string['statusapprove'] = 'Утверждено';
$string['statuspending'] = 'Ожидает';
$string['statusrejected'] = 'Отклонено';
$string['submission_draft'] = 'Черновик';
$string['submission_new'] = 'Новое';
$string['submission_none'] = 'Нет отправки';
$string['submission_submitted'] = 'Отправлено';
$string['submittedfiles'] = 'Отправленные файлы';
$string['task_process_ai_queue'] = 'Обработать отложенную очередь Assign AI';
$string['unexpectederror'] = 'Произошла непредвиденная ошибка: {$a}';
$string['usedelay'] = 'Использовать отложенную проверку';
$string['usedelay_help'] = 'Если включено, проверка с помощью ИИ будет выполнена после настраиваемой задержки, а не сразу.';
$string['viewdetails'] = 'Просмотреть детали';
