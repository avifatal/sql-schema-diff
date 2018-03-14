<?php
namespace AviFatal\SqlSchemaDiff;

use  AviFatal\SqlSchemaDiff\Interfaces\ISqlConnectionDetails;

class MysqlConnectionDetails implements ISqlConnectionDetails
{
    private $host;
    private $userName;
    private $password;
    private $database;

    public function __construct(string $host, string $userName, string $password, string $database)
    {
        $this->host = $host;
        $this->userName = $userName;
        $this->password = $password;
        $this->database = $database;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host)
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getUserName(): string
    {
        return $this->userName;
    }

    /**
     * @param strong $userName
     */
    public function setUserName(string $userName)
    {
        $this->userName = $userName;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password)
    {
        $this->password = $password;
    }


    public function getDatabase(): string
    {
        return $this->getDatabase();
    }

    public function setDatabase(string $database)
    {
        $this->database = $database;
    }
}