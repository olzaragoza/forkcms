<?php

namespace Backend\Core\Engine;

use \MatthiasMullie\Minify;
use \Symfony\Component\HttpKernel\KernelInterface;
use Backend\Core\Engine\Model as BackendModel;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

/**
 * This class will be used to alter the head-part of the HTML-document that will be created by he Backend
 * Therefore it will handle meta-stuff (title, including JS, including CSS, ...)
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 * @author Matthias Mullie <forkcms@mullie.eu>
 */
class Header extends \Backend\Core\Engine\Base\Object
{
    /**
     * All added CSS-files
     *
     * @var array
     */
    private $cssFiles = array();

    /**
     * Data that will be passed to js
     *
     * @var array
     */
    private $jsData = array();

    /**
     * All added JS-files
     *
     * @var	array
     */
    private $jsFiles = array();

    /**
     * Template instance
     *
     * @var	Template
     */
    private $tpl;

    /**
     * URL-instance
     *
     * @var	Url
     */
    private $URL;

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);

        $this->getContainer()->set('header', $this);

        // grab from the reference
        $this->URL = $this->getContainer()->get('url');
        $this->tpl = $this->getContainer()->get('template');
    }

    /**
     * Add a CSS-file.
     *
     * If you don't specify a module, the current one will be used to automatically create the path to the file.
     * Automatic creation of the filename will result in
     *   /backend/modules/MODULE/layout/css/FILE (for modules)
     *   /backend/core/layout/css/FILE (for core)
     *
     * If you set overwritePath to true, the above-described automatic path creation will not happen, instead the
     * file-parameter will be used as path; which we then expect to be a full path (It has to start with a slash '/')
     *
     * @param string $file The name of the file to load.
     * @param string $module The module wherein the file is located.
     * @param bool $overwritePath Should we overwrite the full path?
     * @param bool $minify Should the CSS be minified?
     * @param bool $addTimestamp May we add a timestamp for caching purposes?
     */
    public function addCSS($file, $module = null, $overwritePath = false, $minify = true, $addTimestamp = false)
    {
        $file = (string) $file;
        $module = (string) ($module !== null) ? $module : $this->URL->getModule();
        $overwritePath = (bool) $overwritePath;
        $minify = (bool) $minify;
        $addTimestamp = (bool) $addTimestamp;

        // no actual path given: create
        if(!$overwritePath) {
            // we have to build the path, but core is a special one
            if($module !== 'Core') $file = '/src/Backend/Modules/' . $module . '/Layout/css/' . $file;

            // core is special because it isn't a real module
            else $file = '/src/Backend/Core/Layout/css/' . $file;
        }

        // no minifying when debugging
        if(SPOON_DEBUG) $minify = false;

        // try to minify
        if($minify) $file = $this->minifyCSS($file);

        // in array
        $inArray = false;

        // check if the file already exists in the array
        foreach($this->cssFiles as $row) if($row['file'] == $file) $inArray = true;

        // add to array if it isn't there already
        if(!$inArray) {
            // build temporary array
            $temp['file'] = (string) $file;
            $temp['add_timestamp'] = $addTimestamp;

            // add to files
            $this->cssFiles[] = $temp;
        }
    }

    /**
     * Add a JS-file.
     * If you don't specify a module, the current one will be used
     * If you set overwritePath to true we expect a full path (It has to start with a /)
     *
     * @param string $file The file to load.
     * @param string $module The module wherein the file is located.
     * @param bool $minify Should the module be minified?
     * @param bool $overwritePath Should we overwrite the full path?
     * @param bool $addTimestamp May we add a timestamp for caching purposes?
     */
    public function addJS($file, $module = null, $minify = true, $overwritePath = false, $addTimestamp = false)
    {
        $file = (string) $file;
        $module = (string) ($module !== null) ? $module : $this->URL->getModule();
        $minify = (bool) $minify;
        $overwritePath = (bool) $overwritePath;
        $addTimestamp = (bool) $addTimestamp;

        // no minifying when debugging
        if(SPOON_DEBUG) $minify = false;

        // is the given path the real path?
        if(!$overwritePath) {
            // we have to build the path, but core is a special one
            if($module !== 'Core') $file = '/src/Backend/Modules/' . $module . '/Js/' . $file;

            // core is special because it isn't a real module
            else $file = '/src/Backend/Core/Js/' . $file;
        }

        // try to minify
        if($minify) $file = $this->minifyJS($file);

        // already in array?
        if(!in_array(array('file' => $file, 'add_timestamp' => $addTimestamp), $this->jsFiles)) {
            // add to files
            $this->jsFiles[] = array('file' => $file, 'add_timestamp' => $addTimestamp);
        }
    }

    /**
     * Add data into the jsData
     *
     * @param string $module	The name of the module.
     * @param string $key		The key whereunder the value will be stored.
     * @param mixed $value		The value
     */
    public function addJsData($module, $key, $value)
    {
        $this->jsData[$module][$key] = $value;
    }

    /**
     * Get all added CSS files
     *
     * @return array
     */
    public function getCSSFiles()
    {
        // fetch files
        return $this->cssFiles;
    }

    /**
     * get all added javascript files
     *
     * @return array
     */
    public function getJSFiles()
    {
        return $this->jsFiles;
    }

    /**
     * Minify a CSS-file
     *
     * @param string $file The file to be minified.
     * @return string
     */
    private function minifyCSS($file)
    {
        // create unique filename
        $fileName = md5($file) . '.css';
        $finalURL = BACKEND_CACHE_URL . '/MinifiedCss/' . $fileName;
        $finalPath = BACKEND_CACHE_PATH . '/MinifiedCss/' . $fileName;

        // check that file does not yet exist or has been updated already
        if(!is_file($finalPath) || filemtime(PATH_WWW . $file) > filemtime($finalPath)) {
            // minify the file
            $css = new Minify\CSS(PATH_WWW . $file);
            $css->minify($finalPath);
        }

        return $finalURL;
    }

    /**
     * Minify a JS-file
     *
     * @param string $file The file to be minified.
     * @return string
     */
    private function minifyJS($file)
    {
        // create unique filename
        $fileName = md5($file) . '.js';
        $finalURL = BACKEND_CACHE_URL . '/MinifiedJs/' . $fileName;
        $finalPath = BACKEND_CACHE_PATH . '/MinifiedJs/' . $fileName;

        // check that file does not yet exist or has been updated already
        if(!is_file($finalPath) || filemtime(PATH_WWW . $file) > filemtime($finalPath)) {
            // minify the file
            $js = new Minify\JS(PATH_WWW . $file);
            $js->minify($finalPath);
        }

        return $finalURL;
    }

    /**
     * Parse the header into the template
     */
    public function parse()
    {
        // parse CSS
        $this->parseCSS();

        // parse JS
        $this->parseJS();
    }

    /**
     * Parse the CSS-files
     */
    public function parseCSS()
    {
        // init var
        $cssFiles = array();
        $existingCSSFiles = $this->getCSSFiles();

        // if there aren't any JS-files added we don't need to do something
        if(!empty($existingCSSFiles)) {
            foreach($existingCSSFiles as $file) {
                // add lastmodified time
                if($file['add_timestamp'] !== false) $file['file'] .= (strpos($file['file'], '?') !== false) ? '&m=' . LAST_MODIFIED_TIME : '?m=' . LAST_MODIFIED_TIME;

                // add
                $cssFiles[] = $file;
            }
        }

        // css-files
        $this->tpl->assign('cssFiles', $cssFiles);
    }

    /**
     * Parse the JS-files
     */
    public function parseJS()
    {
        $jsFiles = array();
        $existingJSFiles = $this->getJSFiles();

        // if there aren't any JS-files added we don't need to do something
        if(!empty($existingJSFiles)) {
            // some files should be cached, even if we don't want cached (mostly libraries)
            $ignoreCache = array(
                '/src/Backend/Core/Js/jquery/jquery.js',
                '/src/Backend/Core/Js/jquery/jquery.ui.js',
                '/src/Backend/Core/Js/ckeditor/jquery.ui.dialog.patch.js',
                '/src/Backend/Core/Js/jquery/jquery.tools.js',
                '/src/Backend/Core/Js/jquery/jquery.backend.js',
                '/src/Backend/Core/Js/ckeditor/ckeditor.js',
                '/src/Backend/Core/Js/ckeditor/adapters/jquery.js',
                '/src/Backend/Core/Js/ckfinder/ckfinder.js'
            );

            foreach($existingJSFiles as $file) {
                // some files shouldn't be uncachable
                if(in_array($file['file'], $ignoreCache) || $file['add_timestamp'] === false) $file = array('file' => $file['file']);

                // make the file uncachable
                else {
                    // if the file is processed by PHP we don't want any caching @todo: why a reference to the frontend?
                    if(substr($file['file'], 0, 11) == '/frontend/js') $file = array('file' => $file['file'] . '&amp;m=' . time());

                    // add lastmodified time
                    else {
                        $modifiedTime = (strpos($file['file'], '?') !== false) ? '&amp;m=' . LAST_MODIFIED_TIME : '?m=' . LAST_MODIFIED_TIME;
                        $file = array('file' => $file['file'] . $modifiedTime);
                    }
                }

                // add
                $jsFiles[] = $file;
            }
        }

        // assign JS-files
        $this->tpl->assign('jsFiles', $jsFiles);

        // fetch preferred interface language
        if(Authentication::getUser()->isAuthenticated()) {
            $interfaceLanguage = (string) Authentication::getUser()->getSetting('interface_language');
        } else $interfaceLanguage = Language::getInterfaceLanguage();

        // some default stuff
        $this->jsData['debug'] = SPOON_DEBUG;
        $this->jsData['site']['domain'] = SITE_DOMAIN;
        $this->jsData['editor']['language'] = $interfaceLanguage;
        $this->jsData['interface_language'] = $interfaceLanguage;

        // is the user object filled?
        if(Authentication::getUser()->isAuthenticated()) {
            $this->jsData['editor']['language'] = (string) Authentication::getUser()->getSetting('interface_language');
        }

        // CKeditor has support for simplified Chinese, but the language is called zh-cn instead of zn
        if($this->jsData['editor']['language'] == 'zh') $this->jsData['editor']['language'] = 'zh-cn';

        // theme
        if(BackendModel::getModuleSetting('Core', 'theme') !== null) {
            $this->jsData['theme']['theme'] = BackendModel::getModuleSetting('Core', 'theme');
            $this->jsData['theme']['path'] = FRONTEND_PATH . '/Themes/' . BackendModel::getModuleSetting('Core', 'theme');
            $this->jsData['theme']['has_css'] = (is_file(FRONTEND_PATH . '/Themes/' . BackendModel::getModuleSetting('Core', 'theme') . '/Core/Layout/css/screen.css'));
            $this->jsData['theme']['has_editor_css'] = (is_file(FRONTEND_PATH . '/Themes/' . BackendModel::getModuleSetting('Core', 'theme') . '/Core/Layout/css/editor_content.css'));
        }

        // encode and add
        $jsData = json_encode($this->jsData);
        $this->tpl->assign('jsData', 'var jsData = ' . $jsData . ';' . "\n");
    }
}
