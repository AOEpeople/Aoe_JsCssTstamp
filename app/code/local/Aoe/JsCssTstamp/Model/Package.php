<?php

/**
 * Rewriting package class to add some custom version key to bundled files
 *
 * @author Fabrizio Branca
 */
class Aoe_JsCssTstamp_Model_Package extends Mage_Core_Model_Design_Package
{
    const CACHEKEY = 'aoe_jscsststamp_versionkey';

    protected $cssProtocolRelativeUris;
    protected $jsProtocolRelativeUris;
    protected $dbStorage;
    protected $addTstampToAssets;
    protected $storeMinifiedCssFolder;
    protected $storeMinifiedJsFolder;

    /**
     * Constructor
     *
     * Hint: Parent class is a plain php class not extending anything. So don't try to move this content to _construct()
     */
    public function __construct()
    {
        $this->cssProtocolRelativeUris = Mage::getStoreConfig('dev/css/protocolRelativeUris');
        $this->jsProtocolRelativeUris  = Mage::getStoreConfig('dev/js/protocolRelativeUris');
        $this->addTstampToAssets       = Mage::getStoreConfig('dev/css/addTstampToAssets');
        $this->storeMinifiedCssFolder  = rtrim(Mage::getBaseDir(), DS)
            . DS . trim(Mage::getStoreConfig('dev/css/storeMinifiedCssFolder'), DS);
        $this->storeMinifiedJsFolder   = rtrim(Mage::getBaseDir(), DS)
            . DS . trim(Mage::getStoreConfig('dev/js/storeMinifiedJsFolder'), DS);
    }

    /**
     * Get db storage
     *
     * @return Mage_Core_Model_File_Storage_Database
     */
    protected function getDbStorage()
    {
        if (is_null($this->dbStorage)) {
            $this->dbStorage = Mage::helper('core/file_storage_database')->getStorageDatabaseModel();
        }

        return $this->dbStorage;
    }

    /**
     * Overwrite original method in order to add a version key
     *
     * @param array $files
     *
     * @return string
     */
    public function getMergedJsUrl($files)
    {

        $versionKey     = $this->getVersionKey();
        $targetFilename = md5(implode(',', $files)) . '.' . $versionKey . '.js';
        $targetDir      = $this->_initMergerDir('js');
        if (!$targetDir) {
            return '';
        }

        $mergedJsUrl = $this->generateMergedUrl('js', $files, $targetDir, $targetFilename);
        if ($this->jsProtocolRelativeUris) {
            $mergedJsUrl = $this->convertToProtocolRelativeUri($mergedJsUrl);
        }

        return $mergedJsUrl;
    }

    /**
     * Before merge JS callback function
     *
     * @param string $file
     * @param string $contents
     *
     * @return string
     */
    public function beforeMergeJs($file, $contents)
    {
        $minContent = $this->useMinifiedVersion($file);
        if ($minContent !== false) {
            $contents = $minContent;
        }

        $contents = "\n\n/* FILE: " . basename($file) . " */\n" . $contents;

        return $contents;
    }

    /**
     * Before merge CSS callback function
     *
     * @param string $file
     * @param string $contents
     *
     * @return string
     */
    public function beforeMergeCss($file, $contents)
    {
        $minContent = $this->useMinifiedVersion($file);
        if ($minContent !== false) {
            $contents = $minContent;
        }

        $contents = "\n\n/* FILE: " . basename($file) . " */\n" . $contents;

        return parent::beforeMergeCss($file, $contents);
    }

    /**
     * Checks if minified version of the given file exist. And if returns its content
     *
     * @param string $file
     * @return string|bool the content of the file else false
     */
    protected function useMinifiedVersion($file)
    {
        $parts = pathinfo($file);
        // Add .min to the extension of the original filename
        $minFile = $parts['dirname'] . DS . $parts['filename'] . '.min.' . $parts['extension'];

        if (file_exists($minFile)) {
            // return the content of the min file @see Mage_Core_Helper_Data -> mergeFiles()
            return file_get_contents($minFile) . "\n";
        } else {
            $pathRelativeToBase = str_replace(Mage::getBaseDir(), '', $parts['dirname']);
            $pathRelativeToBase = ltrim($pathRelativeToBase, DS);

            switch ($parts['extension']) {
                case 'js':
                    $minFile = $this->storeMinifiedJsFolder . DS . $pathRelativeToBase
                        . DS . $parts['filename'] . '.min.' . $parts['extension'];
                    break;
                case 'css':
                default:
                    $minFile = $this->storeMinifiedCssFolder . DS . $pathRelativeToBase
                        . DS . $parts['filename'] . '.min.' . $parts['extension'];
                    break;
            }

            if (file_exists($minFile)) {
                // return the content of the min file @see Mage_Core_Helper_Data -> mergeFiles()
                return file_get_contents($minFile) . "\n";
            }
        }

        return false;
    }

    /**
     * Overwrite original method in order to add a version key
     *
     * @param array $files
     *
     * @return string
     */
    public function getMergedCssUrl($files)
    {
        $versionKey     = $this->getVersionKey();
        $targetFilename = md5(implode(',', $files)) . '.' . $versionKey . '.css';
        $targetDir      = $this->_initMergerDir('css');
        if (!$targetDir) {
            return '';
        }

        $mergedCssUrl = $this->generateMergedUrl('css', $files, $targetDir, $targetFilename);
        if ($this->cssProtocolRelativeUris) {
            $mergedCssUrl = $this->convertToProtocolRelativeUri($mergedCssUrl);
        }

        return $mergedCssUrl;
    }

