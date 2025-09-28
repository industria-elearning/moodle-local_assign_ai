<?php
namespace local_assign_ai;

class utils {

    private static $unwanted = [
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U',
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'ñ'=>'n','Ñ'=>'N'
    ];

    /**
     * Quitar acentos y caracteres especiales manteniendo UTF-8.
     */
    public static function remove_accents($text) {
        return strtr($text, self::$unwanted);
    }


    /**
     * Normaliza el payload recorriendo todos sus valores.
     */
    public static function normalize_payload(array $payload) {
        array_walk_recursive($payload, function (&$item) {
            if (is_string($item)) {
                $item = self::remove_accents($item);
            }
        });
        return $payload;
    }

}
