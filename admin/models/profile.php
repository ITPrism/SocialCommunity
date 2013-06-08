<?php
/**
 * @package      SocialCommunity
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2010 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * SocialCommunity is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.modeladmin');

/**
 * This model provides functionality for managing user profile.
 * 
 * @package      SocialCommunity
 * @subpackage   Components
 */
class SocialCommunityModelProfile extends JModelAdmin {
    
    /**
     * @var     string  The prefix to use with controller messages.
     * @since   1.6
     */
    protected $text_prefix = 'COM_SOCIALCOMMUNITY';
    
    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   type    The table type to instantiate
     * @param   string  A prefix for the table class name. Optional.
     * @param   array   Configuration array for model. Optional.
     * @return  JTable  A database object
     * @since   1.6
     */
    public function getTable($type = 'Profile', $prefix = 'SocialCommunityTable', $config = array()){
        return JTable::getInstance($type, $prefix, $config);
    }
    
    /**
     * Method to get the record form.
     *
     * @param   array   $data       An optional array of data for the form to interogate.
     * @param   boolean $loadData   True if the form is to load its own data (default case), false if not.
     * @return  JForm   A JForm object on success, false on failure
     * @since   1.6
     */
    public function getForm($data = array(), $loadData = true){
        
        // Get the form.
        $form = $this->loadForm($this->option.'.profile', 'profile', array('control' => 'jform', 'load_data' => $loadData));
        if(empty($form)){
            return false;
        }
        
        return $form;
    }
    
    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed   The data for the form.
     * @since   1.6
     */
    protected function loadFormData(){
        // Check the session for previously entered form data.
        $data = JFactory::getApplication()->getUserState($this->option.'.edit.profile.data', array());
        
        if(empty($data)){
            $data = $this->getItem();
        }
        
        return $data;
    }
    
    /**
     * Save data into the DB
     * 
     * @param $data   The data about item
     * 
     * @return     Item ID
     */
    public function save($data){
        
        $id     = JArrayHelper::getValue($data, "id");
        $name   = JArrayHelper::getValue($data, "name");
        $alias  = JArrayHelper::getValue($data, "alias");
        $bio    = JArrayHelper::getValue($data, "bio");
        
        // Load a record from the database
        $row = $this->getTable();
        $row->load($id);
        
        $row->set("name",  $name);
        $row->set("alias", $alias);
        $row->set("bio",   $bio);
        
        $this->prepareTable($row);
        
        $row->store();
        
        // Update the name in Joomla! users table
        $this->updateName($id, $name);
        
        return $row->id;
    }
    
    /**
     * Prepare and sanitise the table prior to saving.
     * @since	1.6
     */
    protected function prepareTable(&$table) {
         
        // Fix magic qutoes
        if( get_magic_quotes_gpc() ) {
            $table->name    = stripcslashes($table->name);
            $table->bio     = stripcslashes($table->bio);
        }
    
        // If an alias does not exist, I will generate the new one from the user name.
        if(!$table->alias) {
            $table->alias = $table->name;
        }
        $table->alias = JApplication::stringURLSafe($table->alias);
    }
    
    
    /**
     * 
     * This method updates the name of user in  
     * the Joomla! users table.
     */
    protected function updateName($id, $name) {
        
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        
        $query
            ->update($db->quoteName("#__users"))
            ->set($db->quoteName("name") ."=". $db->quote($name)) 
            ->where($db->quoteName("id") ."=". (int)$id) ;
            
        $db->setQuery($query);
        $db->query();
    }
    
    /**
     * 
     * This method creats records in the table of profiles
     * @param array $pks User IDs
     */
    public function create($pks) {
        
        $db     = JFactory::getDbo();
        $query  = $db->getQuery(true);
        
        // Get data about user from table "users"
        $query
            ->select("a.id, a.name")
            ->from($db->quoteName("#__users") . " AS a")
            ->where("a.id IN (".implode(",", $pks).")");
            
        $db->setQuery($query);
        $results = $db->loadAssocList("id");
    
        // Preparing data for inserting
        $values = array();
        foreach($results as $result) {
            $values[] = $db->quote($result["id"]).','.$db->quote($result["name"]).','.$db->quote(JApplication::stringURLSafe($result["name"])) ;
        }
        
        $query = $db->getQuery(true);
        $query
            ->insert($db->quoteName("#__itpsc_profiles"))
            ->columns( $db->quoteName(array("id", "name", "alias")) )
            ->values($values);
        
        $db->setQuery($query);
        $db->query();
        
    }
    
    /**
     * Verify and filter existing profiles 
     * and the new ones
     * 
     * @param  array $pks Primary Keys of users
     * @return array Return the keys of users without profiles. 
     */
    public function filterProfiles($pks) {
        
        $db    = JFactory::getDbo();
        $query = $db->getQuery(true);
        
        // Remove IDs what allready exists in the table of profiles
        $query
            ->select("a.id")
            ->from($db->quoteName("#__itpsc_profiles") . " AS a")
            ->where("a.id IN (".implode(",", $pks).")");
            
        $db->setQuery($query);
        $results = $db->loadColumn();
        
        $pks = array_diff($pks, $results);
        
        return $pks;
    }
}