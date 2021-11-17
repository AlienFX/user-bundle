<?php
namespace Area\UserAuth;
/**
 * Class Admin
 * @author ALIENFX EURL <eurl@alienfx.net>
 */

//use Area\User;

class Admin extends User {

	public static $userauth = false;
	protected $user;
	protected $user_statut = false;
	protected $admin_statut = false;

	/**
	* loginAdmin
	*
	* @param string $email email
	* @param string $password password
	* @return bool
	*/
	public function login($email, $password, $rememberme=0, $uniq=0){

		if(parent::login($email, $password, $rememberme, $uniq)){
			if($this->hasRole('admin')){
				$this->admin_statut = true;
				$_SESSION['user_role'] = 'admin';
				return $this->admin_statut;
			}else{
				return 0; // N'est pas admin
			}
		}else{
			return -1; // Identifiants incorrects
		}

	}

	/**
	* logoutAdmin
	*
	* @return bool
	*/
	public function logout(){
       	$this->admin_statut = false;
		parent::logout();
        return true;
    }

	/**
	* checkCredentials
	*
	* @return bool
	*/
	public function checkCredentials(){

		parent::checkCredentials();

		if(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'){
			if($this->hasRole($_SESSION['user_role'])){
				$this->admin_statut = true;
			}else{
				unset($_SESSION['user_role']);
			}
		}

		return $this->user_statut;

	}

	/**
	* isAdminAuth
	*
	* @return bool
	*/
	public function isAdminAuth(){
		return $this->admin_statut && $this->hasRole('admin');
	}

}
