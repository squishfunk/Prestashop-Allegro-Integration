<?php

class AllegroAccount extends ObjectModel
{
    public $id;
    public $name;
    public $authorized;

    public static $definition = array(
        'table' => 'allegro_accounts',
        'primary' => 'id_allegro_accounts',
        'fields' => array(
            'name' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true),
            'authorized' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
        ),
    );
}
