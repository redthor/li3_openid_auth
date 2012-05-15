<?php
/**
 * OpenId Auth Adapter
 * 
 * @author        Douglas Reith
 * @website       http://douglasreith.com
 * @created       2012-05-12
 * 
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_openid_auth\extensions\adapter\security\auth;

use \lithium\core\Libraries;

/**
 * The `Auth` adapter provides basic authentication facilities for checking credentials submitted
 * via a web form against an LDAP server. To perform an authentication check, the adapter accepts
 * an instance of a `Request` object which contains the submitted form data in its `$data` property.
 *
 * When a request is submitted, the adapter will take the form data from the `Request` object,
 * apply any filters as appropriate (see the `'filters'` configuration setting below), and
 * attempt to connect to an LDAP server using using the filtered data.
 *
 * If successfully connected, it will retrieve the user's account record and return it to be
 * store in the session data.
 *
 * By default it's set to look for an LDAP server locally. You will probably need to in the least, adjust the basedn.
 * You must always pass a "password" field in your request data. It will not be used to assemble the login RDN.
 * You will pass in the `'fields'` key any field from the login form (request data) that need to be assembled into
 * the login RDN. You can optionally pass additional parameters to be assembled in the RDN with the `'targetdn'` key.
 * 
 * An example configuration might look like the following:
 * {{{
 *      Auth::config(array(
 *              'customer' => array(
 *                      'adapter' => 'Ldap',
 *                      'fields' => array('uid'),
 *                      'server' => array(
 *                          'host' => 'ldapserver.com',
 *                          'basedn' => 'o=ldapserver.com',
 *                          'targetdn' => 'ou=People'
 *                      )
 *              )
 *      ));
 * }}}
 *
 * Another example, if the "ou" field needed to be passed in the request data and be able to be changed on the form
 * for some reason or another:
 * {{{
 *      Auth::config(array(
 *              'customer' => array(
 *                      'adapter' => 'Ldap',
 *                      'fields' => array('uid', 'ou'),
 *                      'server' => array(
 *                          'host' => 'ldapserver.com',
 *                          'basedn' => 'o=ldapserver.com'
 *                      )
 *              )
 *      ));
 * }}}
 *
 * To have the "ou" property be set to "People" you would maybe have a drop down with a name of "ou" on your login form.
 * This might switch out the "ou" value for some reason. Perhaps you're selecting a "group" when logging in, etc.
 *
 * An example RDN that might be constructed would look like:
 * uid=Myuser, ou=Person, o=ldapserver.com
 *
 * Then, again, the "password" field would be used with this RDN to login to the LDAP server.
 *
 * As mentioned, prior to any queries being executed, the request data is modified by any filters
 * configured. Filters are callbacks which accept the value of a field as input, and return a
 * modified version of the value as output. Filters can be any PHP callable, i.e. a closure or
 * `array('ClassName', 'method')`. The Ldap auth adapter will filter the password field by default in order
 * to hash it by `lithium\util\String::hash()`. You may or may not need to hash the password before sending to the
 * LDAP server. Typically you won't be with an LDAP server, but any other fields you may need to alter for any reason,
 * you can do so using callbacks that you define.
 *
 * Note that if you are specifying the `'fields'` configuration using key/value pairs, the key
 * used to specify the filter must match the key side of the `'fields'` assignment.
 *
 * @see lithium\net\http\Request::$data
 */
class OpenId extends \lithium\core\Object {

        /**
         * The field to extract from the `Request` object and use when querying open id.
         *
         * @var string
        */
        protected $_field = '';

        /**
         * Callback filters to apply to request data before using it in the authentication query. Each
         * key in the array must match a request field specified in the `$_fields` property, and each
         * value must either be a reference to a function or method name, or a closure. For example, to
         * automatically hash passwords, ex.: `array('password' => array('\lithium\util\String', 'hash'))`.
         *
         * Optionally, you can specify a callback with no key, which will receive (and can modify) the
         * entire credentials array before the query is executed, as in the following example:
         * {{{
         *      Auth::config(array(
         *              'members' => array(
         *                      'adapter' => 'Ldap',
         *                      'fields' => array('uid'),
         *                      'filters' => array(function($data) {
         *                              // If the user is outside the company, then their account must have the
         *                              // 'private' field set to true in order to log in:
         *                              if (!preg_match('/@mycompany\.com$/', $data['email'])) {
         *                                      $data['private'] = true;
         *                              }
         *                              return $data;
         *                      })
         *              )
         *      ));
         * }}}
         *
         * @see app\extensions\auth\adapter\Ldap::$_fields
         * @var array
        */
        protected $_filters = array();
        
