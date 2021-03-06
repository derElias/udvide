<?php
require_once 'vendor/autoload.php';

/**
 * Created by PhpStorm.
 * User: User
 * Date: 29.06.2017
 * Time: 13:10
 */
class udvidePluginAbout
{
    /** @var false|array
     * false if you cannot create Custom Targets;
     * associative array otherwise; like [$option_name => $option_type]
     *  where $option_type is an Integer from the above Constants
     * */
    public $customTargetOptions;
    /** @var false|array see above */
    public $additionalTargetOptions;
    /** @var false|array
     * false if you cannot create Custom Users;
     * associative array otherwise; like [$option_name => $option_type]
     *  where $option_type is an Integer from the above Constants
     * */
    public $customUserOptions;
    /** @var false|array see above */
    public $additionalUserOptions;

    // future Releases might support maps to be custom generated
    //  and add further plugin integration

    /**
     * @param array|false $customTargetOptions
     * @return udvidePluginAbout
     */
    public function setCustomTargetOptions($customTargetOptions)
    {
        $this->customTargetOptions = $customTargetOptions;
        return $this;
    }

    /**
     * @param array|false $customUserOptions
     * @return udvidePluginAbout
     */
    public function setCustomUserOptions($customUserOptions)
    {
        $this->customUserOptions = $customUserOptions;
        return $this;
    }

    /**
     * @param array|false $additionalTargetOptions
     * @return udvidePluginAbout
     */
    public function setAdditionalTargetOptions($additionalTargetOptions)
    {
        $this->additionalTargetOptions = $additionalTargetOptions;
        return $this;
    }

    /**
     * @param array|false $additionalUserOptions
     * @return udvidePluginAbout
     */
    public function setAdditionalUserOptions($additionalUserOptions)
    {
        $this->additionalUserOptions = $additionalUserOptions;
        return $this;
    }

}