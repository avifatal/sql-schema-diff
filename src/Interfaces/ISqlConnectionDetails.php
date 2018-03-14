<?php
namespace AviFatal\SqlSchemaDiff\Interfaces;


interface ISqlConnectionDetails{
    public function getHost(): string;
    public function setHost(string $host);
    public function getUserName(): strong;
    public function setUserName(strong $userName);
    public function getPassword(): string;
    public function setPassword(string $password);
    public function getDatabase(): string;
    public function setDatabase(string $database);
}