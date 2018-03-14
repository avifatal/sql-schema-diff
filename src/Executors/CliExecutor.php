<?php
namespace AviFatal\Executors;

class CliExecutor implements IExecuteMigrato{

    private $command;
    private $database;

    public function __construct(Command $command,MySqlConnection $database)
    {
        $this->command = $command;
        $this->database = $database;
    }

    public function exec(string $command, string $question){


        $this->command->info($question);
        if ($this->yesNo()) {
            return $this->database->unprepared($command);
        } else {
            die();
        }
    }

    public function yesNo(): bool
    {
        $res = $this->command->ask("[Y,N]");
        if (strtolower($res) != 'y' && strtolower($res) != 'n') {
            $this->command->error("Please choose yes or no");
            return $this->yesNo();
        } else {
            return strtolower($res) == 'y';
        }
    }

    public function streamingInfo(string $message)
    {
        $this->command->info($message);
    }
}