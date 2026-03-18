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
 * Payload anonymizer for local_assign_ai AI requests.
 *
 * @package     local_assign_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assign_ai\local;

/**
 * Handles anonymization/de-anonymization for AI payloads.
 */
class payload_anonymizer {
    /**
     * Fields that are anonymized before sending data to AI.
     *
     * @var array<string, string>
     */
    private const ANONYMIZED_FIELDS = [
        'student_name' => '[STUDENT_NAME]',
    ];

    /**
     * Anonymize configured payload fields.
     *
     * @param array $payload Original payload.
     * @return array{payload: array, replacements: array<string, string>}
     */
    public static function anonymize(array $payload): array {
        $replacements = [];

        foreach (self::ANONYMIZED_FIELDS as $field => $placeholder) {
            if (isset($payload[$field]) && is_string($payload[$field]) && $payload[$field] !== '') {
                $replacements[$placeholder] = $payload[$field];
                $payload[$field] = $placeholder;
            }
        }

        return [
            'payload' => $payload,
            'replacements' => $replacements,
        ];
    }

    /**
     * Restore anonymized placeholders in AI reply text.
     *
     * @param string $text AI reply text.
     * @param array $replacements Placeholder to original value map.
     * @return string
     */
    public static function deanonymize_text(string $text, array $replacements): string {
        if ($text === '' || empty($replacements)) {
            return $text;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
