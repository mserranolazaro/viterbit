<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Ai;

/**
 * A versioned prompt file. The version is the file name (cv_analysis.v1.md → cv_analysis.v1),
 * so changing a prompt means adding a file, and old results keep pointing at their prompt.
 */
final class PromptTemplate
{
    private function __construct(
        public readonly string $version,
        private readonly string $template,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $template = @file_get_contents($path);
        if ($template === false) {
            throw new \RuntimeException(sprintf('Prompt file "%s" cannot be read.', $path));
        }

        return new self(basename($path, '.md'), $template);
    }

    /**
     * @param array<string, string> $variables
     */
    public function render(array $variables): string
    {
        $placeholders = array_map(static fn (string $name): string => '{{' . $name . '}}', array_keys($variables));

        return str_replace($placeholders, array_values($variables), $this->template);
    }
}
