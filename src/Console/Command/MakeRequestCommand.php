<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeRequestCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeRequestCommand implements CommandInterface
{
    /**
     * MakeRequestCommand Constructor
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
        return 'make:request';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new form request class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:request <ClassName>';
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
            fwrite(STDERR, "Usage: ez make:request <ClassName>\n");
            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Requests';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Request already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create request: $filename\n");
            return 1;
        }

        echo "Created: app/Requests/$filename\n";

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

            namespace App\\Requests;

            use EzPhp\\Http\\Request;
            use EzPhp\\Validation\\Validator;

            final class $name
            {
                /**
                 * @return array<string, string|list<string>>
                 */
                public function rules(): array
                {
                    return [];
                }

                /**
                 * @param Request \$request
                 *
                 * @return Validator
                 */
                public function validate(Request \$request): Validator
                {
                    return new Validator(\$request->all(), \$this->rules());
                }
            }
            PHP;
    }
}
