<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

use lithium\net\http\Router;
use lithium\core\Libraries;

$defaultConfig = array(
    'login' => array(
        '/login' => array(
            'OpenIdSessions' => 'add'
        )
    ),
    'logout' => array(
        '/logout' => array(
            'OpenIdSessions' => 'delete'
        )
    ),
);

// Override config
if ($actionConfig = Libraries::get('li3_openid_auth', 'ActionConfig')) {
    foreach (array_keys($actionConfig) as $action) {
        $defaultConfig[$action] = $actionConfig[$action];
    }
}

foreach (array_keys($defaultConfig) as $action) {
    $url  = key($defaultConfig[$action]);
    $ctrl = key($defaultConfig[$action][$url]);
    $act  = $defaultConfig[$action][$url][$ctrl];
    Router::connect($url, $ctrl . '::' . $act);
}

//Router::connect('/dprlogin', 'Sessions::add');
//Router::connect('/logout', 'Sessions::delete');

?>
