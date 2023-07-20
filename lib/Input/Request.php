<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 * @author     mikespub
 */

namespace SebLucas\Cops\Input;

/**
 * Summary of Request
 */
class Request
{
    /**
     * Summary of urlParams
     * @var array
     */
    public $urlParams = [];

    public function __construct()
    {
        $this->init();
    }

    /**
     * Summary of useServerSideRendering
     * @return bool|int
     */
    public function render()
    {
        global $config;
        return preg_match('/' . $config['cops_server_side_render'] . '/', self::agent());
    }

    /**
     * Summary of query
     * @return mixed
     */
    public function query()
    {
        if (isset($_SERVER['QUERY_STRING'])) {
            return $_SERVER['QUERY_STRING'];
        }
        return "";
    }

    /**
     * Summary of agent
     * @return mixed
     */
    public function agent()
    {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        }
        return "";
    }

    /**
     * Summary of init
     * @return void
     */
    public function init()
    {
        $this->urlParams = [];
        if (!empty($_GET)) {
            foreach ($_GET as $name => $value) {
                $this->urlParams[$name] = $_GET[$name];
            }
        }
    }

    /**
     * Summary of get
     * @param mixed $name
     * @param mixed $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        if (!empty($this->urlParams) && isset($this->urlParams[$name]) && $this->urlParams[$name] != '') {
            return $this->urlParams[$name];
        }
        return $default;
    }

    /**
     * Summary of set
     * @param mixed $name
     * @param mixed $value
     * @return void
     */
    public function set($name, $value)
    {
        $this->urlParams[$name] = $value;
    }

    /**
     * Summary of option
     * @param mixed $option
     * @return mixed
     */
    public function option($option)
    {
        global $config;
        if (isset($_COOKIE[$option])) {
            if (isset($config ['cops_' . $option]) && is_array($config ['cops_' . $option])) {
                return explode(',', $_COOKIE[$option]);
            } elseif (!preg_match('/[^A-Za-z0-9\-_.@]/', $_COOKIE[$option])) {
                return $_COOKIE[$option];
            }
        }
        if (isset($config ['cops_' . $option])) {
            return $config ['cops_' . $option];
        }

        return '';
    }

    /**
     * Summary of style
     * @return string
     */
    public function style()
    {
        global $config;
        $style = self::option('style');
        if (!preg_match('/[^A-Za-z0-9\-_]/', $style)) {
            return 'templates/' . self::template() . '/styles/style-' . self::option('style') . '.css';
        }
        return 'templates/' . $config['cops_template'] . '/styles/style-' . $config['cops_template'] . '.css';
    }

    /**
     * Summary of template
     * @return mixed
     */
    public function template()
    {
        global $config;
        $template = self::option('template');
        if (!preg_match('/[^A-Za-z0-9\-_]/', $template)) {
            return $template;
        }
        return $config['cops_template'];
    }

    /**
     * Summary of verifyLogin
     * @return bool
     */
    public static function verifyLogin($serverVars = null)
    {
        global $config;
        $serverVars ??= $_SERVER;
        if (isset($config['cops_basic_authentication']) &&
          is_array($config['cops_basic_authentication'])) {
            if (!isset($serverVars['PHP_AUTH_USER']) ||
              (isset($serverVars['PHP_AUTH_USER']) &&
                ($serverVars['PHP_AUTH_USER'] != $config['cops_basic_authentication']['username'] ||
                  $serverVars['PHP_AUTH_PW'] != $config['cops_basic_authentication']['password']))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Summary of notFound
     * @return void
     */
    public static function notFound()
    {
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        header('Status: 404 Not Found');

        $_SERVER['REDIRECT_STATUS'] = 404;
    }

    /**
     * Summary of build
     * @param array $params
     * @param ?array $server
     * @param ?array $cookie
     * @param ?array $config
     * @return Request
     */
    public static function build($params, $server = null, $cookie = null, $config = null)
    {
        // ['db' => $db, 'page' => $pageId, 'id' => $id, 'query' => $query, 'n' => $n]
        $request = new self();
        $request->urlParams = $params;
        return $request;
    }
}
