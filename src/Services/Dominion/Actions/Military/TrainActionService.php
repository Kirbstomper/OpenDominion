<?php

namespace OpenDominion\Services\Dominion\Actions\Military;

use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Query\Builder;
use OpenDominion\Calculators\Dominion\Actions\TrainingCalculator;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Traits\DominionGuardsTrait;
use RuntimeException;

class TrainActionService
{
    use DominionGuardsTrait;

    /** @var TrainingCalculator */
    protected $trainingCalculator;

    /** @var UnitHelper */
    protected $unitHelper;

    /**
     * TrainActionService constructor.
     *
     * @param TrainingCalculator $trainingCalculator
     * @param UnitHelper $unitHelper
     */
    public function __construct(TrainingCalculator $trainingCalculator, UnitHelper $unitHelper)
    {
        $this->trainingCalculator = $trainingCalculator;
        $this->unitHelper = $unitHelper;
    }

    /**
     * Does a military train action for a Dominion.
     *
     * @param Dominion $dominion
     * @param array $data
     * @return array
     * @throws RuntimeException
     * @throws Exception
     */
    public function train(Dominion $dominion, array $data): array
    {
        $this->guardLockedDominion($dominion);

        $data = array_map('intval', $data);

        $totalUnitsToTrain = array_sum($data);

        if ($totalUnitsToTrain === 0) {
            throw new RuntimeException('Training aborted due to bad input.');
        }

        $totalCosts = [
            'platinum' => 0,
            'ore' => 0,
            'draftees' => 0,
            'wizards' => 0,
        ];

        $unitsToTrain = [];

        $trainingCostsPerUnit = $this->trainingCalculator->getTrainingCostsPerUnit($dominion);

        foreach ($data as $unitType => $amountToTrain) {
            if (!$amountToTrain) {
                continue;
            }

            $costs = $trainingCostsPerUnit[$unitType];

            foreach ($costs as $costType => $costAmount) {
                $totalCosts[$costType] += ($amountToTrain * $costAmount);
            }

            $unitsToTrain[$unitType] = $amountToTrain;
        }

        if (($totalCosts['platinum'] > $dominion->resource_platinum) || ($totalCosts['ore'] > $dominion->resource_ore)) {
            throw new RuntimeException('Training aborted due to lack of economical resources');
        }

        if ($totalCosts['draftees'] > $dominion->military_draftees) {
            throw new RuntimeException('Training aborted due to lack of draftees');
        }

        if ($totalCosts['wizards'] > $dominion->military_wizards) {
            throw new RuntimeException('Training aborted due to lack of wizards');
        }

        $dateTime = new Carbon;

        DB::beginTransaction();

        try {
            $dominion->fill([
                'resource_platinum' => ($dominion->resource_platinum - $totalCosts['platinum']),
                'resource_ore' => ($dominion->resource_ore - $totalCosts['ore']),
                'military_draftees' => ($dominion->military_draftees - $totalCosts['draftees']),
                'military_wizards' => ($dominion->military_wizards - $totalCosts['wizards']),
            ])->save();

            // Check for existing queue
            $existingQueueRows = DB::table('queue_training')
                ->where('dominion_id', $dominion->id)
                ->where(function (Builder $query) {
                    $query->orWhere(function (Builder $query) {
                        // Specialist units take 9 hours to train
                        $query->whereIn('unit_type', ['unit1', 'unit2'])
                            ->where('hours', 9);
                    })->orWhere(function (Builder $query) {
                        // Non-specialist units take 12 hours to train
                        $query->whereNotIn('unit_type', ['unit1', 'unit2'])
                            ->where('hours', 12);
                    });
                })->get(['unit_type', 'amount']);

            foreach ($existingQueueRows as $row) {
                $data[$row->unit_type] += $row->amount;
            }

            foreach ($data as $unitType => $amount) {
                if ($amount === 0) {
                    continue;
                }

                $where = [
                    'dominion_id' => $dominion->id,
                    'unit_type' => $unitType,
                    'hours' => (in_array($unitType, ['unit1', 'unit2'], true) ? 9 : 12),
                ];

                $values = [
                    'amount' => $amount,
                    'updated_at' => $dateTime,
                ];

                $existingQueueRow = $existingQueueRows->filter(function ($row) use ($unitType) {
                    return ($row->unit_type === $unitType);
                });

                if ($existingQueueRow->isEmpty()) {
                    $values['created_at'] = $dateTime;
                }

                DB::table('queue_training')
                    ->updateOrInsert($where, $values);
            }

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return [
            'message' => $this->getReturnMessageString($dominion, $unitsToTrain, $totalCosts),
            'data' => [
                'totalCosts' => $totalCosts,
            ],
        ];
    }

    /**
     * Returns the message for a train action.
     *
     * @param Dominion $dominion
     * @param array $unitsToTrain
     * @param array $totalCosts
     * @return string
     */
    protected function getReturnMessageString(Dominion $dominion, array $unitsToTrain, array $totalCosts): string
    {
        $unitsToTrainStringParts = [];

        foreach ($unitsToTrain as $unitType => $amount) {
            if ($amount > 0) {
                $unitName = strtolower($this->unitHelper->getUnitName($unitType, $dominion->race));
                $unitsToTrainStringParts[] = (number_format($amount) . ' ' . str_plural(str_singular($unitName), $amount));
            }
        }

        $unitsToTrainString = generate_sentence_from_array($unitsToTrainStringParts);

        $trainingCostsStringParts = [];
        foreach ($totalCosts as $costType => $cost) {
            if ($cost === 0) {
                continue;
            }

            $costType = str_singular($costType);
            if (!\in_array($costType, ['platinum', 'ore'], true)) {
                $costType = str_plural($costType, $cost);
            }
            $trainingCostsStringParts[] = (number_format($cost) . ' ' . $costType);

        }

        $trainingCostsString = generate_sentence_from_array($trainingCostsStringParts);

        $message = sprintf(
            'Training of %s begun at a cost of %s.',
            $unitsToTrainString,
            $trainingCostsString
        );

        return $message;
    }
}
