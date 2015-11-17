<?php

namespace Kanboard\Auth;

use Kanboard\Core\Base;
use Kanboard\Event\AuthEvent;

/**
 * ReverseProxy backend
 *
 * @package  auth
 * @author   Sylvain Veyrié
 */
class ReverseProxy extends Ldap
{
    /**
     * Backend name
     *
     * @var string
     */
    const AUTH_NAME = 'ReverseProxy';

    /**
     * Get username from the reverse proxy
     *
     * @access public
     * @return string
     */
    public function getUsername()
    {
        return isset($_SERVER[REVERSE_PROXY_USER_HEADER]) ? $_SERVER[REVERSE_PROXY_USER_HEADER] : '';
    }

 /**
     * Authenticate the user
     *
     * @access public
     * @param  string  $username  Username
     * @param  string  $password  Password
     * @return boolean
     */
    public function authenticate($username, $password)
    {
        if (isset($_SERVER[REVERSE_PROXY_USER_HEADER])) {
            $login = $_SERVER[REVERSE_PROXY_USER_HEADER];
            $user = $this->user->getByUsername($login);

            if (empty($user)) {
                $this->createUser($login);
                $user = $this->user->getByUsername($login);
            }

            $this->userSession->refresh($user);
            $this->container['dispatcher']->dispatch('auth.success', new AuthEvent(self::AUTH_NAME, $user['id']));

            return true;
        }

        return false;
    }

    /**
     * Create automatically a new local user after the authentication
     *
     * @access private
     * @param  string  $login  Username
     * @return bool
     */
    private function createUser($login)
    {
        if (LDAP_ACCOUNT_CREATION && is_array($this->lookup($login)) ) {
            $result=$this->lookup($login);
        }
        else {
            $email = strpos($login, '@') !== false ? $login : '';
            if (REVERSE_PROXY_DEFAULT_DOMAIN !== '' && empty($email)) {
                $email = $login.'@'.REVERSE_PROXY_DEFAULT_DOMAIN;
            }
            $result=array(
                'email' => $email,
                'username' => $login,
                'is_admin' => REVERSE_PROXY_DEFAULT_ADMIN === $login,
                'is_ldap_user' => 1,
                'disable_login_form' => 1,
                );
        }
        return $this->user->create($result);
    }
}
