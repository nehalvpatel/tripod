<?php

namespace Tripod;

class Review
{
    // database
    private $_connection;
    
    // instance
    private $_init_id;
    private $_data;
    
    public function __construct($initiator, $connection)
    {
        $this->_connection = $connection;
        unset($connection);
        
        if (is_array($initiator)) {
            $this->_init_id = $initiator["ID"];
            $this->_data = $initiator;
        } else {
            $this->_init_id = $initiator;
        }
    }
    
    public function checkData()
    {
        if (count($this->_data) == 0) {
            $this->reloadData($this->_init_id);
        }
    }
    
    public function reloadData($review_id = "")
    {
        if (empty($review_id)) {
            $review_id = $this->getID();
        }
        
        if (is_numeric($review_id)) {
            $review_query = $this->_connection->prepare("SELECT * FROM `reviews` WHERE `ID` = :ID");
            $review_query->bindValue(":ID", $review_id, \PDO::PARAM_INT);
            $review_query->execute();
            $review_results = $review_query->fetchAll();
            
            if (count($review_results) > 0) {
                $this->_data = $review_results[0];
            } else {
                throw new \Exception("No review with that number exists");
            }
        } else {
            throw new \Exception("Invalid review ID");
        }
    }
    
    private function _getValue($field)
    {
        $this->checkData();
        return $this->_data[$field];
    }
    
    private function _setValue($field, $value)
    {
        $this->checkData();
        try {
            $update_query = $this->_connection->prepare("UPDATE `reviews` SET {$field} = :Value WHERE `ID` = :ID");
            $update_query->bindValue(":Value", $value);
            $update_query->bindValue(":ID", $this->getID(), \PDO::PARAM_INT);
            $update_query->execute();
            
            $this->reloadData();
            
            return true;
        } catch (\PDOException $e) {
            die("DATABASE ERROR: " . $e->getMessage());
        }
    }
    
    public function getID()
    {
        return $this->_getValue("ID");
    }
    
    public function getPerson()
    {
        return new Person($this->_getValue("Person"), $this->_connection);
    }
    
    public function getEpisode()
    {
        return new Episode($this->_getValue("Episode"), $this->_connection);
    }
    
    public function getReview()
    {
        return $this->_getValue("Review");
    }
    
    public function __toString()
    {
        return $this->getReview();
    }
}