<?php

final class PdoMySqlSessionHandler implements SessionHandlerInterface
{
    private ?string $lockName = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        if ($this->lockName === null) {
            return true;
        }

        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
        $statement->execute([$this->lockName]);
        $this->lockName = null;

        return true;
    }

    public function read(string $id): string|false
    {
        $this->lockName = 'warung_session_' . substr(hash('sha256', $id), 0, 48);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute([$this->lockName]);

        if ((int) $lock->fetchColumn() !== 1) {
            $this->lockName = null;
            return false;
        }

        $maxLifetime = (int) ini_get('session.gc_maxlifetime');
        $statement = $this->pdo->prepare(
            'SELECT session_data FROM app_sessions WHERE session_id = ? AND last_activity >= ?'
        );
        $statement->execute([$id, time() - $maxLifetime]);
        $data = $statement->fetchColumn();

        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO app_sessions (session_id, session_data, last_activity) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), last_activity = VALUES(last_activity)'
        );

        return $statement->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM app_sessions WHERE session_id = ?');

        return $statement->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = $this->pdo->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
        $statement->execute([time() - $max_lifetime]);

        return $statement->rowCount();
    }
}
