<?php

namespace Odahcam\Router;

class Resolver
{
    protected $CONTROLLERS_PATH;
    protected $URL_BASE;

    protected $REQUEST_PROCCESSED;

    public function __construct(string $controllers_path)
    {
        // self::startBuffer();

        $this->CONTROLLERS_PATH = $controllers_path;

        $this->proccess_request();
    }

    public static function removeDoubleSlashes(string $url)
    {
        return preg_replace('~(?<!\:)/{2}~', '/', $url);
    }

    public function proccess_request()
    {
        if (isset($_SERVER)) {
            $this->IS_SERVER = true;

            $url_http_protocol = 'http'.(isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 's' : null);
            $url_http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $url_port = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80' ? ':'.$_SERVER['SERVER_PORT'] : '';
            // $url_request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

            list($url_base_uri, $url_request_uri) = explode('index.php', $_SERVER['PHP_SELF']);

            $url_server = $url_http_protocol.'://'.$url_http_host.$url_port.'/'; // server domain URL
            // remove double slashes (//)
            $url_base = self::removeDoubleSlashes($url_server.$url_base_uri);
            $url_request = self::removeDoubleSlashes($url_base.$url_request_uri);

            $this->URL_BASE = $url_base;
            $this->URL_REQUEST = $url_request;
            $this->URI = $url_request_uri;
        } else {
            $this->IS_SERVER = false;

            trigger_error('A constante $_SERVER não está definida, isto significa que a aplicação não está rodando em um servidor, então não será possível gerar constantes de URL.', E_USER_WARNING);
        }

        $this->REQUEST_PROCCESSED = true;
    }

    public function getBaseUrl()
    {
        return $this->URL_BASE;
    }

    private static function getRequestURI(string $url)
    {
        // debug(trim(URL_BASE, '/'));
        // debug(trim($url, '/'));
        // debug(str_replace(trim(URL_BASE, '/'), '', trim($url, '/')));

        return str_replace(trim($this->URL_BASE, '/'), '', trim($url, '/'));
    }


    private static function getSegmentedRequestURI(string $url)
    {
        // debug(trim(URL_BASE, '/'));
        // debug(trim($url, '/'));
        // debug(str_replace(trim(URL_BASE, '/'), '', trim($url, '/')));

        return array_values(array_filter(explode('/', $url)));
    }

    /**
    * Inicia um buffer (acumulador) que armazena todo o output do servidor e impede que o mesmo seja enviado como response ao requisitante (e.g.: browser)
    * @see http://php.net/manual/en/function.ob-start.php
    * @author odahcam
    * @param {callable} $output_callback = NULL
    * @param {int} $chunk_size = 0
    * @param {int} $flags = PHP_OUTPUT_HANDLER_STDFLAGS
    * @return Returns TRUE on success or FALSE on failure.
    */
    public static function startBuffer(callable $output_callback = null, $chunk_size = 0, $flags = PHP_OUTPUT_HANDLER_STDFLAGS)
    {
        return ob_start($output_callback, $chunk_size, $flags);
    }

    /**
    * Stops the PHP buffer, so the response are free to go.
    * @author odahcam
    * @see http://php.net/manual/en/function.ob-end-flush.php
    * @return Returns TRUE on success or FALSE on failure. Reasons for failure are first that you called the function without an active buffer or that for some reason a buffer could not be deleted (possible for special buffer).
    */
    public static function stopBuffer()
    {
        return ob_end_flush();
    }

    /**
    * Loads and sets a controller to run based on a friendly request URI.
    *
    * @author odahcam
    *
    * @param {string} $url
    *
    * @return bool
    **/
    public function resolve()
    {
        $request = self::getSegmentedRequestURI($this->URI);
        $path = $this->CONTROLLERS_PATH;

        if (empty($request)) {

            // empty routes should open a default controller or give a 404
            $controller = new \Controller\Home;
            // return self::stopBuffer() && $controller->index();
            return $controller->index();

        } elseif ($request[0] == '404') {
            header('HTTP/1.0 404 Not Found');
            // return self::stopBuffer();
            exit;
        }

        foreach ($request as $key => $segment) {
            $path .= str_replace('-', '_', ucfirst($segment)); // treats segment name

            // debug($path, false, true);

            unset($request[$key]);

            switch (true) {
                case is_file($path.'.php'):
                    $ControllerName = '\\Controller\\'.ucfirst($segment); // $request[$key]

                    // $arguments = $request; // the request from here

                    try {
                        $Controller = class_exists($ControllerName) ? new $ControllerName() : false;
                    } catch (Exception $e) {
                        die('Houve um erro ao carregar o Controller '.$Controller.': '.$e->getMessage());
                    }

                    // die(var_dump($Controller));

                    if ($Controller) {
                        $key_next = $key + 1;
                        $method = isset($request[$key_next]) && !empty($request[$key_next]) ? $request[$key_next] : 'index';
                        unset($request[$key_next]);

                        // debug($Controller, false, true);

                        if (method_exists($Controller, $method)) {

                                // header("HTTP/1.0 200 OK");
                            // header("HTTP/1.1 200 OK");

                            try {
                                // stops buffer and let the user be free
                                // return self::stopBuffer() && call_user_func_array([$Controller, $method], $request);
                                return call_user_func_array([$Controller, $method], $request);
                            } catch (Exception $e) {
                                die('Houve um erro ao carregar o método '.$method.' no controller '.$ControllerName.': '.$e->getMessage());
                            }
                        } else {
                            header("HTTP/1.0 404 Not Found");
                            // return self::stopBuffer();
                    exit;
                        }
                    } else {
                        header("HTTP/1.0 404 Not Found");
                        // return self::stopBuffer();
                    exit;
                    }

                break 2;

                case is_dir($path):
                    $path .= DIRECTORY_SEPARATOR;
                    break;

                default:
                    header('HTTP/1.0 404 Not Found');
                    // return self::stopBuffer();
                    exit;

            }

            // debug($path);
        }

    }

    /**
    * Allows the request to be temporary redirected
    */
    public static function redirect(string $url, $status = 302)
    {
        header("Location: ".$this->URL_BASE.$url, true, $status);
    }
}