        /**
         * The OpenId domain
         * 
         * @var string
        */
        protected $_domain = array();
        
        /**
         * List of configuration properties to automatically assign to the properties of the adapter
         * when the class is constructed.
         *
         * @var array
        */
        protected $_autoConfig = array('field', 'domain');
        
        /**
         * The open ID resource
         *
        */
        protected $openId;
        
        /**
         * Sets the initial configuration for the `Ldap` adapter, as detailed below.
         *
         * @see app\extensions\auth\adapter\Ldap::$_server
         * @see app\extensions\auth\adapter\Ldap::$_fields
         * @see app\extensions\auth\adapter\Ldap::$_filters
         * @param array $config Sets the configuration for the adapter, which has the following options:
         *              - `'server'` _array_: The LDAP server information required to connect including the basedn.
         *              - `'fields'` _array_: The fields to use for the login RDN.
         *                See the `$_fields` property for details.
         *              - `'filters'` _array_: Named callbacks to apply to request data before the user
         *                login request is made. See the `$_filters` property for more details.
        */
        public function __construct(array $config = array()) {
                $defaults = array(
                        'filters' => array(),
                        'field' => 'openid_identifier',
                        'domain' => 'localhost'
                );
                
                parent::__construct($config + $defaults);
        }

        /**
         * Initializes values configured in the constructor.
         *
         * @return void
         */
        protected function _init() {
                parent::_init();

                $this->openId = new \lightopenid\LightOpenID($this->_domain);
        }


        /**
         * Called by the `Auth` class to run an authentication check against an LDAP server using the
         * credientials in a data container (a `Request` object), and returns an array of user
         * information on success, or `false` on failure.
         *
         * @param object $credentials A data container which wraps the authentication credentials used
         *               to login to an LDAP server (usually a `Request` object). See the documentation 
         *               for this class for further details.
         * @param array $options Additional configuration options. The basedn or targetdn could be changed for example.
         * @return array Returns an array containing user information on success, or `false` on failure.
         */
        public function check($credentials, array $options = array()) {
            $conditions = $this->_filters($credentials->data);
            $options += $this->_config;

            try {
                if (!$this->openId->mode) {
                    if(isset($_POST[$this->_field])) {
                        $this->openId->identity = $_POST[$this->_field];

                        # The following two lines request email, full name, and a nickname
                        # from the provider. Remove them if you don't need that data.
                        $this->openId->required = array('contact/email');
                        $this->openId->optional = array('namePerson', 'namePerson/friendly');
                        header('Location: ' . $this->openId->authUrl());
                        echo '<pre>';
                        echo $this->openId->authUrl();
                        echo '</pre>';
                    }
                } elseif ($this->openId->mode == 'cancel') {
                    return false;
                } elseif ($this->openId->validate()) {
                    return true;
                    //echo 'User ' . ($this->openId->validate() ? $this->openId->identity . ' has ' : 'has not ') . 'logged in.';
                    //print_r($this->openId->getAttributes());
                }
            } catch(ErrorException $e) {
                echo $e->getMessage();
                return false;
            }

            return false;
        }
        
        /**
         * A pass-through method called by `Auth`. Returns the value of `$data`, which is written to
         * a user's session. When implementing a custom adapter, this method may be used to modify or
         * reject data before it is written to the session.
         *
         * @param array $data User data to be written to the session.
         * @param array $options Adapter-specific options. Not implemented in the `Ldap` adapter.
         * @return array Returns the value of `$data`.
        */
        public function set($data, array $options = array()) {
                return $data;
        }

        /**
         * Called by `Auth` when a user session is terminated. Not implemented in the `Ldap` adapter.
         *
         * @param array $options Adapter-specific options. Not implemented in the `Ldap` adapter.
         * @return void
        */
        public function clear(array $options = array()) {
        }

        /**
         * Calls each registered callback, by field name.
         *
         * @param string $data Keyed form data.
         * @return mixed Callback result.
        */
        protected function _filters($data) {
                return $data;
        }
}
?>
