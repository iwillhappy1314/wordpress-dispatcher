<?php

namespace Wenprise\Dispatcher;

class Router
{

    /**
     * 功能
     *
     * @param array $url_callbacks
     * @param int   $priority
     */
    static function routes(array $url_callbacks, $priority = 5)
    {

        //In form WordPress that these routes ( rewrites ) exist by adding to the rewrite_rules_array
        add_filter('rewrite_rules_array', function ($rules) use ($url_callbacks)
        {
            return array_reduce(array_keys($url_callbacks), function ($rules, $route)
            {
                $newRule = ['^' . trim($route, '/') . '/?$' => 'index.php?' . static::query_var_name($route) . '=1'];

                return $newRule + $rules;
            }, $rules);
        });

        add_filter('query_vars', function ($query_vars) use ($url_callbacks)
        {
            return array_reduce(array_keys($url_callbacks), function ($query_vars, $route)
            {
                $query_vars[] = static::query_var_name($route);

                return $query_vars;
            }, $query_vars);
        });

        add_action('template_redirect', function () use ($url_callbacks)
        {
            global $wp_query;
            foreach ($url_callbacks as $route => $callback) {
                if ($wp_query->get(static::query_var_name($route))) {
                    $wp_query->is_home = false;
                    $params            = null;
                    preg_match('#' . trim($route, '/') . '#', $_SERVER[ 'REQUEST_URI' ], $params);
                    $res = call_user_func_array($callback, $params);
                    if ($res === false) {
                        static::send_404();
                    } else {
                        exit();
                    }
                }
            }
        }, $priority);

        add_action('init', function () use ($url_callbacks)
        {
            static::maybe_flush_rewrites($url_callbacks);
        }, 99);
    }


    /**
     * 需要时，刷新 URL 重定向规则缓存
     *
     * @param $url_callbacks
     */
    protected static function maybe_flush_rewrites($url_callbacks)
    {
        $current = md5(json_encode(array_keys($url_callbacks)));
        $cached  = get_option(static::class, null);
        if (empty($cached) || $current !== $cached) {
            flush_rewrite_rules();
            update_option(static::class, $current);
        }
    }


    /**
     * 获取查询参数名称
     *
     * @param $route
     *
     * @return mixed
     */
    protected static function query_var_name($route)
    {
        static $cache;
        if ( ! isset($cache[ $route ])) {
            $cache[ $route ] = md5($route);
        }

        return $cache[ $route ];
    }


    /**
     * 发送 404 错误
     */
    protected static function send_404()
    {
        global $wp_query;
        status_header('404');
        $wp_query->set_404();
    }
}
