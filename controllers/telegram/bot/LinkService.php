<?php
class LinkService
{
    public int $telegramId;
    public string $websiteUrl;
    public string $username;
    public array $serverError = [
        'success' => false,
        'error' => [
            'code' => 500,
            'message' => 'Server error'
        ]
    ];
    private PDO $pdo;

    public function __construct(int $telegramId, string $websiteUrl, string $username, PDO $pdo)
    {
        $this->telegramId = $telegramId;
        $this->websiteUrl = $websiteUrl;
        $this->username = $username;
        $this->pdo = $pdo;
    }

    public function check(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT auth_hash FROM users WHERE telegram_id = ? LIMIT 1");
            $stmt->execute([$this->telegramId]);
            $result = $stmt->fetch();

            return [
                'success' => true,
                'fields' => [
                    'is_exist' => (empty($result)) ? false : true,
                    'auth_hash' => (empty($result['auth_hash'])) ? NULL : $result['auth_hash']
                ]
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Server error'
                ]
            ];
        }
    }

    public function getLink(array $checkResult): array
    {
        try {
            $newHash = bin2hex(random_bytes(32));

            if ($checkResult['fields']['is_exist']) {
                $stmt = $this->pdo->prepare("UPDATE users SET auth_hash = ? WHERE telegram_id = ? LIMIT 1");
                $stmt->execute([$newHash, $this->telegramId]);

                $result = $stmt->rowCount() > 0;
                return $this->createLink($newHash, $result);
            }

            $stmt = $this->pdo->prepare("INSERT INTO users(telegram_id, username, auth_hash) VALUES(?, ?, ?)");
            $stmt->execute([$this->telegramId, $this->username, $newHash]);

            $result = $stmt->rowCount() > 0;
            if ($result) {
                return $this->createLink($newHash, $result);
            }

            return $this->serverError;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->serverError;
        }
    }

    public function createLink(string $newHash, bool $result): array
    {
        return [
            'success' => $result ? true : false,
            'fields' => [
                'auth_hash' => $result ? $newHash : NULL,
                'link' => $result ? $this->websiteUrl . "telegram?telegram_id=" . $this->telegramId . "&hash=" . $newHash : NULL
            ]
        ];
    }
}
