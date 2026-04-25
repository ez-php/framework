<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeChannelCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeChannelCommand implements CommandInterface
{
    /**
     * MakeChannelCommand Constructor
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
        return 'make:channel';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new notification channel class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:channel <ClassName>';
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
            fwrite(STDERR, "Usage: ez make:channel <ClassName>\n");
            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Channels';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Channel already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create channel: $filename\n");
            return 1;
        }

        echo "Created: app/Channels/$filename\n";

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

            namespace App\\Channels;

            use EzPhp\\Notification\\ChannelInterface;
            use EzPhp\\Notification\\NotificationInterface;

            final class $name implements ChannelInterface
            {
                /**
                 * @param object              \$notifiable  The recipient (e.g. a User model).
                 * @param NotificationInterface \$notification
                 *
                 * @return void
                 */
                public function send(object \$notifiable, NotificationInterface \$notification): void
                {
                    // TODO: implement channel delivery logic
                }
            }
            PHP;
    }
}
