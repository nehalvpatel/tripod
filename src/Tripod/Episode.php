<?php

namespace Tripod;

class Episode
{
    // database
    private $_connection;
    
    // instance
    private $_init_id;
    private $_data;
    private $_highlighted = false;
    
    public function __construct($initiator, $connection)
    {
        $this->_connection = $connection;
        unset($connection);
        
        if (is_array($initiator)) {
            $this->_init_id = $initiator["Identifier"];
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
    
    public function reloadData($episode_id = "")
    {
        if ($episode_id === "") {
            $episode_id = $this->getIdentifier();
        }
        
        if (is_numeric($episode_id)) {
            $episode_id = "PKA_" . Utilities::padEpisodeNumber($episode_id);
        }
        
        $episode_query = $this->_connection->prepare("SELECT * FROM `episodes` WHERE `Identifier` = :Identifier");
        $episode_query->bindValue(":Identifier", $episode_id, \PDO::PARAM_STR);
        $episode_query->execute();
        $episode_results = $episode_query->fetchAll();
        
        if (count($episode_results) > 0) {
            $timeline_query = $this->_connection->prepare("SELECT * FROM `timestamps` WHERE `Episode` = :Identifier ORDER BY `Timestamp` ASC");
            $timeline_query->bindValue(":Identifier", $episode_id, \PDO::PARAM_STR);
            $timeline_query->execute();
            $timeline_results = $timeline_query->fetchAll();
            
            if (count($timeline_results) > 0) {
                foreach ($timeline_results as $timestamp) {
                    $episode_results[0]["Timestamps"][] = new Timestamp($timestamp, $this->_connection);
                }
            } else {
                $episode_results[0]["Timestamps"] = array();
            }
            
            $reviews_query = $this->_connection->prepare("SELECT * FROM `reviews` WHERE `Episode` = :Identifier ORDER BY `ID` ASC");
            $reviews_query->bindValue(":Identifier", $episode_id, \PDO::PARAM_STR);
            $reviews_query->execute();
            $reviews_results = $reviews_query->fetchAll();
            
            if (count($reviews_results) > 0) {
                foreach ($reviews_results as $review) {
                    $episode_results[0]["Reviews"][] = new Review($review, $this->_connection);
                }
            } else {
                $episode_results[0]["Reviews"] = array();
            }
            
            $this->_data = $episode_results[0];
        } else {
            throw new \Exception("No episode with that identifier exists");
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
            $update_query = $this->_connection->prepare("UPDATE `episodes` SET {$field} = :Value WHERE `Identifier` = :Identifier");
            $update_query->bindValue(":Value", $value);
            $update_query->bindValue(":ID", $this->getID(), \PDO::PARAM_INT);
            $update_query->execute();
            
            $this->reloadData();
            
            return true;
        } catch (\PDOException $e) {
            die("DATABASE ERROR: " . $e->getMessage());
        }
    }
    
    public function getIdentifier()
    {
        return $this->_getValue("Identifier");
    }
    
    public function getFileName()
    {
        return str_replace("_", "-", strtolower($this->getIdentifier())) . ".mp3";
    }
    
    public function getNumber()
    {
        return $this->_getValue("Number");
    }
    
    public function getDate()
    {
        return $this->_getValue("Date");
    }
    
    public function setDate($date)
    {
        return $this->_setValue("Date", $date);
    }
    
    public function getHosts()
    {
        $hosts_list = json_decode($this->_getValue("Hosts"), true);
        
        $placeholders = rtrim(str_repeat("?, ", count($hosts_list)), ", ");
        $people_query = $this->_connection->prepare("SELECT * FROM `people` WHERE `ID` IN ($placeholders) ORDER BY `ID` ASC");
        $people_query->execute($hosts_list);
        $people_results = $people_query->fetchAll();
        
        $hosts = array();
        foreach ($people_results as $person) {
            $hosts[] = new Person($person, $this->_connection);
        }
        
        return $hosts;
    }
    
    public function setHosts(array $hosts)
    {
        $hosts_list = array();
        foreach ($hosts as $host) {
            $hosts_list[] = (int)$host->getID();
        }
        
        return $this->_setValue("Hosts", json_encode($hosts_list));
    }
    
    public function getGuests()
    {
        $guests_list = json_decode($this->_getValue("Guests"), true);
        
        $placeholders = rtrim(str_repeat("?, ", count($guests_list)), ", ");
        $people_query = $this->_connection->prepare("SELECT * FROM `people` WHERE `ID` IN ($placeholders) ORDER BY `ID` ASC");
        $people_query->execute($guests_list);
        $people_results = $people_query->fetchAll();
        
        $guests = array();
        foreach ($people_results as $person) {
            $guests[] = new Person($person, $this->_connection);
        }
        
        return $guests;
    }
    
    public function setGuests(array $guests)
    {
        $guests_list = array();
        foreach ($guests as $guest) {
            $guests_list[] = (int)$guest->getID();
        }
        
        return $this->_setValue("Guests", json_encode($guests_list));
    }
    
    public function getSponsors()
    {
        $sponsors_list = json_decode($this->_getValue("Sponsors"), true);
        
        $placeholders = rtrim(str_repeat("?, ", count($sponsors_list)), ", ");
        $people_query = $this->_connection->prepare("SELECT * FROM `people` WHERE `ID` IN ($placeholders) ORDER BY `ID` ASC");
        $people_query->execute($sponsors_list);
        $people_results = $people_query->fetchAll();
        
        $sponsors = array();
        foreach ($people_results as $person) {
            $sponsors[] = new Person($person, $this->_connection);
        }
        
        return $sponsors;
    }
    
    public function setSponsors(array $sponsors)
    {
        $sponsors_list = array();
        foreach ($sponsors as $sponsor) {
            $sponsors_list[] = (int)$sponsor->getID();
        }
        
        return $this->_setValue("Sponsors", json_encode($sponsors_list));
    }
    
    public function getLength()
    {
        return $this->_getValue("Length");
    }
    
    public function setLength($length)
    {
        return $this->_setValue("Length", $length);
    }
    
    public function getYouTubeLength()
    {
        return $this->_getValue("YouTube Length");
    }
    
    public function setYouTubeLength($youtubelength)
    {
        return $this->_setValue("YouTube Length", $youtubelength);
    }
    
    public function getBytes()
    {
        return $this->_getValue("Bytes");
    }
    
    public function setBytes($bytes)
    {
        return $this->_setValue("Bytes", $bytes);
    }
    
    public function getDuration()
    {
        $hours = floor($this->getLength() / 3600);
        $minutes = floor(($this->getLength() / 60) % 60);
        $seconds = $this->getLength() % 60;
        
        return "T" . $hours . "H" . $minutes . "M" . $seconds . "S";
    }
    
    public function getYouTube()
    {
        return $this->_getValue("YouTube");
    }
    
    public function setYouTube($youtube)
    {
        return $this->_setValue("YouTube", $youtube);
    }
    
    public function getPublished()
    {
        return $this->_getValue("Published");
    }
    
    public function setPublished($published)
    {
        return $this->_setValue("Published", $published);
    }
    
    public function getReddit()
    {
        return $this->_getValue("Reddit");
    }
    
    public function setReddit($reddit)
    {
        return $this->_setValue("Reddit", $reddit);
    }
    
    public function getTimelineAuthor()
    {
        return new Author($this->_getValue("TimelineAuthor"), $this->_connection);
    }
    
    public function setTimelineAuthor(Author $timelineauthor)
    {
        return $this->_setValue("TimelineAuthor", $timelineauthor->getID());
    }
    
    public function getTimelined()
    {
        if (count($this->getTimestamps()) > 0) {
            return true;
        } else {
            return false;
        }
    }
    
    public function getTimestamps()
    {
        $timestamps = $this->_getValue("Timestamps");
        
        $timeline_array = array();
        if (count($timestamps) > 0) {
            // We now find the end time value for each timestamp and add it to the timestamp's array element.
            foreach ($timestamps as $timestamp) {
                $timeline_array[] = $timestamp;
                
                // Set the previous array element's finishing time to the currents starting time.
                if (isset($timeline_array[count($timeline_array) - 2])) {
                    $timeline_array[count($timeline_array) - 2]->setEnd($timestamp->getTimestamp());
                }
            }
            
            // The last timestamp ends when the episode ends.
            $timeline_array[count($timeline_array) - 1]->setEnd($this->getYouTubeLength());
            
            // We now find the length of each timestamp as a percentage of the full episode length.
            foreach ($timeline_array as $timeline_key => $timeline_element) {
                // Find size of timeline element.
                $timeline_element_size = $timeline_element->getEnd() - $timeline_element->getBegin();
                
                // Express the timeline size as a quotent of the full current episode size. The * 1.01 gives us some visual spacing to avoid timeline glitches.
                $timeline_element_quotent = $timeline_element_size / ($this->getYouTubeLength() * 1.01);
                
                // Multiply by 100 to express in percentage form and put the value into the $timeline_array array.
                $timeline_array[$timeline_key]->setWidth($timeline_element_quotent * 100);
            }
        }
        
        return $timeline_array;
    }
    
    public function addTimestamp($timestamp, $value, $url = "", $special = null)
    {
        if ($special === null) {
            $special = false;
        }
        
        try {
            $timestamp_query = $this->_connection->prepare("INSERT INTO `timestamps` (`Episode`, `Timestamp`, `Value`, `URL`, `Special`) VALUES (:Episode, :Timestamp, :Value, :URL, :Special)");
            
            $timestamp_query->bindValue(":Episode", $this->getIdentifier());
            $timestamp_query->bindValue(":Timestamp", $timestamp);
            $timestamp_query->bindValue(":Value", $value);
            $timestamp_query->bindValue(":URL", $url);
            $timestamp_query->bindValue(":Special", (int)$special);
            
            $timestamp_query->execute();
            $this->reloadData();
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getReviews()
    {
        return $this->_getValue("Reviews");
    }
    
    public function addReview(Person $person, $review)
    {
        try {
            $review_query = $this->_connection->prepare("INSERT INTO `reviews` (`Person`, `Episode`, `Review`) VALUES (:Person, :Episode, :Review)");
            
            $review_query->bindValue(":Person", $person->getID());
            $review_query->bindValue(":Episode", $this->getIdentifier());
            $review_query->bindValue(":Review", $review);
            
            $review_query->execute();
            $this->reloadData();
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getHighlighted()
    {
        return $this->_highlighted;
    }
    
    public function setHighlighted($highlighted)
    {
        $this->_highlighted = $highlighted;
        return true;
    }
    
    public function __toString()
    {
        return $this->getIdentifier();
    }
}