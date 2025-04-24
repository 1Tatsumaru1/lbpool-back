<?php

namespace LBPool\Utils;

use DateTime;

class Verificator {

    public static function verifyText(mixed $data, ?int $nb_char_min = NULL, ?int $nb_char_max = NULL) {
        if (!isset($data)) return NULL;
        $data = $data ?? NULL;
        if ($data == NULL) return NULL;
        $text = trim(htmlspecialchars($data, ENT_COMPAT|ENT_SUBSTITUTE));
        $min_compliant = ($nb_char_min == NULL) ? true : (strlen($text) >= $nb_char_min);
        $max_compliant = ($nb_char_max == NULL) ? true : (strlen($text) <= $nb_char_max);
        if ($min_compliant && $max_compliant) {
            return $text;
        }
        return NULL;
    }

    public static function verifyPassword(mixed $password, ?int $nb_char_min = NULL, ?int $nb_char_max = NULL) {
        if (!isset($password)) return NULL;
        $password = $password ?? NULL;
        $password = trim(htmlspecialchars($password, ENT_COMPAT|ENT_SUBSTITUTE));
        $min_compliant = ($nb_char_min == NULL) ? true : (strlen($password) >= $nb_char_min);
        $max_compliant = ($nb_char_max == NULL) ? true : (strlen($password) <= $nb_char_max);
        $hasLowercase = preg_match('/[a-zàáâäãåçèéêëìíîïñòóôöõùúûüýÿ]/u', $password);
        $hasUppercase = preg_match('/[A-ZÀÁÂÄÃÅÇÈÉÊËÌÍÎÏÑÒÓÔÖÕÙÚÛÜÝ]/u', $password);
        $hasDigit = preg_match('/\d/', $password);
        $hasSpecialChar = preg_match('/[\W_]/', $password);
        if ($min_compliant && $max_compliant && $hasLowercase && $hasUppercase && $hasDigit && $hasSpecialChar) {
            return $password;
        }
        return NULL;
    }
    

    public static function verifyEmail(mixed $data) {
        if (!isset($data)) return NULL;
        $data = $data ?? NULL;
        if ($data == NULL) return NULL;
        $sanitized_email = filter_var($data, FILTER_SANITIZE_EMAIL);
        $validated_email = filter_var($sanitized_email, FILTER_VALIDATE_EMAIL);
        if ($validated_email == false) {
            return NULL;
        }
        return $validated_email;
    }

    public static function verifyInt(mixed $data, ?int $min_val = NULL, ?int $max_val = NULL) {
        if ($data === NULL) return NULL;
        $sanitized_number = filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        $validated_number = filter_var($sanitized_number, FILTER_VALIDATE_INT);
        if ($validated_number === false) return NULL;
        $number = (int)$validated_number;
        $min_compliant = ($min_val == NULL) ? true : ($number >= $min_val);
        $max_compliant = ($max_val == NULL) ? true : ($number <= $max_val);
        if ($min_compliant && $max_compliant) {
            return $number;
        }
        return NULL;
    }

    public static function verifyUuid(mixed $data) {
        if (!isset($data)) return NULL;
        $data = $data ?? NULL;
        if ($data == NULL) return NULL;
        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        if (preg_match($uuidRegex, $data)) {
            return htmlspecialchars($data, ENT_COMPAT|ENT_SUBSTITUTE);
        }
        return NULL;
    }

    public static function verifyDate(mixed $data, ?\DateTime $min_date = NULL, ?\DateTime $max_date = NULL) {
        if (!isset($data)) return NULL;
        $data = $data ?? NULL;
        if ($data == NULL) return NULL;
        $text = trim(htmlspecialchars($data, ENT_COMPAT|ENT_SUBSTITUTE));
        try{
            $date = new \DateTime($text);
        } catch (\Exception $e) {
            return NULL;
        }
        $min_compliant = ($min_date == NULL) ? true : ($date >= $min_date);
        $max_compliant = ($max_date == NULL) ? true : ($date <= $max_date);
        if ($min_compliant && $max_compliant) {
            return $date;
        }
        return NULL;
    }

    public static function hasNullValue(...$variables) {
        return in_array(NULL, $variables, true);
    }

    public static function isWithinMinutes(\DateTime $datetime1, \DateTime $datetime2, int $intervalInMinutes) {
        $diff = $datetime1->diff($datetime2);
        $diff_minutes = ($diff->h * 60) + $diff->i;
        return ($diff_minutes < $intervalInMinutes);
    }

}