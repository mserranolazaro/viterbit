<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Infrastructure\Ai;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Domain\Write\Service\CvAnalysisFailedException;
use App\Recruitment\Infrastructure\Ai\LlmClient;
use App\Recruitment\Infrastructure\Ai\LlmRequestFailedException;
use App\Recruitment\Infrastructure\Ai\PromptBasedCvAnalyzer;
use App\Recruitment\Infrastructure\Ai\SimulatedLlmClient;
use App\Tests\Support\JobApplicationMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PromptBasedCvAnalyzerTest extends TestCase
{
    private const string PROMPT = __DIR__ . '/../../../../../resources/prompts/cv_analysis.v1.md';
    private const string SCHEMA = __DIR__ . '/../../../../../resources/prompts/cv_analysis.v1.schema.json';

    public function testItRendersThePromptWithThePositionAndTheCv(): void
    {
        $prompt = null;
        $llm = $this->createMock(LlmClient::class);
        $llm->expects(self::once())->method('complete')
            ->willReturnCallback(static function (string $sent) use (&$prompt): string {
                $prompt = $sent;

                return '{"summary": "Fits.", "score": 50, "matched_skills": [], "missing_skills": []}';
            });

        new PromptBasedCvAnalyzer($llm, self::PROMPT, self::SCHEMA)
            ->analyze(CvText::fromString('My CV text'), JobApplicationMother::position());

        self::assertIsString($prompt);
        self::assertStringContainsString('<title>Backend Engineer</title>', $prompt);
        self::assertStringContainsString("- [must] PHP\n- [nice] MongoDB", $prompt);
        self::assertStringContainsString("<cv>\nMy CV text\n</cv>", $prompt);
        self::assertStringNotContainsString('{{', $prompt);
    }

    public function testItReturnsTheAnalysisWithThePromptVersion(): void
    {
        $analysis = $this->analyzeWithAnswer(
            "```json\n" . self::answer(['summary' => 'Strong PHP profile.', 'score' => 67]) . "\n```",
        );

        self::assertSame('Strong PHP profile.', $analysis->summary);
        self::assertSame(67, $analysis->score->value());
        self::assertSame('cv_analysis.v1', $analysis->promptVersion);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidAnswers(): iterable
    {
        $invalid = 'does not match the schema';

        yield 'not JSON' => ['Sure! Here is the analysis.', 'not valid JSON'];
        yield 'score out of range' => [self::answer(['score' => 140]), $invalid];
        yield 'score as text' => [self::answer(['score' => 'high']), $invalid];
        yield 'empty summary' => [self::answer(['summary' => '']), $invalid];
        yield 'missing summary' => [self::answer(['summary' => null]), $invalid];
        yield 'unexpected field' => [self::answer(['hire' => true]), $invalid];
    }

    #[DataProvider('invalidAnswers')]
    public function testItRejectsAnswersThatAreNotValidAgainstTheSchema(string $answer, string $expectedReason): void
    {
        $this->expectException(CvAnalysisFailedException::class);
        $this->expectExceptionMessage($expectedReason);

        $this->analyzeWithAnswer($answer);
    }

    public function testItReportsAFailedRequestAsAFailedAnalysis(): void
    {
        $llm = $this->createStub(LlmClient::class);
        $llm->method('complete')->willThrowException(new LlmRequestFailedException('HTTP 529 overloaded'));

        $this->expectException(CvAnalysisFailedException::class);
        $this->expectExceptionMessage('The LLM request failed: HTTP 529 overloaded');

        new PromptBasedCvAnalyzer($llm, self::PROMPT, self::SCHEMA)
            ->analyze(CvText::fromString('CV'), JobApplicationMother::position());
    }

    public function testTheSimulatedClientAlwaysProducesSchemaValidAnswers(): void
    {
        $analysis = new PromptBasedCvAnalyzer(new SimulatedLlmClient(), self::PROMPT, self::SCHEMA)
            ->analyze(CvText::fromString("PHP engineer\nMongoDB in production."), JobApplicationMother::position());

        self::assertSame(100, $analysis->score->value());
        self::assertSame('PHP engineer. Meets 1 of 1 must-have requirements (PHP), plus MongoDB.', $analysis->summary);
    }

    /**
     * A valid answer with some fields overridden; null removes the field.
     *
     * @param array<string, mixed> $overrides
     */
    private static function answer(array $overrides): string
    {
        $answer = array_filter(
            [...['summary' => 'Ok.', 'score' => 50, 'matched_skills' => [], 'missing_skills' => []], ...$overrides],
            static fn (mixed $value): bool => $value !== null,
        );

        return json_encode($answer, JSON_THROW_ON_ERROR);
    }

    private function analyzeWithAnswer(string $answer): CvAnalysis
    {
        $llm = $this->createStub(LlmClient::class);
        $llm->method('complete')->willReturn($answer);

        return new PromptBasedCvAnalyzer($llm, self::PROMPT, self::SCHEMA)
            ->analyze(CvText::fromString('CV'), JobApplicationMother::position());
    }
}
