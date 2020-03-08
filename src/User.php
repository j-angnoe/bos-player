<?php

namespace BOS\Player;

class User {
    static $instance;

    protected $user;

    function __construct() {
        // php 5.4+
        // this function is not without side-effects ;-)

        if (isset($_ENV['SKIP_SESSION_START'])) {
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
        } else {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
        }

        if (isset($_SESSION['BOS']['User'])) {
            $this->user = $_SESSION['BOS']['User'];
        } else {
            $this->user = false;
        }
    }

    static function getInstance() {
        if (!self::$instance) {
            self::$instance = new User();
        }    

        return self::$instance;
    }

    static function isLogged() {
        $instance = self::getInstance();

        return !!$instance->getUser();
    }

    static function getUser() {
        $instance = self::getInstance();

        if (isset($instance->getUserCallback)) {
            return call_user_func($instance->getUserCallback);
        }

        return $instance->user;
    }

    static function id() {
        return static::getUser()['id'];
    }

    static function setUser($userData) {
        $instance = static::getInstance();
        if ($userData instanceof \Closure) {
            $instance->getUserCallback = $userData;
            return;
        }

        $userData['started_at'] = date('Y-m-d H:i:s');

        $instance->user = $userData;
        $_SESSION['BOS']['User'] = $userData;
    }

    static function logoutUser() {
        $instance = static::getInstance();
        $instance->user = null;

        unset($_SESSION['BOS']['User']);
    }

    // @fixme - put a proper token here...
    static private $admin_key = 'sdfkjasdflkjfaldfd';

    static function declareUserIsAdmin() {
        $instance = static::getInstance();

        $instance->user['admin_token'] = password_hash(static::$admin_key, PASSWORD_DEFAULT);

        $_SESSION['BOS']['User'] = $instance->user;
    }

    // Is the user admin?
    static function isAdmin() {
        $instance = static::getInstance();

        if (isset($instance->user['admin_token'])) {
            return password_verify(static::$admin_key, $instance->user['admin_token']);
        } 
        return false;
    }


}

