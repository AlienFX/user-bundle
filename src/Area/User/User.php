<?php
namespace Area\User;
/**
 * Class User
 * @author ALIENFX EURL <eurl@alienfx.net>
 */

class User {
	
	// id de l'utilisateur
	protected $id;
	
	// array contenant les infos de l'utilisateur provenant de la BDD
	protected $user = array();
	
	// array contenant les infos de l'utilisateur à updater/insérer dans la BDD tels que donnés par l'utilisateur
	protected $user_update = array();
	
	//permet de récuperer un message d'erreur, de succés...
	protected $erreurs;
    
    /**
     * récupére les données de l'utilisateur via son id
     *
     * @param int $id id utilisateur à récupérer
     * @return boolean
     */ 
	public function loadById( $id ){       
		if( is_numeric($id) && $this->user = \App::$sql->select_one_row('SELECT * FROM users WHERE id = "'.$id.'"') ){
			$this->id = $this->user['id'];
			return true;
		}
		return false;	
	}
	
	/**
     * récupére les données de l'utilisateur via son email
     *
     * @param string $email email de l'utilisateur à récupérer
     * @return boolean
     */ 
	public function loadByEmail( $email ){
        if(filter_var($email, FILTER_VALIDATE_EMAIL)){
    		if( $this->user = \App::$sql->select_one_row('SELECT * FROM users WHERE email = "'.$email.'"') ){
				$this->id = $this->user['id'];
				return true;
    		}
        }
		return false;
	}
	
	/**
     * récupére les erreurs
	 *
	 * @return array
	 */
	public function getErreurs(){
		return $this->erreurs;	
	}
	
	/**
	 * methode magique de get/set
	 * récupere les éléments via le get
	 * prépare l'update/l'insert via le set
	 * pas de SQL ici : le get reprend les infos introduites préalablement par le loadById, le set prépare les données à insérer/updater qui se feront ensuite via un save()          	 
	 * 
     * @param string $name la commange set ou get
     * @param string $arguments la valeur de l'élément en cas de set
     * @return string la valeur de l'élément
	 */          	
	public function __call( $name, $arguments ){
		
		if( substr($name, 0, 3) == 'get' ){
			
			$cle = strtolower(str_replace('get', '', $name));
			switch($cle){
				default:
					if(isset($this->user[$cle])){
						return $this->user[$cle];
					}else{
						return NULL;	
					}
					break;
			}
			
		}elseif( substr($name, 0, 3) == 'set' ){
			
			$cle = strtolower(str_replace('set', '', $name));
			if($this->id && in_array($cle, array('id','salt','date_insert'))) throw new Exception('Interdiction de modifier la cle "'.$cle.'" de l\'object User', 403);
			$this->user_update[$cle] = $arguments[0];
			return $this->user_update[$cle];
			
		}
	}
	
	/**
     * vérifie la validité du mot de passe  
     * 
     * @param string $pass le mot de passe à crypter
     * @param string $salt le grain de sel
     * @return bool
     */              	
	public function checkPassword($pass, $salt){
		return ($this->cryptPassword($pass, $salt) == \App::$sql->select_one('SELECT password FROM users WHERE id = "'.$this->user['id'].'"'));
	}

	/**
     * réalise le cryptage du mot de passe  
     * 
     * @param string $pass le mot de passe à crypter
     * @param string $salt le grain de sel
     * @return string
     */              
	public function cryptPassword($pass, $salt){
		return crypt(md5(trim($pass)), $salt);
	}
			
    /**
     * enregistre en base les données préalablement stockées via un/des 'set' dans $user_update
     *
     * @return boolean     
     */              
	public function save(){
	
        if($this->id && !isset($this->user['id'])){
            //chose étrange ici : on veut faire un update mais on ne trouve pas l'id... le loadById() a du foirer..
            $this->erreurs[] = 'La modification est impossible car cet utilisateur n\'existe pas';
            return false;
        }
        
        if(count($this->user_update) <= 0){
            //on fait un 'save()' avant d'avoir fait un/des 'set()' = erreur
            $this->erreurs[] = 'Aucune donnée à modifier transmise';
            return false;
        }
        
        //controle + filtre des données passées par l'utilisateur avant insert BDD
        if(!$verifDonnees = $this->controleData()){
            return false;
        }
        
        // on lance les actions SQL :
        if(!$this->id){
            //insert :
            if($retour = $this->_insertUser()){
				# email de confirmation
			}
        }else{
            //update :
            $retour = $this->_updateUser();
        }
        
        if($retour){
            $this->user = $this->user + $this->user_update; //le tableau "officiel" peut maintenant etre rempli par les infos insérées car on est ISO avec la BDD, permet d'éviter un loadById()
        }
        
        return $retour;
    }


