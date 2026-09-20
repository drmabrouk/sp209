<?php
/**
 * Dubai Local Date & Time Utility Class
 * Ensures all dates/times use Asia/Dubai timezone explicitly.
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_DateTime {

    public static function get_timezone() {
        return new DateTimeZone('Asia/Dubai');
    }

    public static function now($format = 'Y-m-d H:i:s') {
        $date = new DateTime('now', self::get_timezone());
        return $date->format($format);
    }

    public static function today($format = 'Y-m-d') {
        $date = new DateTime('now', self::get_timezone());
        return $date->format($format);
    }

    public static function format($datetime_str, $format = 'Y-m-d H:i:s') {
        if (empty($datetime_str)) {
            return '—';
        }
        try {
            $date = new DateTime($datetime_str, self::get_timezone());
            return $date->format($format);
        } catch (Exception $e) {
            return $datetime_str;
        }
    }
}
