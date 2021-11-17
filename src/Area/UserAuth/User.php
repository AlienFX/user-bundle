<?php
namespace Area\UserAuth;
/**
 * Class User
 * @author ALIENFX EURL <eurl@alienfx.net>
 */

//use Area\User;

class User {

	protected $user;
	protected $user_statut = false;

	/**
	* login
	*
	* @param string $email email
	* @param string $password password
	* @return bool
	*/
	public function login($email, $password, $rememberme=0, $uniq=0){

		$user = new \Area\User\User();
		if($user->loadByEmail($email) && $user->checkPassword($password, $user->getSalt())){
			$this->user = $user;
			$this->user_statut = true;
			$_SESSION['user'] = $user->getId().'-'.$this->generateCredentials( $user->getSalt().($uniq?:'') );
			if($rememberme) setcookie("rememberme", $user->getId().'-'.md5(md5($user->getSalt().($uniq?:''))), time()+(86400*30), '/');
			if($uniq) \App::$sql->update('UPDATE users SET salt_login = "'.$uniq.'" WHERE id = "'.$user->getId().'"');
		}else{
			$this->logout();
		}

		return $this->user_statut;

	}

    /**
	* logout
	*
	* @return bool
	*/
	public function logout(){
      $this->user_statut = false;
      unset($this->user);
      unset($_SESSION['user']);
			unset($_SESSION['user_role']);
			setcookie("rememberme", '', time()-3600, '/');
      return true;
  }

	/**
	* checkCredentials
	*
	* @return bool
	*/
	public function checkCredentials(){

		if(isset($_SESSION['user'])){

			list($id_user, $salt) = explode('-', $_SESSION['user']);

			$user = new \Area\User\User();
			if($user->loadById( $id_user ) && ($this->generateCredentials( $user->getSalt() ) == $salt || $this->generateCredentials( $user->getSalt().$user->getSalt_login() ) == $salt)){
				$this->user = $user;
				$this->user_statut = true;
			}else{
				unset($_SESSION['user']);
				\App::$rewrites->redirige( \App::$rewrites->make('accueil'), array('erreur' => 1, 'message' => 'Vous avez été déconnecté !'));
			}

		}elseif(isset($_COOKIE['rememberme'])){

			list($id_user, $salt) = explode('-', $_COOKIE['rememberme']);

			$user = new \Area\User\User();
			if($user->loadById( $id_user ) && (md5(md5( $user->getSalt() )) == $salt || md5(md5( $user->getSalt().$user->getSalt_login() )) == $salt)){
				$this->user = $user;
				$this->user_statut = true;
				$_SESSION['user'] = $user->getId().'-'.$this->generateCredentials( $user->getSalt().$user->getSalt_login() );
			}else{
				setcookie("rememberme", '', time()-3600);
			}

		}

		if($this->user_statut && $this->hasRole('admin'))
			$_SESSION['user_role'] = 'admin';

		return $this->user_statut;

	}

	/**
	* generateCredentials
	*
	* @param string $salt salt
	* @return string
	*/
	public function generateCredentials($salt){
		return md5(crypt(md5($salt), $salt));
	}

	/**
	* isAuth
	*
	* @return bool
	*/
	public function isAuth(){
		return $this->user_statut;
	}

	/**
	* hasRole
	* @param string $type nom du role
	* @return bool
	*/
	public function hasRole($type){
		if(isset($this->user))
			return $this->user->getType() == $type;
		else
			return false;
	}

	/**
	 * lance la methode magique de get/set de la classe user
	 *
     * @param string $name la commange set ou get
     * @param string $arguments la valeur de l'Ã©lÃ©ment en cas de set
     * @return string la valeur de l'Ã©lÃ©ment
	 */
	public function __call( $name, $arguments ){
		if($this->isAuth())
			return call_user_func_array(array($this->user, $name), $arguments);
		else
			return false;
	}

	/**
	* getUser
	* donne les données de l'array user, les données BDD de l'utilisateur
	*
	* @return bool
	*/
	public function getUser(){
		return $this->user;
	}

}
