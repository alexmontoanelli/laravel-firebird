<?php

namespace Firebird\Schema\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Illuminate\Database\Schema\Blueprint;
use Firebird\Schema\SequenceBlueprint;


class FirebirdGrammar extends Grammar
{

    protected $modifiers = ['Charset', 'Collate', 'Increment', 'Nullable', 'Default'];


    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];


    public function compileTableExists()
    {
        return 'SELECT * FROM RDB$RELATIONS WHERE RDB$RELATION_NAME = ?';
    }

    public function compileColumnExists($table)
    {
        return 'SELECT TRIM(RDB$FIELD_NAME) AS "column_name" ' . "FROM RDB\$RELATION_FIELDS WHERE RDB\$RELATION_NAME = '$table'";
    }

    public function compileSequenceExists()
    {
        return 'SELECT * FROM RDB$GENERATORS WHERE RDB$GENERATOR_NAME = ?';
    }

    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = $blueprint->temporary ? 'CREATE TEMPORARY' : 'CREATE';

        $sql .= ' TABLE ' . $this->wrapTable($blueprint) . " ($columns)";

        if ($blueprint->temporary) {
            if ($blueprint->preserve) {
                $sql .= ' ON COMMIT DELETE ROWS';
            } else {
                $sql .= ' ON COMMIT PRESERVE ROWS';
            }
        }

        return $sql;
    }

    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'DROP TABLE ' . $this->wrapTable($blueprint);
    }

    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        $columns = $this->prefixArray('ADD', $this->getColumns($blueprint));

        return 'ALTER TABLE' . $table . ' ' . implode(', ', $columns);
    }

    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->columnize($command->columns);

        return 'ALTER TABLE ' . $this->wrapTable($blueprint) . " ADD PRIMARY KEY ({$columns})";
    }

    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        $index = $this->wrap(substr($command->index, 0, 31));

        $columns = $this->columnize($command->columns);

        return "ALTER TABLE {$table} ADD CONSTRAINT {$index} UNIQUE ({$columns})";
    }

    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->columnize($command->columns);

        $index = $this->wrap(substr($command->index, 0, 31));

        $table = $this->wrapTable($blueprint);

        return "CREATE INDEX {$index} ON {$table} ($columns)";
    }

    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        //return parent::compileForeign($blueprint, $command); // TODO: Change the autogenerated stub

        $table = $this->wrapTable($blueprint);

        $on = $this->wrapTable($command->on);


        $columns = $this->columnize($command->columns);

        $onColumns = $this->columnize((array) $command->refernce);

        $fkName = substr($command->index, 0, 31);

        $sql = "ALTER TABLE {$table} ADD CONSTRAINT {$fkName} ";

        $sql .= "FOREIGN KEY ({$columns}) REFERENCES {$on} ({$onColumns})";


        if (!is_null($command->onDelete)) {
            $sql .= " ON DELETE {$command->onDelete}";
        }

        if (!is_null($command->onUpdate)) {
            $sql .= " ON UPDATE {$command->onUpdate}";
        }

        return $sql;
    }


    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        return "ALTER TABLE {$table} DROP CONSTRAINT {$command->index}";
    }


    protected function modifyCharset(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->charset)) {
            return ' CHARACTER SET ' . $column->charset;
        }
    }

    protected function modifyCollate(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->collation)) {
            return ' COLLATE ' . $column->collation;
        }
    }

    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        return $column->nullable ? '' : ' NOT NULL ';
    }

    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->default)) {
            return ' DEFAULT ' . $this->getDefaultValue($column->default);
        }
    }

    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            // identity columns support beginning Firebird 3.0 and above
            return $blueprint->use_identity ? ' GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY' : ' PRIMARY KEY';
        }
    }

    protected function typeChar(Fluent $column)
    {
        return "CHAR({$column->length})";
    }

    protected function typeString(Fluent $column)
    {
        return "VARCHAR({$column->length})";
    }

    protected function typeText(Fluent $column)
    {
        return 'BLOB SUB_TYPE TEXT';
    }

    protected function typeMediumText(Fluent $column)
    {
        return 'BLOB SUB_TYPE TEXT';
    }

    protected function typeLongText(Fluent $column)
    {
        return 'BLOB SUB_TYPE TEXT';
    }

    protected function typeInteger(Fluent $column)
    {
        return 'INTEGER';
    }

    protected function typeBigInteger(Fluent $column)
    {
        return 'BIGINT';
    }

    protected function typeMediumInteger(Fluent $column)
    {
        return 'INTEGER';
    }

    protected function typeTinyInteger(Fluent $column)
    {
        return 'SMALLINT';
    }

    protected function typeSmallInteger(Fluent $column)
    {
        return 'SMALLINT';
    }

    protected function typeFloat(Fluent $column)
    {
        return 'FLOAT';
    }

    protected function typeDouble(Fluent $column)
    {
        return 'DOUBLE PRECISION';
    }

    protected function typeDecimal(Fluent $column)
    {
        return "DECIMAL({$column->total}, {$column->places})";
    }

    protected function typeNumeric(Fluent $column)
    {
        return "NUMERIC(15, 2)";
    }

    protected function typeBoolean(Fluent $column)
    {
        return 'CHAR(1)';
    }

    protected function typeCharBool(Fluent $column)
    {
        return 'SMALLINT' . ' NOT NULL ' . $this->getDefaultValue(0);
    }

    protected function typeEnum(Fluent $column)
    {
        $allowed = array_map(function ($a) {
            return "'" . $a . "'";
        }, $column->allowed);

        return "VARCHAR(255) CHECK (\"{$column->name}\" IN (" . implode(', ', $allowed) . '))';
    }

    protected function typeJson(Fluent $column)
    {
        return 'VARCHAR(8191)';
    }

    protected function typeJsonb(Fluent $column)
    {
        return 'VARCHAR(8191) CHARACTER SET OCTETS';
    }

    protected function typeDate(Fluent $column)
    {
        return 'DATE';
    }

    protected function typeDateTime(Fluent $column)
    {
        return 'TIMESTAMP';
    }

    protected function typeTime(Fluent $column)
    {
        return 'TIME';
    }

    protected function typeTimeTz(Fluent $column)
    {
        return 'TIME';
    }

    protected function typeTimestamp(Fluent $column)
    {
        if ($column->useCurrent) {
            return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        }

        return 'TIMESTAMP';
    }

    protected function typeTimestampTz(Fluent $column)
    {
        if ($column->useCurrent) {
            return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        }

        return 'TIMESTAMP';
    }

    protected function typeBinary(Fluent $column)
    {
        return 'BLOB SUB_TYPE BINARY';
    }

    protected function typeUuid(Fluent $column)
    {
        return 'CHAR(36)';
    }

    protected function typeIpAddress(Fluent $column)
    {
        return 'VARCHAR(45)';
    }

    protected function typeMacAddress(Fluent $column)
    {
        return 'VARCHAR(17)';
    }


    public function compileCreateSequence(SequenceBlueprint $blueprint, Fluent $command)
    {
        $sql = 'CREATE SEQUENCE ';

        $sql .= $this->wrapSequence($blueprint);

        if ($blueprint->getInitialValue() !== 0) {
            $sql .= ' START WITH ' . $blueprint->getInitialValue();
        }
        if ($blueprint->getIncrement() !== 1) {
            $sql .= ' INCREMENT BY ' . $blueprint->getIncrement();
        }

        return $sql;
    }

    public function compileSequenceForTable(Blueprint $blueprint, Fluent $command)
    {
        $sequence = $this->wrap(substr('GEN_' . $blueprint->getTable() . '_ID', 0, 31));

        return "CREATE SEQUENCE {$sequence}";
    }

    public function compileDropSequenceForTable(Blueprint $blueprint, Fluent $command)
    {
        $triggerName = substr('TR_' . $blueprint->getTable() . '_BI', 0, 31);

        $sequenceTr = $this->wrap($triggerName);

        $sequenceName = substr('GEN_' . $blueprint->getTable() . '_ID', 0, 31);

        $sequence = $this->wrap($sequenceName);

        $sql = 'EXECUTE BLOCK' . "\n";
        $sql .= 'AS' . "\n";
        $sql .= 'BEGIN' . "\n";
        $sql .= "  IF(EXISTS(SELECT * FROM RDB\$TRIGGERS WHERE RDB\$TRIGGER_NAME = '{$triggerName}')) THEN" ."\n";
        $sql .= "     EXECUTE STATEMENT 'DROP SEQUENCE {$sequenceTr}';" . "\n";
        $sql .= "  IF(EXISTS(SELECT * FROM RDB\$GENERATORS WHERE RDB\$GENERATOR_NAME = '{$sequenceName}')) THEN" . "\n";
        $sql .= "     EXECUTE STATEMENT 'DROP SEQUENCE {$sequence}';" . "\n";
        $sql .= 'END';

        return $sql;
    }

    public function compileTriggerForAutoincrement(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        $trigger = $this->wrap(substr('TR_' . $blueprint->getTable() . '_BI', 0, 31));

        $column = $this->wrap($command->columnname);

        $sequence = $this->wrap(substr('GEN_' . $blueprint->getTable() . '_ID', 0, 31));



        $sql = "CREATE OR ALTER TRIGGER {$trigger} FOR {$table}\n";
        $sql .= "ACTIVE BEFORE INSERT\n";
        $sql .= "AS\n";
        $sql .= "BEGIN\n";
        $sql .= "  IF (NEW.{$column} IS NULL) THEN\n";
        $sql .= "    NEW.{$column} = GEN_ID({$sequence}, 1);\n";
        $sql .= 'END';

        return $sql;
    }

    public function compileAlterSequence(SequenceBlueprint $blueprint, Fluent $command)
    {
        $sql = 'ALTER SEQUENCE ';
        $sql .= $this->wrapSequence($blueprint);
        if($blueprint->isRestart()) {
            $sql .= ' RESTART';
            if($blueprint->getInitialValue() !== null) {
                $sql .= ' WITH ' . $blueprint->getInitialValue();
            }
        }
        if($blueprint->getIncrement() !== 1) {
            $sql .= ' INCREMENT BY ' . $blueprint->getIncrement();
        }

        return $sql;
    }

    public function compileDropSequence(SequenceBlueprint $blueprint, Fluent $command)
    {
        return "DROP SEQUENCE " . $this->wrapSequence($blueprint);
    }

    public function compileDropSequenceIfExists(SequenceBlueprint $blueprint, Fluent $command)
    {
        $sql = "EXECUTE BLOCK \n";
        $sql .= "AS\n";
        $sql .= "BEGIN\n";
        $sql .= "  IF(EXISTS(SELECT * FROM RDB\$GENERATORS WHERE RDB\$GENERATOR_NAME = '" . $blueprint->getSequence() . "')) THEN" . "\n";
        $sql .= "     EXECUTE STATEMENT 'DROP SEQUENCE '" . $this->wrapSequence($blueprint) . "';" . "\n";
        $sql .= 'END';

        return $sql;
    }

    public function wrapSequence($sequence)
    {
        if($sequence instanceof SequenceBlueprint) {
            $sequence = $sequence->getSequence();
        }

        if($this->isExpression($sequence)) {
            return $this->getValue($sequence);
        }

        return $this->wrap($this->tablePrefix . $sequence, true);
    }


}