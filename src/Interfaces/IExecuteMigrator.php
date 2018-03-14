<?php

namespace AviFatal\SqlSchemaDiff\Interfaces;

interface IExecuteMigrator{
    public function exec(string $command,string $question);
    public function streamingInfo(string $string);
}