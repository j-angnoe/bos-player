<?php

require_once __DIR__ . '/../includes.php';

autoloadBosPlayer();

use BOS\Player\RequestAgainstEnvironment;
use BOS\Player\AuthorizesRequest;
use BOS\Player\User;

class MyRequest {
    use AuthorizesRequest;
}

$x = new MyRequest();

// Guest assertions
assertTrue($x->userHasLevel('everyone'));
assertTrue($x->userHasLevel('guest'));


// User assertions
User::setUser([
    'id'=>1
]);
assertTrue(!$x->userHasLevel('guest'));
assertTrue($x->userHasLevel('user'));

// Admin assertions

assertTrue(!$x->userHasLevel('admin'));

// Now it will be admin
User::declareUserIsAdmin();
assertTrue($x->userHasLevel('admin'));


// And logout
User::logoutUser();
assertTrue(!$x->userHasLevel('user'));
assertTrue($x->userHasLevel('guest'));



// now, do some magic with special specifiers:

User::setUser([
    'id' => 2,
    'type' => 'employee',
    'accepted' => 1,
    'is_deleted' => 0
]);

// user has id 2
assertTrue($x->userHasLevel('user:2'));
assertTrue(!$x->userHasLevel('user:1'));

assertTrue($x->userHasLevel('user:accepted'));
assertTrue(!$x->userHasLevel('user:non_existent_unit'));
assertTrue(!$x->userHasLevel('user:is_deleted'));

assertTrue($x->userHasLevel('user:type:employee'));
assertTrue(!$x->userHasLevel('user:type:admin'));


// Access 
$x->environment = new class {
    var $accessMap = [
        'login_mod' => 'guest',
        'admin_mod' => 'admin',
        'special_mod' => 'user:accepted',
        'user_specific_mod' => 'user:3',
        'deny_mod' => 'deny'
    ];
    function provides($x) {
        if ($x === 'authentication') {
            return true;
        }
        return false;
    }
};

// User is currently logged in, so no access to login
assertTrue(!$x->authorize('login_mod', false));

// And we are no admin, so no access here.
assertTrue(!$x->authorize('admin_mod', false));

// but we do have access to the special mod
assertTrue($x->authorize('special_mod', false));

// we have no access to user:3 mod:
assertTrue(!$x->authorize('user_specific_mod', false));

// but if we declare ourselves admin, we do:
User::declareUserIsAdmin();
assertTrue($x->authorize('user_specific_mod', false));

// but even admin wont get access to denied mods:
assertTrue(!$x->authorize('deny_mod', false));
