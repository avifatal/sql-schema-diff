<?php

namespace AviFatal\SqlSchemaDiff\Interfaces;

interface IExecuteMigrator{
    public function exec(string $command,string $question);
    public function yesNo(): bool;
    public function streamingInfo(string $string);
}