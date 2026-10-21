<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Ai;

/**
 * A deterministic stand-in for an LLM (the exercise forbids real API calls).
 *
 * It only sees the rendered prompt, exactly as a real model would, and answers with
 * the JSON the prompt asks for:
 * - score: weighted share of requirements found in the CV (must = 3, nice = 1), 0–100;
 * - summary: the CV headline, which requirements it meets and which it misses.
 *
 * Same prompt, same answer, so tests and demos are reproducible. A CV containing
 * FAILURE_MARKER gets a schema-invalid answer, to show retries and the failed status.
 */
final class SimulatedLlmClient implements LlmClient
{
    public const string FAILURE_MARKER = '[simulate-llm-failure]';

    private const int MUST_WEIGHT = 3;
    private const int NICE_WEIGHT = 1;
    private const int HEADLINE_MAX_LENGTH = 140;

    public function complete(string $prompt): string
    {
        // The requirements come before the CV and are trusted; the CV is read up to the last
        // closing tag, so text inside it cannot end its section early or forge requirements.
        [$requirementsSection, $requirementsEnd] = $this->section(
            $prompt,
            '<requirements>',
            '</requirements>',
            0,
            false,
        );
        [$cv] = $this->section($prompt, '<cv>', '</cv>', $requirementsEnd, true);

        if (str_contains($cv, self::FAILURE_MARKER)) {
            return '{"summary": "", "score": 140}';
        }

        $requirements = $this->requirements($requirementsSection);
        $matched = $missing = ['must' => [], 'nice' => []];
        $matchedWeight = $totalWeight = 0;

        foreach ($requirements as [$level, $skill]) {
            $weight = $level === 'must' ? self::MUST_WEIGHT : self::NICE_WEIGHT;
            $totalWeight += $weight;

            if ($this->mentions($cv, $skill)) {
                $matched[$level][] = $skill;
                $matchedWeight += $weight;
            } else {
                $missing[$level][] = $skill;
            }
        }

        return json_encode([
            'summary' => $this->summary($cv, $matched, $missing),
            'score' => $totalWeight === 0 ? 0 : (int) round(100 * $matchedWeight / $totalWeight),
            'matched_skills' => [...$matched['must'], ...$matched['nice']],
            'missing_skills' => [...$missing['must'], ...$missing['nice']],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{string, int} The section's text and the offset where it ends
     */
    private function section(string $prompt, string $open, string $close, int $from, bool $lastClose): array
    {
        $start = strpos($prompt, $open, $from);
        if ($start === false) {
            return ['', $from];
        }

        $contentStart = $start + strlen($open);
        $end = $lastClose ? strrpos($prompt, $close) : strpos($prompt, $close, $contentStart);
        if ($end === false || $end < $contentStart) {
            return ['', $from];
        }

        return [trim(substr($prompt, $contentStart, $end - $contentStart)), $end + strlen($close)];
    }

    /**
     * @return array<array{string, string}> [level, skill] pairs from "- [must] PHP" lines
     */
    private function requirements(string $section): array
    {
        preg_match_all('/^- \[(must|nice)\] (.+)$/m', $section, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [$match[1], trim($match[2])], $matches);
    }

    /**
     * Whole-word, case-insensitive: "Go" does not match "Google"; "C++" and "Node.js" still work.
     */
    private function mentions(string $cv, string $skill): bool
    {
        return preg_match('/(?<![\p{L}\p{N}])' . preg_quote($skill, '/') . '(?![\p{L}\p{N}])/iu', $cv) === 1;
    }

    /**
     * @param array{must: string[], nice: string[]} $matched
     * @param array{must: string[], nice: string[]} $missing
     */
    private function summary(string $cv, array $matched, array $missing): string
    {
        $sentences = [$this->headline($cv)];

        $musts = count($matched['must']) + count($missing['must']);
        $sentences[] = sprintf(
            'Meets %d of %d must-have requirements%s',
            count($matched['must']),
            $musts,
            $matched['must'] === [] ? '' : ' (' . implode(', ', $matched['must']) . ')',
        ) . ($matched['nice'] === [] ? '' : ', plus ' . implode(', ', $matched['nice'])) . '.';

        $gaps = [...$missing['must'], ...$missing['nice']];
        if ($gaps !== []) {
            $sentences[] = 'Missing: ' . implode(', ', $gaps) . '.';
        }

        $years = $this->yearsOfExperience($cv);
        if ($years !== null) {
            $sentences[] = sprintf('Mentions %d years of experience.', $years);
        }

        return implode(' ', $sentences);
    }

    private function headline(string $cv): string
    {
        foreach (preg_split('/\R/', $cv) ?: [] as $line) {
            $line = trim($line, " \t-*#");
            if ($line !== '') {
                $line = mb_strlen($line) > self::HEADLINE_MAX_LENGTH
                    ? rtrim(mb_substr($line, 0, self::HEADLINE_MAX_LENGTH - 1)) . '…'
                    : $line;

                return rtrim($line, '.') . '.';
            }
        }

        return 'No headline found in the CV.';
    }

    private function yearsOfExperience(string $cv): ?int
    {
        if (preg_match_all('/\b(\d{1,2})\+?\s*(?:years?|yrs?)\b/i', $cv, $matches) === 0) {
            return null;
        }

        return max(array_map('intval', $matches[1]));
    }
}