    /**
     * réalise le controle et le filtrage des données avant update/insert
     * écrase les propriétés contenues      
     * 
     * @return bool
     */
    public function controleData(){

		if(!isset($this->user_update['email']) || !filter_var($this->user_update['email'], FILTER_VALIDATE_EMAIL)){
			//controle de l'email
			$this->erreurs['email'] = 'L\'email est invalide';
		}
		
		if(isset($this->user_update['last_name']) && (!filter_var($this->user_update['last_name'], FILTER_SANITIZE_STRING))){
			//controle du nom
			$this->erreurs['last_name'] = 'Le nom est invalide';
		}
		
		if(isset($this->user_update['first_name']) && (!filter_var($this->user_update['first_name'], FILTER_SANITIZE_STRING))){
			//controle du prenom
			$this->erreurs['first_name'] = 'Le prénom est invalide';
		}
				
		if(isset($this->user_update['locale']) && !filter_var($this->user_update['locale'], FILTER_SANITIZE_STRING)){
			//controle du locale si renseigne
			$this->erreurs['locale'] = 'La locale est invalide';
		}
		
		if( count($this->erreurs) ){
			return false;	
		}

        return true;        
    }
    
    /**
     * insertion d'un user
     * retourne ID créé, sinon 0 en cas d'echec   
     * 
     * @return boolean           
     */              
    private function _insertUser(){
        
		$this->user_update['salt'] = uniqid();
		$this->user_update['date_insert'] = date("Y-m-d H:i:s");
		
		if(isset($this->user_update['password'])) $this->user_update['password'] = $this->cryptPassword($this->user_update['password'], $this->user_update['salt']);
		
        $reqInsert = "INSERT INTO users SET ";
        foreach($this->user_update as $key => $val){
            //on boucle sur les colonnes à insérer
            $reqInsert.= $key." = '".htmlspecialchars(addslashes($val))."',";
        }   
        $reqInsert = substr($reqInsert, 0, -1);
		
        if(!$resSql = \App::$sql->insert($reqInsert)){
            if(strstr(mysql_error(),'Duplicate')){
                $this->erreurs[] = 'Création impossible un compte existe déjà avec cet email';
            }else{
                $this->erreurs[] = 'Création impossible '.mysql_error();
            }
        }else{
            $this->user['id'] = isset($this->user_update['id']) ? $this->user_update['id'] : \App::$sql->insert_id();
            $this->id = $this->user['id'];
			return $this->id;
        }
        return false;
    }
    
    /**
     * update des données d'un user
     * retourne true en cas de succes, sinon false     
     *
     * @return boolean
     */              
    private function _updateUser(){
        $reqUpdate = "UPDATE users SET ";
		
		if(isset($this->user_update['password'])) $this->user_update['password'] = $this->cryptPassword($this->user_update['password'], $this->user['salt']);

        foreach($this->user_update as $key => $val){
            //on boucle sur les colonnes à updater
            if($key != 'id' && $key != 'salt' && $key != 'date_insert'){
                $reqUpdate.= $key." = '".htmlspecialchars(addslashes($val))."',";
            }
        }
        $reqUpdate = substr($reqUpdate, 0 ,-1);
        $reqUpdate.= " WHERE id = ".$this->user['id'];
		
        if(!$resSql = \App::$sql->update($reqUpdate)){
            $this->erreurs[] = "Une erreur est survenue durant la mise à jour de l'user ".$this->user['id']." : ".mysql_error();
        }else{
			return true;	
		}
        return false;
    }
    
}

?>
