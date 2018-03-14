<?php
namespace AviFatal\SqlSchemaDiff;

use AviFatal\SqlSchemaDiff\Interfaces\ICoreMigrator;
use AviFatal\SqlSchemaDiff\Interfaces\IExecuteMigrator;
use Illuminate\Database\Capsule\Manager as Capsule;
use AviFatal\SqlSchemaDiff\Interfaces\ISqlConnectionDetails;
use Illuminate\Database\Connection;



class MysqlCoreMigrator implements ICoreMigrator
{
    const STARS = "\n\n /******************************************************************************/\n\n";

    private $source_db;
    private $target_db;
    private $executeMigrator;
    private $sourceConnection;
    private $targetConnection;

    public function __construct(ISqlConnectionDetails $source, ISqlConnectionDetails $target, IExecuteMigrator $executeMigrator)
    {

        $source_capsule = new Capsule;

        $source_capsule->addConnection([
            'driver' => 'mysql',
            'host' => $source->getHost(),
            'database' => $source->getDatabase(),
            'username' => $source->getUserName(),
            'password' => $source->getPassword(),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);

        $target_capsule = new Capsule;

        $target_capsule->addConnection([
            'driver' => 'mysql',
            'host' => $target->getHost(),
            'database' => $target->getDatabase(),
            'username' => $target->getUserName(),
            'password' => $target->getPassword(),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);


        $this->source_db = $source_capsule->getConnection()->getDatabaseName();
        $this->target_db = $target_capsule->getConnection()->getDatabaseName();

        $this->sourceConnection = $source_capsule->getConnection();
        $this->targetConnection = $target_capsule->getConnection();

        $this->executeMigrator = $executeMigrator;

    }

    public function migrate()
    {
        $this->targetConnection->unprepared("SET foreign_key_checks = 0;");
        $source_tables = $this->skip($this->getAllTables($this->sourceConnection));
        $target_tables = $this->skip($this->getAllTables($this->targetConnection));

        $create = $source_tables->diff($target_tables);
        $this->createTables($create);
        $drop = $target_tables->diff($source_tables);

        $this->dropTables($drop);


        foreach ($source_tables as $key => $table) {
            $this->addColumns($table);
        }

        foreach ($source_tables as $key => $table) {
            $this->addFks($table);
        }

        foreach ($source_tables as $key => $table) {
            $this->dropFks($table);
        }

        foreach ($source_tables as $key => $table) {
            $this->diffColumns($table);
        }

        foreach ($source_tables as $key => $table) {
            $this->dropColumns($table);
        }

        $this->targetConnection->unprepared("SET foreign_key_checks = 1;");

    }

    private function toCollectionArray($collection)
    {
        return (json_decode(json_encode($collection), true));
    }

    private function diffColumns($table)
    {
        $target_cols = $this->toCollectionArray($this->getAllColmns($table, $this->targetConnection));
        $source_cols = $this->toCollectionArray($this->getAllColmns($table, $this->sourceConnection));

        foreach ($source_cols as $key => $val) {
            $diff = array_diff_assoc($val, $target_cols[$key]);
            if (!empty($diff)) {
                $this->changeSingleColumn($source_cols[$key], $table);
            }

        }
    }

    private function addColumns($table)
    {
        $target_cols = $this->toCollectionArray($this->getAllColmns($table, $this->targetConnection));
        $source_cols = $this->toCollectionArray($this->getAllColmns($table, $this->sourceConnection));

        $add_cols = array_diff_key($source_cols, $target_cols);

        foreach ($add_cols as $key => $col) {
            $this->addSingleColumn($col, $table);
        }
    }

    private function addSingleColumn($col, $table)
    {
        $this->singleColumn($col, $table, 'ADD');
    }

    private function changeSingleColumn($col, $table)
    {
        $this->singleColumn($col, $table, 'MODIFY');
    }

    private function singleColumn($col, $table, $do)
    {
        $null = $col['IS_NULLABLE'] == 'YES' ? $null = 'NULL' : 'NOT NULL';
        $default = !empty($col['COLUMN_DEFAULT']) ? 'DEFAULT ' . $col['COLUMN_DEFAULT'] : '';
        $query = "ALTER TABLE {$this->target_db}.{$table}
                  $do COLUMN {$col['COLUMN_NAME']} {$col['COLUMN_TYPE']} $null $default;";
        $this->exec($query);
    }

    private function dropColumns($table)
    {
        $target_cols = $this->getAllColmns($table, $this->targetConnection);
        $source_cols = $this->getAllColmns($table, $this->sourceConnection);
        $drop_cols = ($target_cols->diffKeys($source_cols));

        foreach ($drop_cols as $key => $col) {
            $this->dropSingleColumn($col->COLUMN_NAME, $table);
        }

    }

    private function dropSingleColumn($col_name, $table)
    {

        $query = "ALTER TABLE {$this->target_db}.{$table}
                  DROP COLUMN {$col_name}";
        $this->exec($query);

    }

    private function addFks($table)
    {
        $source_fk = $this->getAllFK($table, $this->sourceConnection);
        $target_fk = $this->getAllFK($table, $this->targetConnection);
        $add_fks = $source_fk->diffKeys($target_fk);
        $this->addFk($add_fks, $table);
    }

    private function dropFKs($table)
    {
        $source_fk = $this->getAllFK($table, $this->sourceConnection);
        $target_fk = $this->getAllFK($table, $this->targetConnection);
        $drop_fks = $target_fk->diffKeys($source_fk);
        $this->dropFk($drop_fks, $table);
    }

    private function dropFk($drop_fks, $table)
    {
        foreach ($drop_fks as $key => $fk) {
            if (!empty($fk->REFERENCED_TABLE_NAME)) {
                $query = "ALTER TABLE {$this->target_db}.{$table}
    DROP FOREIGN KEY {$key};

    ";
                $this->exec($query);
            }
        }
    }

    private function addFk($add_fks, $table)
    {
        foreach ($add_fks as $key => $fk) {
            if (!empty($fk->REFERENCED_TABLE_NAME)) {
                $query = "ALTER TABLE {$this->target_db}.{$table}
                        ADD CONSTRAINT {$key}
                        FOREIGN KEY ({$fk->COLUMN_NAME}) REFERENCES {$fk->REFERENCED_TABLE_NAME}({$fk->REFERENCED_COLUMN_NAME});
    ";
                $this->exec($query);
            }
        }
    }

    private function getAllColmns($table, Connection $connection)
    {
        $query = "SELECT
                        COLUMN_NAME,COLUMN_DEFAULT,IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,COLUMN_TYPE,EXTRA
                        FROM
                        INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = '{$connection->getDatabaseName()}' AND TABLE_NAME = '$table'";
        $res = (collect($connection->select($query)));
        return $res->keyBy('COLUMN_NAME');
    }

    private function exec($query)
    {
        $question = "About to run: " . self::STARS . "  $query  " . self::STARS . "  Do you agree?";
        $this->executeMigrator->exec($query, $question);

    }

    private function dropTables($drop)
    {
        foreach ($drop as $key => $table) {
            $statement = "DROP TABLE `{$table}`;";
            $this->executeMigrator->exec($statement, "The table $table was not found on the source database ($this->source_db), would you like to DROP it? ($statement)");
        }
    }

    private function createTables($create)
    {

        foreach ($create as $key => $table) {
            $res = $this->sourceConnection->select("show create table `{$table}` ;");
            $create_table = (collect($res)->toArray()[0]->{"Create Table"});
            $this->executeMigrator->streamingInfo("The table $table was not found on the desitnation database ($this->target_db), would you like to create it?");
            $this->exec($create_table);

        }
        return $create;
    }

    private function skip($list)
    {
        $skip_tables = ['migrations'];
        return $list->forget($skip_tables);
    }

    private function getAllTables(Connection $connection)
    {
        return collect($connection->select("SELECT table_name FROM information_schema.tables  WHERE table_schema = '{$connection->getDatabaseName()}'"))->pluck('table_name', 'table_name');
    }

    private function getAllFK($table, Connection $connection)
    {
        $query = "SELECT
                        REFERENCED_TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
                        FROM
                        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                        WHERE
                        TABLE_SCHEMA = '{$connection->getDatabaseName()}'
                        AND INFORMATION_SCHEMA.KEY_COLUMN_USAGE.TABLE_NAME = '$table';";

        $res = collect($connection->select($query));
        return ($res->keyBy('CONSTRAINT_NAME'));
    }
}