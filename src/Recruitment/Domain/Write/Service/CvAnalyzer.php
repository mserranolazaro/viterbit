<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Service;

use App\Recruitment\Domain\Write\Aggregate\Root\Position\Position;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;

/**
 * Port to the AI that summarises a CV and scores it against a position.
 * The domain does not know whether a real LLM or a simulation sits behind it.
 */
interface CvAnalyzer
{
    /**
     * @throws CvAnalysisFailedException when no valid analysis could be produced
     */
    public function analyze(CvText $cv, Position $position): CvAnalysis;
}
