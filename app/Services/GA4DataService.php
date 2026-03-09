<?php

namespace App\Services;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Support\Facades\Log;

class GA4DataService
{
    private ?BetaAnalyticsDataClient $client = null;
    private string $propertyId;
    
    public function __construct(?string $propertyId = null)
    {
        $this->propertyId = $propertyId ?? env('GA4_PROPERTY_ID_RADIO', '');
    }
    
    /**
     * Initialiser le client GA4
     */
    private function getClient(): BetaAnalyticsDataClient
    {
        if ($this->client === null) {
            $credentialsPath = env('GA4_CREDENTIALS_PATH');
            
            if (!$credentialsPath || !file_exists($credentialsPath)) {
                Log::error('GA4 Credentials path not found: ' . $credentialsPath);
                throw new \Exception('GA4 credentials not configured');
            }
            
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentialsPath);
            
            $this->client = new BetaAnalyticsDataClient();
        }
        
        return $this->client;
    }
    
    /**
     * Récupérer le nombre total d'événements pour un événement spécifique
     */
    public function getEventCount(string $eventName, int $days = 30): int
    {
        try {
            $client = $this->getClient();
            
            $request = new RunReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => $days . 'daysAgo',
                        'end_date' => 'today',
                    ]),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'eventName']),
                ],
                'metrics' => [
                    new Metric(['name' => 'eventCount']),
                ],
                'dimension_filter' => new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => 'eventName',
                        'string_filter' => new Filter\StringFilter([
                            'match_type' => Filter\StringFilter\MatchType::EXACT,
                            'value' => $eventName,
                        ]),
                    ]),
                ]),
            ]);
            
            $response = $client->runReport($request);
            
            $totalCount = 0;
            foreach ($response->getRows() as $row) {
                $metricValues = $row->getMetricValues();
                $totalCount += (int) $metricValues[0]->getValue();
            }
            
            Log::info("GA4 Event count for {$eventName}: {$totalCount}");
            
            return $totalCount;
            
        } catch (\Exception $e) {
            Log::error('GA4 getEventCount error: ' . $e->getMessage(), [
                'event_name' => $eventName,
                'property_id' => $this->propertyId,
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return 0;
        }
    }
    
    /**
     * Récupérer les statistiques par pays
     */
    public function getStatsByCountry(string $eventName, int $days = 30): array
    {
        try {
            $client = $this->getClient();
            
            $request = new RunReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => $days . 'daysAgo',
                        'end_date' => 'today',
                    ]),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'country']),
                ],
                'metrics' => [
                    new Metric(['name' => 'eventCount']),
                ],
                'dimension_filter' => new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => 'eventName',
                        'string_filter' => new Filter\StringFilter([
                            'match_type' => Filter\StringFilter\MatchType::EXACT,
                            'value' => $eventName,
                        ]),
                    ]),
                ]),
            ]);
            
            $response = $client->runReport($request);
            
            $statsByCountry = [];
            foreach ($response->getRows() as $row) {
                $dimensionValues = $row->getDimensionValues();
                $metricValues = $row->getMetricValues();
                
                $country = $dimensionValues[0]->getValue();
                $count = (int) $metricValues[0]->getValue();
                
                $statsByCountry[$country] = $count;
            }
            
            // Trier par nombre décroissant
            arsort($statsByCountry);
            
            Log::info("GA4 Stats by country for {$eventName}: " . count($statsByCountry) . " countries");
            
            return $statsByCountry;
            
        } catch (\Exception $e) {
            Log::error('GA4 getStatsByCountry error: ' . $e->getMessage(), [
                'event_name' => $eventName,
                'property_id' => $this->propertyId,
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
    
    /**
     * Récupérer les statistiques par appareil
     */
    public function getStatsByDevice(string $eventName, int $days = 30): array
    {
        try {
            $client = $this->getClient();
            
            $request = new RunReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => $days . 'daysAgo',
                        'end_date' => 'today',
                    ]),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'deviceCategory']),
                ],
                'metrics' => [
                    new Metric(['name' => 'eventCount']),
                ],
                'dimension_filter' => new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => 'eventName',
                        'string_filter' => new Filter\StringFilter([
                            'match_type' => Filter\StringFilter\MatchType::EXACT,
                            'value' => $eventName,
                        ]),
                    ]),
                ]),
            ]);
            
            $response = $client->runReport($request);
            
            $statsByDevice = [];
            foreach ($response->getRows() as $row) {
                $dimensionValues = $row->getDimensionValues();
                $metricValues = $row->getMetricValues();
                
                $device = $dimensionValues[0]->getValue();
                $count = (int) $metricValues[0]->getValue();
                
                $statsByDevice[$device] = $count;
            }
            
            Log::info("GA4 Stats by device for {$eventName}: " . json_encode($statsByDevice));
            
            return $statsByDevice;
            
        } catch (\Exception $e) {
            Log::error('GA4 getStatsByDevice error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupérer les utilisateurs actifs en temps réel
     */
    public function getActiveUsersRealTime(): int
    {
        try {
            $client = $this->getClient();
            
            $request = new RunReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => 'today',
                        'end_date' => 'today',
                    ]),
                ],
                'metrics' => [
                    new Metric(['name' => 'activeUsers']),
                ],
            ]);
            
            $response = $client->runReport($request);
            
            $activeUsers = 0;
            foreach ($response->getRows() as $row) {
                $metricValues = $row->getMetricValues();
                $activeUsers += (int) $metricValues[0]->getValue();
            }
            
            Log::info("GA4 Active users: {$activeUsers}");
            
            return $activeUsers;
            
        } catch (\Exception $e) {
            Log::error('GA4 getActiveUsersRealTime error: ' . $e->getMessage());
            return 0;
        }
    }
}

