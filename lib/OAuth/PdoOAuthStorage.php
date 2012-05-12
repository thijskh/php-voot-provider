<?php

class PdoOAuthStorage implements IOAuthStorage {

    private $_c;
    private $_pdo;

    // FIXME: look at throwing exception on PDO errors?!

    // OK
    public function __construct(Config $c) {
        $this->_c = $c;
        $this->_pdo = new PDO($this->_c->getSectionValue('PdoOAuthStorage', 'dsn'), $this->_c->getSectionValue('PdoOAuthStorage', 'username', FALSE), $this->_c->getSectionValue('PdoOAuthStorage', 'password', FALSE));
    	$this->_pdo->exec("PRAGMA foreign_keys = ON");
    }

    // OK
    public function getClients() {
        $stmt = $this->_pdo->prepare("SELECT * FROM Client");
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve clients");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    }

    // OK
    public function getClient($clientId) {
        $stmt = $this->_pdo->prepare("SELECT * FROM Client WHERE id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve client");
        }
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    // OK
    public function getClientByRedirectUri($redirectUri) {
        $stmt = $this->_pdo->prepare("SELECT * FROM Client WHERE redirect_uri = :redirect_uri");
        $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve client by redirectUri");
        }
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    // OK
    public function updateClient($clientId, $data) {
        $stmt = $this->_pdo->prepare("UPDATE Client SET name = :name, description = :description, secret = :secret, redirect_uri = :redirect_uri, type = :type WHERE id = :client_id");
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":secret", $data['secret'], PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $data['redirect_uri'], PDO::PARAM_STR);
        $stmt->bindValue(":type", $data['type'], PDO::PARAM_STR);
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to update client");
        }
        return 1 === $stmt->rowCount();
    }

    // OK
    public function addClient($data) {
        $stmt = $this->_pdo->prepare("INSERT INTO Client (id, name, description, secret, redirect_uri, type) VALUES(:client_id, :name, :description, :secret, :redirect_uri, :type)");
        $stmt->bindValue(":client_id", $data['id'], PDO::PARAM_STR);
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":secret", $data['secret'], PDO::PARAM_STR | PDO::PARAM_NULL);
        $stmt->bindValue(":redirect_uri", $data['redirect_uri'], PDO::PARAM_STR);
        $stmt->bindValue(":type", $data['type'], PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to add client");
        }
        return TRUE;
    }

    // OK
    public function deleteClient($clientId) {
        // delete approvals
        $stmt = $this->_pdo->prepare("DELETE FROM Approval WHERE client_id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete approvals");
        }
        // delete access tokens
        $stmt = $this->_pdo->prepare("DELETE FROM AccessToken WHERE client_id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete access tokens");
        }
        // delete authorize nonces
        $stmt = $this->_pdo->prepare("DELETE FROM AuthorizeNonce WHERE client_id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete authorize nonces");
        }
        // delete authorization codes
        $stmt = $this->_pdo->prepare("DELETE FROM AuthorizationCode WHERE client_id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete authorization codes");
        }
        // delete the client
        $stmt = $this->_pdo->prepare("DELETE FROM Client WHERE id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete client");
        }
        return 1 === $stmt->rowCount();
    }

    // OK
    public function addApproval($clientId, $resourceOwnerId, $scope) {
        $stmt = $this->_pdo->prepare("INSERT INTO Approval (client_id, resource_owner_id, scope) VALUES(:client_id, :resource_owner_id, :scope)");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to store approved scope");
        }
        return TRUE;
    }

    // OK
    public function updateApproval($clientId, $resourceOwnerId, $scope) {
        $stmt = $this->_pdo->prepare("UPDATE Approval SET scope = :scope WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to update approved scope");
        }
        return TRUE;
    }

    // OK
    public function getApproval($clientId, $resourceOwnerId) {
        $stmt = $this->_pdo->prepare("SELECT * FROM Approval WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get approved scope");
        }
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    // OK
    public function storeAccessToken($accessToken, $clientId, $resourceOwnerId, $resourceOwnerDisplayName, $scope, $expiry) {
        $stmt = $this->_pdo->prepare("INSERT INTO AccessToken (client_id, resource_owner_id, resource_owner_display_name, issue_time, expires_in, scope, access_token) VALUES(:client_id, :resource_owner_id, :resource_owner_display_name, :issue_time, :expires_in, :scope, :access_token)");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_display_name", $resourceOwnerDisplayName, PDO::PARAM_STR);
        $stmt->bindValue(":issue_time", time(), PDO::PARAM_INT);
        $stmt->bindValue(":expires_in", $expiry, PDO::PARAM_INT);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->bindValue(":access_token", $accessToken, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to store access token");
        }
        return TRUE;
    }

    // OK
    public function storeAuthorizationCode($authorizationCode, $clientId, $redirectUri, $accessToken) {
        $stmt = $this->_pdo->prepare("INSERT INTO AuthorizationCode (client_id, authorization_code, redirect_uri, issue_time, access_token) VALUES(:client_id, :authorization_code, :redirect_uri, :issue_time, :access_token)");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR);
        $stmt->bindValue(":access_token", $accessToken, PDO::PARAM_STR);
        $stmt->bindValue(":issue_time", time(), PDO::PARAM_INT);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to store authorization code");
        }
        return TRUE;
    }

    // BIG FAIL
    public function getAuthorizationCode($authorizationCode, $redirectUri) {
        //error_log(var_export($authorizationCode, TRUE));
        //error_log(var_export($redirectUri, TRUE));
        $stmt = $this->_pdo->prepare("SELECT * FROM AuthorizationCode WHERE authorization_code IS :authorization_code AND redirect_uri IS :redirect_uri");
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR | PDO::PARAM_NULL);
        $result = $stmt->execute();
        if (FALSE === $result) {
            //error_log("SELECT result === FALSE");
            return FALSE;
        }
        $data = $stmt->fetch(PDO::FETCH_OBJ);
        if(FALSE === $data) {
            //error_log("SELECT no data");
            return FALSE;
        }
        $accessToken = $data->access_token;

        // authorization code expired
        if(time() > $data->issue_time + 600) {
            //error_log("authorization_code expired");
            return FALSE;
        }

        // delete authorization code
        $stmt = $this->_pdo->prepare("DELETE FROM AuthorizationCode WHERE authorization_code IS :authorization_code AND redirect_uri IS :redirect_uri");
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR | PDO::PARAM_NULL);
        $result = $stmt->execute();
        if (FALSE === $result) {
            //error_log("DELETE result === FALSE");
            return FALSE;
        }
        return (1 === $stmt->rowCount()) ? $this->getAccessToken($accessToken) : FALSE; 
    }

    // OK
    public function getAccessToken($accessToken) {
        $stmt = $this->_pdo->prepare("SELECT * FROM AccessToken WHERE access_token = :access_token");
        $stmt->bindValue(":access_token", $accessToken, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get access token");
        }
        return $stmt->fetch(PDO::FETCH_OBJ);        
    }

    // OK
    public function storeAuthorizeNonce($authorizeNonce, $clientId, $resourceOwnerId, $responseType, $redirectUri, $scope, $state) {
        $stmt = $this->_pdo->prepare("INSERT INTO AuthorizeNonce (authorize_nonce, resource_owner_id, client_id, response_type, redirect_uri, scope, state) VALUES(:authorize_nonce, :resource_owner_id, :client_id, :response_type, :redirect_uri, :scope, :state)");
        $stmt->bindValue(":authorize_nonce", $authorizeNonce, PDO::PARAM_STR);        
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":response_type", $responseType, PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->bindValue(":state", $state, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to store authorize nonce");
        }
        return TRUE;
    }

    // OK
    public function getAuthorizeNonce($clientId, $resourceOwnerId, $scope, $authorizeNonce) {
        $stmt = $this->_pdo->prepare("DELETE FROM AuthorizeNonce WHERE client_id = :client_id AND scope = :scope AND authorize_nonce = :authorize_nonce AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->bindValue(":authorize_nonce", $authorizeNonce, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get authorize nonce");
        }
        return 1 === $stmt->rowCount();
    }

    // OK
    public function getApprovals($resourceOwnerId) {
        $stmt = $this->_pdo->prepare("SELECT c.id, a.scope, c.name, c.description, c.redirect_uri FROM Approval a, Client c WHERE resource_owner_id = :resource_owner_id AND a.client_id = c.id");
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get approvals");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    }

    // OK
    public function deleteApproval($clientId, $resourceOwnerId) {
        $stmt = $this->_pdo->prepare("DELETE FROM Approval WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete approval");
        } 
        return TRUE;
    }

}

?>
