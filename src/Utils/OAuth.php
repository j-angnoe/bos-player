<?php

namespace BOS\Player\Utils;

class OAuth {
    // must start with a slash!
    static protected $globalCallbackPath = '/oauth/return';
    static protected $cookieName = 'oauth-return';

    static function getGlobalCallbackUrl() {
        return $_SERVER['SERVER_URL'] . static::$globalCallbackPath;
    }

    static function deliverTo($url) {
        setcookie(
            static::$cookieName,
            $url,
            time() + 5 * 60,
            '/'
        );
    }
    static function middleware() {
        if (strpos($_SERVER['REQUEST_URI'], static::$globalCallbackPath) === 0) {
            if (isset($_COOKIE[static::$cookieName])) {
                $deliverTo = $_COOKIE[static::$cookieName];
                
                // @fixme - this cookie value should be decrypted using 
                // a bos-player router key.
                setcookie(static::$cookieName, '', -1,'/');

                // We must also forward the query parameters, thats why we str_replace instead of
                // forwarding to /$deliverTo
                header('Location: ' . str_replace(static::$globalCallbackPath, $deliverTo, $_SERVER['REQUEST_URI']));
                exit;
            }
        }
    }
}