<?php

namespace Tripod;

class Timestamp
{
    // database
    private $_connection;
    
    // instance
    private $_init_id;
    private $_data;
    
    // etc
    private $_end;
    private $_width;
    
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
    
    public function reloadData($timestamp_id = "")
    {
        if ($timestamp_id === "") {
            $timestamp_id = $this->getID();
        }
        
        if (is_numeric($timestamp_id)) {
            $timestamp_query = $this->_connection->prepare("SELECT * FROM `timestamps` WHERE `ID` = :ID");
            $timestamp_query->bindValue(":ID", $timestamp_id, \PDO::PARAM_INT);
            $timestamp_query->execute();
            $timestamp_results = $timestamp_query->fetchAll();
            
            if (count($timestamp_results) > 0) {
                $this->_data = $timestamp_results[0];
            } else {
                throw new \Exception("No timestamp with that number exists");
            }
        } else {
            throw new \Exception("Invalid timestamp ID");
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
            $update_query = $this->_connection->prepare("UPDATE `timestamps` SET {$field} = :Value WHERE `ID` = :ID");
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
    
    public function getEpisode()
    {
        return new Episode($this->_getValue("Episode"), $this->_connection);
    }
    
    public function getSpecial()
    {
        return filter_var($this->_getValue("Special"), FILTER_VALIDATE_BOOLEAN);
    }
    
    public function setSpecial($special)
    {
        return $this->_setValue("Special", (int)$special);
    }
    
    public function getTimestamp()
    {
        return $this->_getValue("Timestamp");
    }
    
    public function setTimestamp($timestamp)
    {
        return $this->_setValue("Timestamp", $timestamp);
    }
    
    public function getTime()
    {
        $init = $this->getTimestamp();
        $hours = floor($init / 3600);
        $minutes = floor(($init / 60) % 60);
        $seconds = $init % 60;
        
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    }
    
    public function getValue()
    {
        return $this->_getValue("Value");
    }
    
    public function setValue($text)
    {
        return $this->_setValue("Value", $text);
    }
    
    public function getURL()
    {
        return $this->_getValue("URL");
    }
    
    public function setURL($url)
    {
        return $this->_setValue("URL", $url);
    }
    
    public function getBegin()
    {
        return $this->getTimestamp();
    }
    
    public function getEnd()
    {
        return $this->_end;
    }
    
    public function setEnd($end)
    {
        $this->_end = $end;
    }
    
    public function getWidth()
    {
        return $this->_width;
    }
    
    public function setWidth($width)
    {
        $this->_width = $width;
    }
    
    public function __toString()
    {
        return $this->getValue();
    }
}