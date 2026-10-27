<?php
// config/env.php - Lightweight & Secure Native Environment (.env) Loader

if (!function_exists('load_env')) {
    function load_env($file_path = null) {
        if ($file_path === null) {
            $file_path = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }

        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            // Split by first =
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Remove quotes if present
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Strip trailing comments if unquoted
            if (strpos($value, ' #') !== false) {
                $parts = explode(' #', $value);
                $value = trim($parts[0]);
            }

            // Normalize boolean and null
            $low = strtolower($value);
            if ($low === 'true') {
                $parsed_val = true;
            } elseif ($low === 'false') {
                $parsed_val = false;
            } elseif ($low === 'null') {
                $parsed_val = null;
            } else {
                $parsed_val = $value;
            }

            $_ENV[$key] = $parsed_val;
            $_SERVER[$key] = $parsed_val;
            if (is_string($parsed_val)) {
                putenv("{$key}={$parsed_val}");
            }
        }

        return true;
    }
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        if ($val !== false) {
            $low = strtolower($val);
            if ($low === 'true') return true;
            if ($low === 'false') return false;
            if ($low === 'null') return null;
            return $val;
        }
        return $default;
    }
}

// Auto-load .env when this file is included
load_env();
