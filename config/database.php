<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'adotai_site';
const DB_USER = 'root';
const DB_PASS = '';
const DB_FETCH_COLUMN = 1001;

final class MysqliDatabase
{
    private bool $transactionActive = false;

    public function __construct(private mysqli $connection)
    {
    }

    public function prepare(string $sql): MysqliStatement
    {
        return new MysqliStatement($this->connection, $sql);
    }

    public function query(string $sql): MysqliStatement
    {
        $statement = $this->prepare($sql);
        $statement->execute();
        return $statement;
    }

    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
        $this->transactionActive = true;
    }

    public function commit(): void
    {
        $this->connection->commit();
        $this->transactionActive = false;
    }

    public function rollBack(): void
    {
        $this->connection->rollback();
        $this->transactionActive = false;
    }

    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }

    public function lastInsertId(): string
    {
        return (string)$this->connection->insert_id;
    }
}

final class MysqliStatement
{
    private mysqli_stmt $statement;
    private ?mysqli_result $result = null;
    private array $parameterKeys;

    public function __construct(mysqli $connection, string $sql)
    {
        [$sql, $this->parameterKeys] = self::convertPlaceholders($sql);
        $this->statement = $connection->prepare($sql);
    }

    public function execute(array $parameters = []): bool
    {
        $this->result?->free();
        $this->result = null;

        $values = $this->orderedParameters($parameters);
        if ($values !== []) {
            $types = '';
            foreach ($values as $value) {
                $types .= is_int($value) || is_bool($value)
                    ? 'i'
                    : (is_float($value) ? 'd' : 's');
            }

            $bindings = [$types];
            foreach ($values as $index => &$value) {
                $bindings[] = &$value;
            }
            unset($value);
            $this->statement->bind_param(...$bindings);
        }

        $this->statement->execute();
        $result = $this->statement->get_result();
        $this->result = $result instanceof mysqli_result ? $result : null;
        return true;
    }

    public function fetch(int $mode = MYSQLI_ASSOC): array|false
    {
        if ($this->result === null) {
            return false;
        }
        return $mode === MYSQLI_NUM
            ? $this->result->fetch_row()
            : $this->result->fetch_assoc();
    }

    public function fetchAll(int $mode = MYSQLI_ASSOC): array
    {
        if ($this->result === null) {
            return [];
        }

        if ($mode === DB_FETCH_COLUMN) {
            $values = [];
            while (($row = $this->result->fetch_row()) !== null) {
                $values[] = $row[0];
            }
            return $values;
        }

        return $this->result->fetch_all($mode === MYSQLI_NUM ? MYSQLI_NUM : MYSQLI_ASSOC);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->result === null) {
            return false;
        }

        $row = $this->result->fetch_row();
        return $row === null ? false : ($row[$column] ?? false);
    }

    private function orderedParameters(array $parameters): array
    {
        if ($this->parameterKeys === []) {
            if ($parameters !== []) {
                throw new InvalidArgumentException('A consulta não possui parâmetros vinculáveis.');
            }
            return [];
        }

        $parameterIndexes = array_keys($parameters);
        $isPositional = $parameterIndexes === (
            $parameters === [] ? [] : range(0, count($parameters) - 1)
        );
        if ($isPositional) {
            if (count($parameters) !== count($this->parameterKeys)) {
                throw new InvalidArgumentException('A quantidade de parâmetros não corresponde à consulta.');
            }
            foreach ($this->parameterKeys as $key) {
                if (!is_int($key)) {
                    throw new InvalidArgumentException('Use parâmetros nomeados para esta consulta.');
                }
            }
            return array_values($parameters);
        }

        $normalized = [];
        foreach ($parameters as $key => $value) {
            $normalized[ltrim((string)$key, ':')] = $value;
        }

        $values = [];
        foreach ($this->parameterKeys as $key) {
            if (!is_string($key) || !array_key_exists($key, $normalized)) {
                throw new InvalidArgumentException('Parâmetro nomeado ausente na consulta: ' . (string)$key);
            }
            $values[] = $normalized[$key];
        }
        return $values;
    }

    private static function convertPlaceholders(string $sql): array
    {
        $converted = '';
        $parameterKeys = [];
        $quote = '';
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $sql[$index + 1] ?? '';

            if ($quote !== '') {
                $converted .= $character;
                if ($character === '\\' && $index + 1 < $length) {
                    $converted .= $sql[++$index];
                } elseif ($character === $quote) {
                    if ($next === $quote) {
                        $converted .= $sql[++$index];
                    } else {
                        $quote = '';
                    }
                }
                continue;
            }

            if ($character === '\'' || $character === '"' || $character === '`') {
                $quote = $character;
                $converted .= $character;
                continue;
            }

            $isDashComment = $character === '-'
                && $next === '-'
                && isset($sql[$index + 2])
                && ord($sql[$index + 2]) <= 32;
            if ($character === '#' || $isDashComment || ($character === '/' && $next === '*')) {
                $commentEnd = $character === '/' ? '*/' : "\n";
                $commentStart = $character === '/' ? '/*' : ($character === '#' ? '#' : '--');
                $commentLength = strlen($commentStart);
                $commentEndIndex = strpos($sql, $commentEnd, $index + $commentLength);
                if ($commentEndIndex === false) {
                    $converted .= substr($sql, $index);
                    break;
                }
                $end = $character === '/' ? $commentEndIndex + 2 : $commentEndIndex + 1;
                $converted .= substr($sql, $index, $end - $index);
                $index = $end - 1;
                continue;
            }

            if ($character === '?') {
                $parameterKeys[] = count($parameterKeys);
                $converted .= '?';
                continue;
            }

            if (
                $character === ':'
                && $next !== ':'
                && ($index === 0 || $sql[$index - 1] !== ':')
                && preg_match('/[a-zA-Z_]/', $next)
            ) {
                $end = $index + 2;
                while ($end < $length && preg_match('/[a-zA-Z0-9_]/', $sql[$end])) {
                    $end++;
                }
                $parameterKeys[] = substr($sql, $index + 1, $end - $index - 1);
                $converted .= '?';
                $index = $end - 1;
                continue;
            }

            $converted .= $character;
        }

        return [$converted, $parameterKeys];
    }
}

function getConnection(): MysqliDatabase
{
    static $database = null;
    if ($database instanceof MysqliDatabase) {
        return $database;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host = getenv('DB_HOST') ?: DB_HOST;
    $port = (int)(getenv('DB_PORT') ?: DB_PORT);
    $name = getenv('DB_NAME') ?: DB_NAME;
    $user = getenv('DB_USER') ?: DB_USER;
    $password = getenv('DB_PASS');

    $connection = new mysqli(
        $host,
        $user,
        $password === false ? DB_PASS : $password,
        $name,
        $port
    );
    $connection->set_charset('utf8mb4');

    $database = new MysqliDatabase($connection);
    return $database;
}
