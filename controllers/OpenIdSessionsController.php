<?php

namespace li3_openid_auth\controllers;

use lithium\security\Auth;
use lithium\analysis\Debugger;

class OpenIdSessionsController extends \lithium\action\Controller {

    public function add() {
        echo Debugger::export($this->request->data);
        if ($this->request->data && Auth::check('openid', $this->request)) {
            return $this->redirect('/');
        }
        // Handle failed authentication attempts
    }

    /* ... */

    public function delete() {
        Auth::clear('openid');
        return $this->redirect('/');
    }
}

?>
