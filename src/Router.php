<?php

namespace Odahcam\Http;

use \Psr\Http\Message\ServerRequestInterface as Request;

class Router
{
    /**
     * @var Holds the path to the controllers folder.
     */
    protected $URL_BASE;
    /**
     * @var {bool} If _request was processedor not.
     */
    protected $IS_REQUEST_PROCCESSED;
    /**
     * @var {string} The path to the controllers folder.
     */
    protected $controller_path;
    /**
     * @var {class} The default controller class, could be index.
     */
    protected $controller_default;
    /**
     * @var {string} The path to the controllers folder.
     */
    protected $controller_namespace = '\Controller\\';

    /**
     * Class constructor.
     */
    public function __construct(string $controller_path)
    {
        // self::buffer_start();

        $this->controller_path = $controller_path;
    }

    /**
     * @return {string}
     */
    public function get_base_url()
    {
        return $this->URL_BASE;
    }

    /**
     * @param {string} $url
     * @return {}
     */
    public function set_base_url(string $url)
    {
        $this->URL_BASE = $url;
    }

    /**
     * @param {string} $url
     * @return {string}
     * @throws \Exception
     */
    private static function get_request_uri_from_url(string $url)
    {
        if (!$this->URL_BASE) {
            throw new \Exception('URL base não definida. Não é possível recuperar o URI da requisição precisamente sem a URL base.', 1);
        }

        return str_replace(trim($this->URL_BASE, '/'), '', trim($url, '/'));
    }

    /**
     * @param {string} $uri
     * @return {array}
     */
    private static function get_segmented_request_uri(string $url)
    {
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
    public static function buffer_start(callable $output_callback = null, $chunk_size = 0, $flags = PHP_OUTPUT_HANDLER_STDFLAGS)
    {
        return ob_start($output_callback, $chunk_size, $flags);
    }

    /**
    * Stops the PHP buffer, so the response are free to go.
    * @author odahcam
    * @see http://php.net/manual/en/function.ob-end-flush.php
    * @return Returns TRUE on success or FALSE on failure. Reasons for failure are first that you called the function without an active buffer or that for some reason a buffer could not be deleted (possible for special buffer).
    */
    public static function buffer_stop()
    {
        return ob_end_flush();
    }

    /**
     * Function that removes double slashes _from strings.
     */
    public static function remove_double_slashes(string $url)
    {
        return preg_replace('~(?<!\:)/{2}~', '/', $url);
    }

    /**
    * Loads and sets a controller to run based on a friendly _request REQUEST_URI.
    *
    * @author odahcam
    *
    * @param {string} $request_path_segments_path
    *
    * @return bool
    **/
    public function resolve(Request $request)
    {
        $request_path = $request->getUri()->getPath();
        $request_path_segments = self::get_segmented_request_uri($request_path);

        if (empty($request_path_segments)) {
            return null; // resolves to nothing.
        }

        /**
         * @var {string} $path : The class file path.
         */
        $path = $this->controller_path;

        foreach ($request_path_segments as $key => $segment) {
            $path .= str_replace('-', '_', ucfirst($segment)); // treats segment name

            // debug($path, false, true);

            unset($request_path_segments[$key]);

            switch (true) {

                // NOTE: respeitar a ordem dos cases é importante para o resultado final

                case is_file($path.'.php'):
                    $class_path = $this->controller_namespace.ucfirst($segment);

                    try {
                        if (class_exists($class_path)) {
                            $controller_obj = new $class_path();
                        }
                    } catch (Exception $e) {
                        die('Houve um erro ao carregar o Controller '.$controller_obj.': '.$e->getMessage());
                    }

                    // die(var_dump($controller_obj));

                    if ($controller_obj) {
                        $key_next = $key + 1;

                        /**
                         * @var {string} $action : The controller's method name, this is the action to be taken.
                         */
                        $action = isset($request_path_segments[$key_next]) && !empty($request_path_segments[$key_next]) ? $request_path_segments[$key_next] : 'index';

                        unset($request_path_segments[$key_next]);

                        // debug($controller_obj, false, true);

                        if (method_exists($controller_obj, $action)) {

                            try {
                                // stops buffer and let the user be free
                                // return self::buffer_stop() && call_user_func_array([$controller_obj, $action], $request_path_segments);
                                return call_user_func_array([$controller_obj, $action], $request_path_segments);
                            } catch (Exception $e) {
                                die('Houve um erro ao carregar o método '.$action.' no controller '.$class_path.': '.$e->getMessage());
                            }
                        } else {
                            header("HTTP/1.0 404 Not Found");
                            // return self::buffer_stop();
                            exit;
                        }
                    } else {
                        header("HTTP/1.0 404 Not Found");
                        // return self::buffer_stop();
                        exit;
                    }

                break 2;

                case is_dir($path):
                    $path .= DIRECTORY_SEPARATOR;
                    break;

                default:
                    header('HTTP/1.0 404 Not Found');
                    // return self::buffer_stop();
                    exit;

            }

            // debug($path);
        }
    }
}
