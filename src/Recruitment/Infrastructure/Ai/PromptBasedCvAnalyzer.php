<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Ai;

use App\Recruitment\Domain\Write\Aggregate\Root\Position\Position;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Score;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\Requirement;
use App\Recruitment\Domain\Write\Service\CvAnalysisFailedException;
use App\Recruitment\Domain\Write\Service\CvAnalyzer;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Treats the model as untrusted: renders a versioned prompt, sends it, and accepts
 * the answer only if it is JSON that matches the schema shipped with that prompt.
 */
final class PromptBasedCvAnalyzer implements CvAnalyzer
{
    private readonly PromptTemplate $prompt;
    private readonly string $schema;

    public function __construct(
        private readonly LlmClient $llmClient,
        #[Autowire('%kernel.project_dir%/resources/prompts/cv_analysis.v1.md')]
        string $promptPath,
        #[Autowire('%kernel.project_dir%/resources/prompts/cv_analysis.v1.schema.json')]
        string $schemaPath,
    ) {
        $this->prompt = PromptTemplate::fromFile($promptPath);
        $this->schema = (string) file_get_contents($schemaPath);
    }

    public function analyze(CvText $cv, Position $position): CvAnalysis
    {
        try {
            $answer = $this->llmClient->complete($this->prompt->render([
                'position_title' => $position->title(),
                'position_description' => $position->description(),
                'requirements' => implode("\n", array_map(
                    static fn (Requirement $requirement): string => sprintf(
                        '- [%s] %s',
                        $requirement->level->value,
                        $requirement->skill,
                    ),
                    $position->requirements(),
                )),
                'cv' => $cv->value(),
            ]));
        } catch (LlmRequestFailedException $e) {
            throw CvAnalysisFailedException::because('The LLM request failed: ' . $e->getMessage(), $e);
        }

        $output = $this->decode($answer);

        return new CvAnalysis($output['summary'], Score::fromInt($output['score']), $this->prompt->version);
    }

    /**
     * @return array{summary: string, score: int}
     */
    private function decode(string $answer): array
    {
        // Models often wrap JSON in a Markdown code fence even when told not to.
        $json = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($answer)));

        try {
            $data = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw CvAnalysisFailedException::because('The LLM answer is not valid JSON.', $e);
        }

        $error = new Validator()->validate($data, $this->schema)->error();
        if ($error !== null) {
            $errors = new ErrorFormatter()->formatFlat($error);

            throw CvAnalysisFailedException::because(
                'The LLM answer does not match the schema: ' . implode('; ', $errors),
            );
        }

        /** @var array{summary: string, score: int} */
        return json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }
}
