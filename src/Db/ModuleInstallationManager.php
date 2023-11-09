<?php
namespace Allegro\Db;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class ModuleInstallationManager
{
    use ContainerAwareTrait;

    /**
     * ModuleInstallationManager constructor.
     */
    public function __construct()
    {
    }

    /**
     * Funkcja zwraca sql potrzebny do instalacji modułu do sklepu
     * @return string
     */
    public function getInstallationSql(): array
    {
        $sql = [];
        /* ps_allegro_account */
        $sql[] = "CREATE TABLE IF NOT EXISTS ps_allegro_account (id_allegro_account INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, authorized TINYINT(1) DEFAULT '0' NOT NULL, PRIMARY KEY(id_allegro_account));";

        /* ps_allegro_category */
        $sql[] = "CREATE TABLE IF NOT EXISTS ps_allegro_category (id_allegro_category INT AUTO_INCREMENT NOT NULL, allegro_id INT NOT NULL, name VARCHAR(255) NOT NULL, parent_id INT DEFAULT NULL, leaf TINYINT(1) NOT NULL, options LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)', PRIMARY KEY(id_allegro_category));";

        $sql[] = "CREATE TABLE IF NOT EXISTS `ps_allegro_external` (
                   `id_allegro_external` int(11) NOT NULL AUTO_INCREMENT,
                   `model_name` varchar(255) NOT NULL,
                   `internal_id` varchar(255) NOT NULL,
                   `external_id` varchar(255) NOT NULL,
                   `external_name` varchar(255) DEFAULT NULL,
                   PRIMARY KEY (`id_allegro_external`)
                )";

        return $sql;
    }

    /**
     * Funkcja zwraca sql potrzebny do usunięcia modułu ze sklepu
     * @return string
     */
    public function getDropSql(): string
    {
        $sql = '
             DROP TABLE IF EXISTS ps_allegro_account;
             DROP TABLE IF EXISTS ps_allegro_category;
             DROP TABLE IF EXISTS ps_allegro_external;
        ';
        return $sql;
    }
}