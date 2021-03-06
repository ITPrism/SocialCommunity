<?php
/**
 * @package      Socialcommunity
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;

// no direct access
defined('_JEXEC') or die;

class SocialcommunityModelImport extends JModelForm
{
    protected function populateState()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationAdministrator */

        // Load the filter state.
        $value = $app->getUserStateFromRequest('import.context', 'type', 'currencies');
        $this->setState('import.context', $value);
    }

    /**
     * Method to get the record form.
     *
     * @param   array   $data     An optional array of data for the form to interrogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  JForm|bool   A JForm object on success, false on failure
     * @since   1.6
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm($this->option . '.import', 'import', array('control' => 'jform', 'load_data' => $loadData));
        if (!$form) {
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
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = JFactory::getApplication()->getUserState($this->option . '.edit.import.data', array());

        return $data;
    }

    public function extractFile($file, $destFolder)
    {
        $filePath = '';

        $zipAdapter = JArchive::getAdapter('zip');
        $zipAdapter->extract($file, $destFolder);

        $dir = new DirectoryIterator($destFolder);

        foreach ($dir as $fileinfo) {
            $ext = JFile::getExt($fileinfo->getFilename());
            if (!$fileinfo->isDot() and strcmp('txt', $ext) === 0) {
                $filePath = $destFolder .'/'. JFile::makeSafe($fileinfo->getFilename());
                break;
            }
        }

        return $filePath;
    }

    public function uploadFile($uploadedFileData, $type)
    {
        jimport('joomla.filesystem.archive');

        $app = JFactory::getApplication();
        /** @var $app JApplicationAdministrator */

        $uploadedFile = ArrayHelper::getValue($uploadedFileData, 'tmp_name');
        $uploadedName = ArrayHelper::getValue($uploadedFileData, 'name');
        $errorCode    = ArrayHelper::getValue($uploadedFileData, 'error');

        // Prepare size validator.
        $KB       = pow(1024, 2);
        $fileSize = ArrayHelper::getValue($uploadedFileData, 'size', 0, 'int');

        $mediaParams   = JComponentHelper::getParams('com_media');
        $uploadMaxSize = $mediaParams->get('upload_maxsize') * $KB;

        // Prepare size validator.
        $sizeValidator = new Prism\File\Validator\Size($fileSize, $uploadMaxSize);

        // Prepare server validator.
        $serverValidator = new Prism\File\Validator\Server($errorCode, array(UPLOAD_ERR_NO_FILE));

        $file = new Prism\File\File($uploadedFile);
        $file
            ->addValidator($sizeValidator)
            ->addValidator($serverValidator);

        // Validate the file
        if (!$file->isValid()) {
            throw new RuntimeException($file->getError());
        }

        // Upload the file.
        $rootFolder      = JPath::clean($app->get('tmp_path'), '/');
        $filesystemLocal = new Prism\Filesystem\Adapter\Local($rootFolder);
        $sourceFile      = $filesystemLocal->upload($uploadedFileData);

        $fileName        = basename($sourceFile);

        // Extract file if it is archive
        $ext = StringHelper::strtolower(JFile::getExt($fileName));
        if (strcmp($ext, 'zip') === 0) {
            $destinationFolder = JPath::clean($app->get('tmp_path') .'/'. $type, '/');
            if (JFolder::exists($destinationFolder)) {
                JFolder::delete($destinationFolder);
            }

            $filePath = $this->extractFile($sourceFile, $destinationFolder);
        } else {
            $filePath = $sourceFile;
        }

        return $filePath;
    }
    
    /**
     *
     * Import locations from TXT or XML file.
     * The TXT file comes from geodata.org
     * The XML file is generated by the current extension ( Socialcommunity )
     *
     * @param string $file    A path to file
     * @param integer   $minPopulation Reset existing IDs with new ones.
     */
    public function importLocations($file, $minPopulation = 0)
    {
        if (JFile::exists($file)) {
            $this->removeAll('locations');

            $db = $this->getDbo();

            $items   = array();
            $columns = array('id', 'name', 'latitude', 'longitude', 'country_code', 'timezone');

            $i = 0;
            foreach (Prism\Utilities\FileHelper::getLine($file) as $key => $geodata) {
                $item = mb_split("\t", $geodata);

                // Check for missing ascii characters name
                $name = StringHelper::trim($item[2]);
                if (!$name) {
                    // If missing ascii characters name, use utf-8 characters name
                    $name = StringHelper::trim($item[1]);
                }

                // If missing name, skip the record
                if (!$name) {
                    continue;
                }

                if ($minPopulation > (int)$item[14]) {
                    continue;
                }

                $id = StringHelper::trim($item[0]);

                $items[] = $id . ',' . $db->quote($name) . ',' . $db->quote(StringHelper::trim($item[4])) . ',' . $db->quote(StringHelper::trim($item[5])) . ',' . $db->quote(StringHelper::trim($item[8])) . ',' . $db->quote(StringHelper::trim($item[17]));
                $i++;
                if ($i === 500) {
                    $i = 0;

                    $query = $db->getQuery(true);
                    $query
                        ->insert($db->quoteName('#__itpsc_locations'))
                        ->columns($db->quoteName($columns))
                        ->values($items);

                    $db->setQuery($query);
                    $db->execute();

                    $items = array();
                }
            }

            if (count($items) > 0) {
                $query = $db->getQuery(true);

                $query
                    ->insert($db->quoteName('#__itpsc_locations'))
                    ->columns($db->quoteName($columns))
                    ->values($items);

                $db->setQuery($query);
                $db->execute();
            }

            unset($content, $items);
        }
    }

    /**
     * Import countries from XML file.
     * Downloaded the source from https://github.com/ITPrism/country-list
     *
     * @param string $file    A path to file
     *
     * @throws \RuntimeException
     */
    public function importCountries($file)
    {
        if (JFile::exists($file)) {
            $items = array();

            $xmlstr  = file_get_contents($file);
            $content = new SimpleXMLElement($xmlstr);

            $this->removeAll('countries');

            $db = $this->getDbo();

            foreach ($content->country as $item) {
                $name = StringHelper::trim($item->name);
                $code = StringHelper::trim($item->code);
                if (!$name or !$code) {
                    continue;
                }

                $items[] = 'NULL, ' . $db->quote($name) . ',' . $db->quote($code) . ',' . $db->quote($item->latitude) . ',' . $db->quote($item->longitude) . ',' . $db->quote($item->timezone);
            }

            $columns = array('id', 'name', 'code', 'latitude', 'longitude', 'timezone');

            $query = $db->getQuery(true);

            $query
                ->insert($db->quoteName('#__itpsc_countries'))
                ->columns($db->quoteName($columns))
                ->values($items);

            $db->setQuery($query);
            $db->execute();
        }
    }

    public function removeAll($resource)
    {
        if (!$resource) {
            throw new InvalidArgumentException('COM_SOCIALCOMMUNITY_ERROR_INVALID_RESOURCE_TYPE');
        }

        $db = $this->getDbo();

        switch ($resource) {
            case 'countries':
                $db->truncateTable('#__itpsc_countries');
                break;

            case 'locations':
                $db->truncateTable('#__itpsc_locations');
                break;
        }
    }
}
