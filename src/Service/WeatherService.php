<?php

namespace App\Service;

use App\Entity\GeoPoint;
use Flibidi67\OpenMeteo\Service\HistoricalService;

/**
 * @phpstan-type SequencedWeatherData array{
 *   sequence: string,
 *   apparent_temperature_min_avg: float,
 *   apparent_temperature_max_avg: float,
 *   temperature_min_avg: float,
 *   temperature_max_avg: float
 * }
 * @phpstan-type HistoricalWeatherData array{
 *   latitude: float,
 *   longitude: float,
 *   timezone: string,
 *   elevation: float,
 *   daily: array{
 *     time: array<string>,
 *     apparent_temperature_mean: array<?float>,
 *     apparent_temperature_max: array<?float>,
 *     apparent_temperature_min: array<?float>,
 *     temperature_2m_mean: array<?float>,
 *     temperature_2m_max: array<?float>,
 *     temperature_2m_min: array<?float>
 *   }
 * }
 */
class WeatherService
{
    public function __construct(
        private readonly HistoricalService $historicalService,
    ) {
    }

    /**
     * @return HistoricalWeatherData
     */
    public function historicalWeatherAtPoint(GeoPoint $point): array
    {
        /* @phpstan-ignore-next-line */
        return $this->historicalService
            ->setCoordinates((float) $point->getLon(), (float) $point->getLat())
            ->getDaily()
            ?->withApparentTemperatureMean()
            ?->withApparentTemperatureMax()
            ?->withApparentTemperatureMin()
            ?->withTemperature2mMean()
            ?->withTemperature2mMax()
            ?->withTemperature2mMin()
            ?->getSettings()
            ?->setStartDate(new \DateTime('-1 year -1 day'))
            ?->setEndDate(new \DateTime('now'))
            ?->get() ?? throw new \Exception('Invalid API call.')
        ;
    }

    /**
     * @param HistoricalWeatherData $data
     *
     * @return array<SequencedWeatherData>
     */
    public function averageWeatherByWeeks(array $data): array
    {
        $result = [];

        $weeklyData = [];
        foreach ($data['daily']['time'] as $index => $date) {
            $week = (int) (new \DateTimeImmutable($date))->format('W');

            if (!isset($weeklyData[$week])) {
                $weeklyData[$week] = [
                    'count' => 0,
                    'apparent_temp_min_sum' => 0,
                    'apparent_temp_max_sum' => 0,
                    'temperature_2m_min_sum' => 0,
                    'temperature_2m_max_sum' => 0,
                    'date' => $date,
                ];
            }

            ++$weeklyData[$week]['count'];
            $weeklyData[$week]['apparent_temp_min_sum'] += $data['daily']['apparent_temperature_min'][$index];
            $weeklyData[$week]['apparent_temp_max_sum'] += $data['daily']['apparent_temperature_max'][$index];
            $weeklyData[$week]['temperature_2m_min_sum'] += $data['daily']['temperature_2m_min'][$index];
            $weeklyData[$week]['temperature_2m_max_sum'] += $data['daily']['temperature_2m_max'][$index];
        }

        foreach ($weeklyData as $week => $values) {
            $result[] = [
                'sequence' => (new \DateTimeImmutable($values['date']))->format('M') . ' W' . $week,
                'apparent_temperature_min_avg' => round($values['apparent_temp_min_sum'] / $values['count'], 1),
                'apparent_temperature_max_avg' => round($values['apparent_temp_max_sum'] / $values['count'], 1),
                'temperature_min_avg' => round($values['temperature_2m_min_sum'] / $values['count'], 1),
                'temperature_max_avg' => round($values['temperature_2m_max_sum'] / $values['count'], 1),
            ];
        }

        return $result;
    }

    /**
     * @param HistoricalWeatherData $data
     *
     * @return array<SequencedWeatherData>
     */
    public function averageWeatherByMonths(array $data): array
    {
        $result = [];

        $monthlyData = [];
        foreach ($data['daily']['time'] as $index => $date) {
            $month = mb_substr($date, 0, 7); // Get YYYY-MM format

            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = [
                    'count' => 0,
                    'apparent_temp_min_sum' => 0,
                    'apparent_temp_max_sum' => 0,
                    'temperature_2m_min_sum' => 0,
                    'temperature_2m_max_sum' => 0,
                ];
            }

            ++$monthlyData[$month]['count'];
            $monthlyData[$month]['apparent_temp_min_sum'] += $data['daily']['apparent_temperature_min'][$index];
            $monthlyData[$month]['apparent_temp_max_sum'] += $data['daily']['apparent_temperature_max'][$index];
            $monthlyData[$month]['temperature_2m_min_sum'] += $data['daily']['temperature_2m_min'][$index];
            $monthlyData[$month]['temperature_2m_max_sum'] += $data['daily']['temperature_2m_max'][$index];
        }

        foreach ($monthlyData as $month => $values) {
            $result[] = [
                'sequence' => $month,
                'apparent_temperature_min_avg' => round($values['apparent_temp_min_sum'] / $values['count'], 1),
                'apparent_temperature_max_avg' => round($values['apparent_temp_max_sum'] / $values['count'], 1),
                'temperature_min_avg' => round($values['temperature_2m_min_sum'] / $values['count'], 1),
                'temperature_max_avg' => round($values['temperature_2m_max_sum'] / $values['count'], 1),
            ];
        }

        return $result;
    }

    /**
     * @param array<SequencedWeatherData> $sequencedWeatherData
     */
    public function formatSequencedWeatherToMarkdown(array $sequencedWeatherData): string
    {
        $markdown = '';
        foreach ($sequencedWeatherData as $period) {
            $markdown .= str_replace("\n", '', <<<MD
                | {$period['sequence']}
                |
                <span style="color:#0d6efd">{$period['temperature_min_avg']}</span>・
                <span style="color:#dc3545">{$period['temperature_max_avg']}</span>&nbsp;/&nbsp;
                <span style="color:#0d6efd">{$period['apparent_temperature_min_avg']}</span>・
                <span style='color:#dc3545'>{$period['apparent_temperature_max_avg']}</span>
                |
                MD) . "\n";
        }

        return $markdown;
    }
}