    /**
     * Generate url for merged file of given $type
     *
     * @param string $type
     * @param array  $files
     * @param string $targetDir
     * @param string $targetFilename
     *
     * @return string
     */
    protected function generateMergedUrl($type, array $files, $targetDir, $targetFilename)
    {
        // This is to fix the secure/unsecure URL problem
        $store = $this->getStore();
        if ($store->isAdmin()) {
                $secure = $store->isAdminUrlSecure();
        } else {
                $secure = $store->isFrontUrlSecure() && Mage::app()->getRequest()->isSecure();
        }
        $targetFilename = ($secure ? 's' : 'u') . '.' . $targetFilename;

        $path = $targetDir . DS . $targetFilename;

        // relative path
        $relativePath = ltrim(str_replace(Mage::getBaseDir('media'), '', $path), DS);

        $mergedUrl = Mage::getBaseUrl('media') . $type . DS . $targetFilename;
        $storage = Mage::getStoreConfig('dev/' . $type . '/storage');

        /* @var $coreHelper Mage_Core_Helper_Data */
        $coreHelper = Mage::helper('core');

        switch ($storage) {
            case Aoe_JsCssTstamp_Model_System_Config_Source_Storage::FILESYSTEM;
                /**
                 * Using the file system to store the file
                 */
                if (!$coreHelper->mergeFiles($files, $path, false, array($this, 'beforeMerge' . ucfirst($type)), $type)) {
                    Mage::throwException("Error while merging {$type} files to path: " . $relativePath);
                }
                break;
            case Aoe_JsCssTstamp_Model_System_Config_Source_Storage::DATABASE:
                /**
                 * Using the database to store the files.
                 * First check if the file exists in the datase. If it exists, no further action is required.
                 * The file will be delivered directly by a mod_rewrite rule pointing to get.php
                 */
                /* @var $dbStorage Mage_Core_Model_File_Storage_Database */
                $dbStorage = $this->getDbStorage();
                if (!$dbStorage->fileExists($relativePath)) {
                    if (!$coreHelper->mergeFiles($files, $path, false, array($this, 'beforeMerge' . ucfirst($type)), $type)) {
                        Mage::throwException("Error while merging {$type} files to path: " . $relativePath);
                    }
                    $dbStorage->saveFile($relativePath);
                }
                break;
            case Aoe_JsCssTstamp_Model_System_Config_Source_Storage::CDN;
                /**
                 * Using the cdn to store the file.
                 * Make sure to point the urls correctly to the cdn so that files will be delivered directly from there
                 * Also note, that Cloudfront using an Amazon S3 bucket does not support compression!
                 */
                // check cdn (if available)
                $cdnUrl = Mage::helper('aoejscsststamp')->getCdnUrl($path);
                if (!$cdnUrl) {
                    if (!$coreHelper->mergeFiles($files, $path, false, array($this, 'beforeMerge' . ucfirst($type)), $type)) {
                        Mage::throwException("Error while merging {$type} files to path: " . $relativePath);
                    }

                    // store file to cdn (if available)
                    $cdnUrl = Mage::helper('aoejscsststamp')->storeInCdn($path);
                }

                if ($cdnUrl) {
                    $mergedUrl = $cdnUrl;
                } else {
                    Mage::throwException('Error while processing url');
                }
                break;
            default:
                Mage::throwException('Unsupported storage mode');
                break;
        }

        return $mergedUrl;
    }

    /**
     * Get a cached timestamp as version key
     *
     * @return int timestamp
     */
    protected function getVersionKey()
    {
        $timestamp = Mage::app()->loadCache(self::CACHEKEY);
        if (empty($timestamp)) {
            $timestamp = time();
            Mage::app()->saveCache($timestamp, self::CACHEKEY, array(), null);
        }

        return $timestamp;
    }

    /**
     * Convert uri to protocol independent uri
     * E.g. http://example.com -> //example.com
     *
     * @param string $uri
     *
     * @return string
     */
    protected function convertToProtocolRelativeUri($uri)
    {
        return preg_replace('/^https?:/i', '', $uri);
    }

    /**
     * Convert uri to protocol independent uri
     * E.g. http://example.com -> //example.com
     *
     * @param string $uri
     *
     * @return string
     */
    protected function _prepareUrl($uri)
    {
        $uri = parent::_prepareUrl($uri);
        if ($this->cssProtocolRelativeUris) {
            $uri = $this->convertToProtocolRelativeUri($uri);
        }

        if ($this->addTstampToAssets) {
            $matches = array();
            if (preg_match('/(.*)\.(gif|png|jpg)$/i', $uri, $matches)) {
                $uri = $matches[1] . '.' . $this->getVersionKey() . '.' . $matches[2];
            }
        }

        return $uri;
    }

    /**
     * Get skin file url
     *
     * @param string $file
     * @param array $params
     * @return string
     */
    public function getSkinUrl($file = null, array $params = array())
    {
        Varien_Profiler::start(__METHOD__);
        if (empty($params['_type'])) {
            $params['_type'] = 'skin';
        }
        if (empty($params['_default'])) {
            $params['_default'] = false;
        }
        $this->updateParamDefaults($params);
        if (!empty($file)) {
            $result = $this->_fallback($file, $params, array(
                array(),
                array('_theme' => $this->getFallbackTheme()),
                array('_theme' => self::DEFAULT_THEME),
            ));
        }
        $result = $this->getSkinBaseUrl($params) . (empty($file) ? '' : $file);
        Varien_Profiler::stop(__METHOD__);

        if ($this->addTstampToAssets) {
            $matches = array();
            if (preg_match('/(.*)\.(css|js)$/i', $result, $matches)) {
                $result = $matches[1] . '.' . $this->getVersionKey() . '.' . $matches[2];
            }
        }
        return $result;
    }
}
