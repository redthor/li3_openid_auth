<?php

namespace li3_openid_auth\controllers;

use lithium\security\Auth;
use lithium\analysis\Debugger;

class OpenIdSessionsController extends \lithium\action\Controller {

    public function add() {
        $error = false;
        if ($this->request->data) {
            if (Auth::check('openid', $this->request)) {
                return $this->redirect('/');
            }

            $error = true;
        }

        return compact('error');
    }

    /* ... */

    public function delete() {
        Auth::clear('openid');
        return $this->redirect('/');
    }
}

?>
