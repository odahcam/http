<?php

namespace Odahcam\Helper;

/**
 * @author odahcam
 */
class TroubleHandler
{
    public function __construct()
    {
        /*
         * Pré definição para exibição de erros.
         * Grante que todo e qualquer erro seja exibido
         * antes que as definições de exibição sejam ajustadas.
         */
        ini_set('error_log', 'error.php.log');

        ini_set('log_errors', 'On');

        $display_errors = IS_DEV ? 'On' : 'Off';

        ini_set('display_errors', $display_errors); // permite que sejam exibidos erros
        ini_set('display_startup_errors', $display_errors);

        error_reporting(-1);

        $html_errors = (int) !IS_CLI;

        ini_set('html_errors', $html_errors);

        // set to the user defined error handler
        $this->old_error_handler = set_error_handler([$this, 'error_handler']);
        register_shutdown_function([$this, 'shutdown_handler']);
    }

    public function error_handler($errno, $errstr, $errfile, $errline)
    {
        //don't display error if no error number
        if (!(error_reporting() & $errno)) {
            return;
        }

        //display errors according to the error number
        switch ($errno) {
            case E_USER_ERROR:
                echo <<<HTML
<b>ERROR</b> [{$errno}] {$errstr}<br />
Fatal error on line {$errline} in file {$errfile},
PHP ${PHP_VERSION} (${PHP_OS})<br />
Aborting...<br />
HTML;
                exit(1);
                break;

            case E_USER_WARNING:
                echo "<b>WARNING</b> [{$errno}] {$errstr}<br />\n";
                break;

            case E_USER_NOTICE:
                echo "<b>NOTICE</b> [{$errno}] {$errstr}<br />\n";
                break;

            default:
                echo "<b>UNKNOWN ERROR</b> [{$errno}] {$errstr}<br />\n";
                break;
        }

        // don't execute PHP internal error handler
        return true;
    }

    /**
     * Shutdown Handler
     */
    private function shutdown_handler()
    {
        if (is_array($error = error_get_last())) {
            // echo '<h1>PHP has shutdown!</h1>';
            var_dump($error);
            return call_user_func_array([$this, 'error'], $error);
        }
        // else {
        //     echo '<p>Impossible to collect error data.</p>';
        // }

        // return true;
    }

    /**
     * Debug certain variable and tells it's origin.
     *
     * @author Luiz Barni <machado@odahcam.com>
     *
     * @param {any}  $any : Any kind of data.
     * @param {callable} $callback : a callable.
     *
     * @return {void}
     */
    public static function debug_html($any, callable $callback = null)
    {
        $whitelist = [
            // '179.107.80.222',
        ];

        if (in_array($_SERVER['REMOTE_ADDR'], $whitelist) || IS_DEV) {
            $bt = debug_backtrace();
            $caller = array_shift($bt);

            echo <<<HTML
<pre>
<span>(line {$caller['line']}) {$caller['file']}:</span>
<code>
    {print_r($any)}
</code>
</pre>
HTML;

            if (!!$callback && function_exists($callback)) {
                $callback();
            } else {
                switch ($callback) {
                    case 'die': die;
                    case 'exit': exit;
                    default: break;
                }
            }
        }

        return;
    }

    private function __destruct()
    {
        set_error_handler($this->old_error_handler);
    }
}
