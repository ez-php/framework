<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeNotificationCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeNotificationCommand implements CommandInterface
{
    /**
     * MakeNotificationCommand Constructor
     *
     * @param string $appPath
     */
    public function __construct(private string $appPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:notification';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new notification class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:notification <ClassName>';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $name = $args[0] ?? null;

        if ($name === null || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
            fwrite(STDERR, "Usage: ez make:notification <ClassName>\n");
            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Notifications';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Notification already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create notification: $filename\n");
            return 1;
        }

        echo "Created: app/Notifications/$filename\n";

        return 0;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function stub(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Notifications;

            use EzPhp\\Notification\\NotificationInterface;

            final class $name implements NotificationInterface
            {
                /**
                 * @return list<string>
                 */
                public function via(): array
                {
                    return ['mail'];
                }

                /**
                 * @return array<string, mixed>
                 */
                public function toArray(): array
                {
                    return [];
                }
            }
            PHP;
    }
}
