<?php

namespace Src;

use Exception;

class Log
{
    public static function log(mixed $text): void
    {
        $date = date('Y-m-d H:i:s');
        if (is_array($text)) {
            $data = print_r($text, true);
            $content = <<<EOT
                ------------------------------------------------------------------------------------------------------------------------
                [$date]
                $data
                ------------------------------------------------------------------------------------------------------------------------
                EOT;
        } else {
            $content = '[' . $date . '] ' . $text . "\n";
        }
        file_put_contents(STORAGE . '/info' . '.log', $content, FILE_APPEND);
    }

    public static function error(Exception $e, mixed $record = null): void
    {
        $time = date('Y-m-d H:i:s');
        $message = $e->getMessage();
        $error_place = str_replace(dirname(__DIR__, 2), '.', $e->getFile()) . ' ' . $e->getLine();
        $errors = implode(
            "\n",
            array_map(
                fn($trace) => str_replace(dirname(__DIR__, 2), '.', ($trace['file'] ?? 'no_file')) . ' ' . ($trace['line'] ?? 'no_line') . ' ' . ($trace['class'] ?? 'no_class') . ($trace['type'] ?? 'no_type') . ($trace['function'] ?? 'no_function'),
                $e->getTrace()
            )
        );
        if ($record)
            $errors .= "\n\n" . print_r($record, true);
        file_put_contents(
            STORAGE . '/error' . '.log',
            <<<EOT
            ------------------------------------------------------------------------------------------------------------------------
            [$time] $message
            $error_place
            $errors
            ------------------------------------------------------------------------------------------------------------------------
            EOT . "\n",
            FILE_APPEND
        );
    }
}