<?php
namespace local_assign_ai\rubric;

class rubric_helper {
    public static function get_rubric($cmid) {
        global $DB;

        $context = \context_module::instance($cmid);
        $gradingareas = $DB->get_records('grading_areas', ['contextid' => $context->id]);

        if (!$gradingareas) {
            return null;
        }

        $area = reset($gradingareas);
        $definition = $DB->get_record('grading_definitions', ['areaid' => $area->id]);

        if (!$definition) {
            return null;
        }

        $criteria = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $definition->id]);

        $rubric = [
            'title' => $definition->name,
            'description' => $definition->description,
            'criteria' => []
        ];

        foreach ($criteria as $criterion) {
            $levels = $DB->get_records('gradingform_rubric_levels', ['criterionid' => $criterion->id]);
            $crit = ['criterion' => $criterion->description, 'levels' => []];
            foreach ($levels as $level) {
                $crit['levels'][] = [
                    'points' => (int) $level->score,
                    'description' => $level->definition
                ];
            }
            $rubric['criteria'][] = $crit;
        }

        return $rubric;
    }
}
