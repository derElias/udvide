<?php
require_once 'vendor/autoload.php';


abstract class udvide extends udvide_entity
{

    //<editor-fold desc="Constructors">
    /**
     * indirect constructor
     * @param string $name
     * @return static
     */
    public static function fromDB(string $name = '') {
        $instance = new static();
        if (!empty($name)) {
            $instance->setName($name)->read(); // phpstorm bug see https://stackoverflow.com/questions/44803353/returntype-self-in-abstract-php-class/44803407?noredirect=1
        }
        return $instance;
    }

    /**
     * indirect constructor
     * @param array|null $array
     * @return static
     */
    public static function fromArray(array $array = null) {
        $instance = new static();
        if (!empty($array))
            $instance->set($array);
        return $instance;
    }

    /**
     * indirect constructor
     * @param string $json
     * @return static
     */
    public static function fromJSON(string $json = '') {
        $instance = new static();
        if (!empty($json))
            $instance->set(json_decode($json, true));
        return $instance;
    }
    //</editor-fold>

    public abstract function read();
    public abstract function update(string $subject = null);

    public abstract function setName(string $name);






}