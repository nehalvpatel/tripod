<?php

namespace Tripod;

class Podcast
{
    // database
    private $_connection;
    
    // instance
    private $_data;
    
    public function __construct($connection)
    {
        $this->_connection = $connection;
        unset($connection);
        
        $this->_data = array();
    }
    
    private function _getValue($field)
    {
        if (isset($this->_data[$field])) {
            return $this->_data[$field];
        } else {
            return "";
        }
    }
    
    private function _setValue($field, $value)
    {
        $this->_data[$field] = $value;
    }
    
    public function getPrefix()
    {
        return $this->_getValue("Prefix");
    }
    
    public function setPrefix($prefix)
    {
        $this->_setValue("Prefix", $prefix);
    }
    
    public function getName()
    {
        return $this->_getValue("Name");
    }
    
    public function setName($name)
    {
        $this->_setValue("Name", $name);
    }
    
    public function getDescription()
    {
        return $this->_getValue("Description");
    }
    
    public function setDescription($description)
    {
        $this->_setValue("Description", $description);
    }
    
    public function getAuthors()
    {
        $authors_query = $this->_connection->prepare("SELECT * FROM `admins` ORDER BY `ID` ASC");
        $authors_query->execute();
        $authors_results = $authors_query->fetchAll();
        
        $authors = array();
        foreach ($authors_results as $author) {
            $authors[] = new Author($author, $this->_connection);
        }
        
        return $authors; 
    }
    
    public function addEpisode($number, \DateTime $date, array $hosts, array $guests, array $sponsors, $youtube, $reddit)
    {
        if ($this->getPrefix() == "") {
            throw new \Exception("The prefix must be set before adding an episode.");
        }
        
        $created = $date->format("Y-m-d");
        
        $hosts_list = array();
        foreach ($hosts as $host) {
            $hosts_list[] = (int)$host->getID();
        }
        
        $guests_list = array();
        foreach ($guests as $guest) {
            $guests_list[] = (int)$guest->getID();
        }
        
        $sponsors_list = array();
        foreach ($sponsors as $sponsor) {
            $sponsors_list[] = (int)$sponsor->getID();
        }
        
        $youtube_data = file_get_contents("https://gdata.youtube.com/feeds/api/videos/$youtube?alt=json");
        $youtube_json = json_decode($youtube_data, true);
        
        $published = $youtube_json["entry"]["published"]["\$t"];
        $duration = $youtube_json["entry"]["media\$group"]["yt\$duration"]["seconds"];
        
        try {
            $add_query = $this->_connection->prepare("INSERT INTO `episodes` (`Identifier`, `Number`, `Date`, `Hosts`, `Guests`, `Sponsors`, `YouTube Length`, `YouTube`, `Published`, `Reddit`) VALUES (:Identifier, :Number, :Date, :Hosts, :Guests, :Sponsors, :YouTubeLength, :YouTube, :Published, :Reddit)");
            
            $add_query->bindValue(":Identifier", $this->getPrefix() . $number);
            $add_query->bindValue(":Number", $number);
            $add_query->bindValue(":Date", $created);
            $add_query->bindValue(":Hosts", json_encode($hosts_list));
            $add_query->bindValue(":Guests", json_encode($guests_list));
            $add_query->bindValue(":Sponsors", json_encode($sponsors_list));
            $add_query->bindValue(":YouTubeLength", $duration);
            $add_query->bindValue(":YouTube", $youtube);
            $add_query->bindValue(":Published", $published);
            $add_query->bindValue(":Reddit", $reddit);
            
            $add_query->execute();
        } catch (\PDOException $e) {
            die("DATABASE ERROR: " . $e->getMessage());
        }
    }
    
    public function getEpisodes()
    {
        $episodes_query = $this->_connection->prepare("SELECT * FROM `episodes` ORDER BY `Identifier` ASC");
        $episodes_query->execute();
        $episodes_results = $episodes_query->fetchAll();
        
        $people = array();
        foreach ($this->getPeople() as $person) {
            $people[$person->getID()] = $person;
        }
        
        $timestamps_query = $this->_connection->prepare("SELECT * FROM `timestamps` ORDER BY `Timestamp` ASC");
        $timestamps_query->execute();
        $timestamps_results = $timestamps_query->fetchAll();
        
        $timelines = array();
        foreach ($timestamps_results as $timestamp) {
            $timelines[$timestamp["Episode"]][] = new Timestamp($timestamp, $this->_connection);
        }
        
        $reviews_query = $this->_connection->prepare("SELECT * FROM `reviews` ORDER BY `ID` ASC");
        $reviews_query->execute();
        $reviews_results = $reviews_query->fetchAll();
        
        $reviews = array();
        foreach ($reviews_results as $review) {
            $reviews[$review["Episode"]][] = new Review($review, $this->_connection);
        }
        
        $episodes = array();
        foreach ($episodes_results as $episode) {
            $hosts = json_decode($episode["Hosts"], true);
            $episode["Hosts"] = array();
            foreach ($hosts as $host) {
                $episode["Hosts"][] = $people[$host];
            }
            
            $guests = json_decode($episode["Guests"], true);
            $episode["Guests"] = array();
            foreach ($guests as $guest) {
                $episode["Guests"][] = $people[$guest];
            }
            
            $sponsors = json_decode($episode["Sponsors"], true);
            $episode["Sponsors"] = array();
            foreach ($sponsors as $sponsor) {
                $episode["Sponsors"][] = $people[$sponsor];
            }
            
            if (isset($timelines[$episode["Identifier"]])) {
                $episode["Timestamps"] = $timelines[$episode["Identifier"]];
            } else {
                $episode["Timestamps"] = array();
            }
            
            if (isset($reviews[$episode["Identifier"]])) {
                $episode["Reviews"] = $reviews[$episode["Identifier"]];
            } else {
                $episode["Reviews"] = array();
            }
            
            $episodes[] = new Episode($episode, $this->_connection);
        }
        
        return $episodes;
    }
    
    public function getPeople()
    {
        $people_query = $this->_connection->prepare("SELECT * FROM `people` ORDER BY `ID` ASC");
        $people_query->execute();
        $people_results = $people_query->fetchAll();
        
        $people = array();
        foreach ($people_results as $person) {
            $people[] = new Person($person, $this->_connection);
        }
        
        return $people;
    }
    
    public function getSearchResults($query)
    {
        $search_results = array();
        if (!empty($query)) {
            $search_query = $this->_connection->prepare("SELECT `Episode`, `Timestamp`, `Value` FROM `timestamps` WHERE REPLACE(`Value`, :Replace, '') LIKE :Value");
            $search_query->bindValue(":Replace", "'");
            $search_query->bindValue(":Value", "%" . str_replace("'", "", trim($query) . "%"));
            $search_query->execute();

            foreach ($search_query->fetchAll() as $result) {
                $timestamp_data = array();
                $timestamp_data["Timestamp"] = $result["Timestamp"];
                $timestamp_data["Value"] = $result["Value"];
                $timestamp_data["HMS"] = Utilities::convertToHMS($result["Timestamp"]);
                
                $search_results[$result["Episode"]][] = $timestamp_data;
            }
        } else {
            $search_query = $this->_connection->prepare("SELECT * FROM `episodes`");
            $search_query->execute();

            foreach ($search_query->fetchAll() as $result) {
                $search_results[] = $result["Identifier"];
            }
        }

        return $search_results;
    }
}