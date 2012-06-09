<?php

require_once "lib/Voot/IVootStorage.php";
require_once "lib/Config.php";
require_once "lib/StorageException.php";


class PdoVootStorage implements IVootStorage {

    private $_c;
    private $_pdo;

    public $requiredVersion = 2012060701;

    public function __construct(Config $c) {
        $this->_c = $c;

        $driverOptions = array();
        if(TRUE === $this->_c->getSectionValue('PdoVootStorage', 'persistentConnection')) {
            $driverOptions = array(PDO::ATTR_PERSISTENT => TRUE);
        }

        $this->_pdo = new PDO($this->_c->getSectionValue('PdoVootStorage', 'dsn'), $this->_c->getSectionValue('PdoVootStorage', 'username', FALSE), $this->_c->getSectionValue('PdoVootStorage', 'password', FALSE), $driverOptions);

        // enforce foreign keys
    	$this->_pdo->exec("PRAGMA foreign_keys = ON");
    }

    public function isMemberOf($resourceOwnerId, $startIndex = null, $count = null) {
        $stmt = $this->_pdo->prepare("SELECT COUNT(*) AS count FROM membership m, groups g, roles r WHERE m.id=:id AND m.groupid = g.id AND m.role = r.id");
        $stmt->bindValue(":id", $resourceOwnerId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            return FALSE;
        }
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalResults = $data['count'];

        if(!is_numeric($startIndex)) {
            $startIndex = 0;
        }
        if(!is_numeric($count)) {
            $count = $totalResults;
        }

        $stmt = $this->_pdo->prepare("SELECT m.id, g.id, g.title, g.description, r.voot_membership_role FROM membership m, groups g, roles r WHERE m.id=:id AND m.groupid = g.id AND m.role = r.id LIMIT :startIndex, :count");
        $stmt->bindValue(":id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":startIndex", $startIndex, PDO::PARAM_INT);
        $stmt->bindValue(":count", $count, PDO::PARAM_INT);
        $result = $stmt->execute();
        if (FALSE === $result) {
            return FALSE;
        }
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (FALSE === $data) {
            return FALSE;
        }

        // FIXME: should itemsPerPage return the count value or the actual number of results returned?
	    return array ( 'startIndex' => $startIndex, 'totalResults' => $totalResults, 'itemsPerPage' => sizeof($data), 'entry' => $data);
    }

    public function getGroupMembers($resourceOwnerId, $groupId, $startIndex = 0, $count = null) {
        // FIXME: check whether or not $resourceOwnerId is a member of the group, if not don't 
        // return anything (or error).

        $stmt = $this->_pdo->prepare("SELECT COUNT(*) AS count FROM membership m, groups g, roles r WHERE g.id = m.groupid AND r.id=m.role AND g.id=:groupId");
        $stmt->bindValue(":groupId", $groupId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            return FALSE;
        }
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalResults = $data['count'];

        if(!is_numeric($startIndex)) {
            $startIndex = 0;
        }
        if(!is_numeric($count)) {
            $count = $totalResults;
        }

        $stmt = $this->_pdo->prepare("SELECT m.id, r.voot_membership_role FROM membership m, groups g, roles r WHERE g.id = m.groupid AND r.id=m.role AND g.id=:groupId ORDER BY r.id LIMIT :startIndex, :count");
        $stmt->bindValue(":groupId", $groupId, PDO::PARAM_STR);
        $stmt->bindValue(":startIndex", $startIndex, PDO::PARAM_INT);
        $stmt->bindValue(":count", $count, PDO::PARAM_INT);
        $result = $stmt->execute();
        if (FALSE === $result) {
            return FALSE;
        }
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (FALSE === $data) {
            return FALSE;
        }

        // FIXME: should itemsPerPage return the count value or the actual number of results returned?
	    return array ( 'startIndex' => $startIndex, 'totalResults' => $totalResults, 'itemsPerPage' => sizeof($data), 'entry' => $data);
    } 

    public function addGroup($id, $title, $description) {
        $stmt = $this->_pdo->prepare("INSERT INTO groups (id, title, description) VALUES(:id, :title, :description)");
        $stmt->bindValue(":id", $id, PDO::PARAM_STR);
        $stmt->bindValue(":title", $title, PDO::PARAM_STR);
        $stmt->bindValue(":description", $description, PDO::PARAM_STR);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to add group");
        }
        return 1 === $stmt->rowCount();
    }

    public function addMembership($id, $groupId, $role) {
        $stmt = $this->_pdo->prepare("INSERT INTO membership (id, groupid, role) VALUES(:id, :groupid, :role)");
        $stmt->bindValue(":id", $id, PDO::PARAM_STR);
        $stmt->bindValue(":groupid", $groupId, PDO::PARAM_STR);
        $stmt->bindValue(":role", $role, PDO::PARAM_INT);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to add membership");
        }
        return 1 === $stmt->rowCount();
    }

    public function initDatabase() {
        // this is the initial database. Any modifications to the database 
        // after this will be done in updateDatabase

        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `Version` (
            `version` int(11) NOT NULL)
        ");

        $this->_pdo->exec("
            DELETE FROM Version
        ");

        $this->_pdo->exec("
            INSERT INTO Version
            VALUES(2012060701)
        ");

        $this->_pdo->exec("
            CREATE TABLE `groups` (
            `id` varchar(64) NOT NULL,
            `title` varchar(64) NOT NULL,
            `description` text,
            PRIMARY KEY (`id`))
        ");

        $this->_pdo->exec("
            CREATE TABLE `roles` (
            `id` int(11) NOT NULL,
            `voot_membership_role` varchar(64) NOT NULL,
            PRIMARY KEY (`id`))
        ");

        $this->_pdo->exec("
            CREATE TABLE `membership` (
            `id` varchar(64) NOT NULL,
            `groupid` varchar(64) NOT NULL,
            `role` int(11) NOT NULL,
            FOREIGN KEY (`groupid`) REFERENCES `groups` (`id`),
            FOREIGN KEY (`role`) REFERENCES `roles` (`id`))
        ");

        // add some default roles
        $this->_pdo->exec("INSERT INTO `roles` VALUES (10,'member')");
        $this->_pdo->exec("INSERT INTO `roles` VALUES (20,'manager')");
        $this->_pdo->exec("INSERT INTO `roles` VALUES (50,'admin')");
    }

    public function getDatabaseVersion() {
        $stmt = $this->_pdo->prepare("SELECT * FROM Version");
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get database version");
        }
        $data = $stmt->fetch(PDO::FETCH_OBJ);
        return $data->version;
    }

    public function setDatabaseVersion($version) {
        $stmt = $this->_pdo->prepare("UPDATE Version SET version = :version");
        $stmt->bindValue(":version", $version, PDO::PARAM_INT);
        if(FALSE === $stmt->execute()) {
            throw new StorageException("unable to update database version");
        }
        return 1 === $stmt->rowCount();
    }

    public function updateDatabase() {
        $version = $this->getDatabaseVersion();
        switch($version) {
            case 2012060701:
                // intial version, do nothing here...

        /*
            case 2012060702:
                // perform updates to reach this version...
                $this->setDatabaseVersion(2012060602);
            case 2012070101:
                // perform updates to reach this version...
                $this->setDatabaseVersion(2012070701);
        */
        }
    }


}

?>
