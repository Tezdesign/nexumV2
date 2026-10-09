<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class AIAuditService
{
    private string $projectRoot;
    private string $pythonScriptPath;

    public function __construct(string $projectDir)
    {
        $this->projectRoot = $projectDir;
        $this->pythonScriptPath = $projectDir . '/src/aitools/userai/ai_audit_wrapper.py';
    }

    /**
     * Generate AI insights for user audit
     */
    /**
     * @param array<string,mixed> $userData
     * @return array<string,mixed>
     */
    public function generateUserAuditInsights(array $userData): array
    {
        $prompt = $this->buildUserAuditPrompt($userData);
        
        try {
            $aiResponse = $this->callAI($prompt);
            return $this->parseAIResponse($aiResponse);
        } catch (\Exception $e) {
            return ['error' => 'AI service unavailable, showing the raw counts only.'] + $this->getFallbackUserInsights($userData);
        }
    }

    /**
     * Generate AI insights for reclamation audit
     */
    /**
     * @param array<string,mixed> $reclamationData
     * @return array<string,mixed>
     */
    public function generateReclamationAuditInsights(array $reclamationData): array
    {
        $prompt = $this->buildReclamationAuditPrompt($reclamationData);
        
        try {
            $aiResponse = $this->callAI($prompt);
            return $this->parseAIResponse($aiResponse);
        } catch (\Exception $e) {
            return ['error' => 'AI service unavailable, showing the raw counts only.'] + $this->getFallbackReclamationInsights($reclamationData);
        }
    }

    /**
     * Generate summary statistics with AI analysis
     */
    /**
     * @param array<string,mixed> $stats
     * @return array<string,mixed>
     */
    public function generateAISummary(array $stats): array
    {
        $prompt = $this->buildSummaryPrompt($stats);
        
        try {
            $aiResponse = $this->callAI($prompt);
            return $this->parseSummary($aiResponse);
        } catch (\Exception $e) {
            return ['error' => 'AI service unavailable, showing the raw counts only.'] + $this->getFallbackSummary($stats);
        }
    }

    private function callAI(string $prompt): string
    {
        // ensure project root is used as working directory so static analysis sees $projectRoot used
        $process = new Process(['python', $this->pythonScriptPath], $this->projectRoot);
        $process->setInput($prompt);
        $process->setTimeout(30); // 30 second timeout
        
        try {
            $process->run();
            
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            
            return $process->getOutput();
        } catch (ProcessFailedException $e) {
            throw new \Exception('AI service failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $userData
     */
    private function buildUserAuditPrompt(array $userData): string
    {
        $totalUsers = $userData['total_users'];
        $activeUsers = $userData['active_users'];
        $suspendedUsers = $userData['suspended_users'];
        $newUsers = $userData['new_users_this_month'];
        
        $activeRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;
        $suspensionRate = $totalUsers > 0 ? round(($suspendedUsers / $totalUsers) * 100, 2) : 0;
        $growthRate = $totalUsers > 0 ? round(($newUsers / $totalUsers) * 100, 2) : 0;
        
        return "Analyze this specific user audit data and provide data-driven insights:

TOTAL USERS: {$totalUsers}
ACTIVE USERS: {$activeUsers} ({$activeRate}% active rate)
SUSPENDED USERS: {$suspendedUsers} ({$suspensionRate}% suspension rate)
NEW USERS THIS MONTH: {$newUsers} ({$growthRate}% growth rate)

Calculate and analyze:
1. User engagement health based on {$activeRate}% active rate
2. Security risk level from {$suspensionRate}% suspension rate  
3. Growth sustainability from {$growthRate}% monthly growth
4. Specific observations about these exact numbers

Provide analysis specific to these metrics, not generic statements.
Format as JSON: {\"observations\": [\"specific data points\"], \"concerns\": [\"specific issues\"], \"recommendations\": [\"data-driven actions\"], \"risk_level\": \"low/medium/high\"}";
    }

    /**
     * @param array<string,mixed> $reclamationData
     */
    private function buildReclamationAuditPrompt(array $reclamationData): string
    {
        $total = $reclamationData['total_reclamations'];
        $pending = $reclamationData['pending_reclamations'];
        $resolved = $reclamationData['resolved_reclamations'];
        $closed = $reclamationData['closed_reclamations'];
        
        $resolutionRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;
        $pendingRate = $total > 0 ? round(($pending / $total) * 100, 1) : 0;
        $closureRate = $total > 0 ? round(($closed / $total) * 100, 1) : 0;
        
        return "Analyze this specific reclamation audit data and provide data-driven insights:

TOTAL RECLAMATIONS: {$total}
PENDING: {$pending} ({$pendingRate}% pending rate)
RESOLVED: {$resolved} ({$resolutionRate}% resolution rate)
CLOSED: {$closed} ({$closureRate}% closure rate)

Calculate and analyze:
1. Resolution efficiency based on {$resolutionRate}% success rate
2. Workload pressure from {$pendingRate}% pending rate
3. Process effectiveness from {$closureRate}% closure rate
4. Specific patterns from these exact metrics

Provide analysis specific to these percentages and numbers, not generic statements.
Format as JSON: {\"patterns\": [\"specific data patterns\"], \"response_assessment\": \"specific assessment\", \"efficiency\": \"specific efficiency analysis\", \"recommendations\": [\"data-driven actions\"]}";
    }

    /**
     * @param array<string,mixed> $stats
     */
    private function buildSummaryPrompt(array $stats): string
    {
        $users = $stats['users'] ?? [];
        $reclamations = $stats['reclamations'] ?? [];
        
        $totalUsers = $users['total_users'] ?? 0;
        $activeUsers = $users['active_users'] ?? 0;
        $totalReclamations = $reclamations['total_reclamations'] ?? 0;
        $resolvedReclamations = $reclamations['resolved_reclamations'] ?? 0;
        $pendingReclamations = $reclamations['pending_reclamations'] ?? 0;
        
        $userEngagementRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;
        $reclamationResolutionRate = $totalReclamations > 0 ? round(($resolvedReclamations / $totalReclamations) * 100, 1) : 0;
        $reclamationPendingRate = $totalReclamations > 0 ? round(($pendingReclamations / $totalReclamations) * 100, 1) : 0;
        
        return "Analyze these specific audit metrics and provide data-driven insights:

USER METRICS:
- Total Users: {$totalUsers}
- Active Users: {$activeUsers} ({$userEngagementRate}% engagement)
- User Engagement Rate: {$userEngagementRate}%

RECLAMATION METRICS:
- Total Reclamations: {$totalReclamations}
- Resolved: {$resolvedReclamations} ({$reclamationResolutionRate}% resolution rate)
- Pending: {$pendingReclamations} ({$reclamationPendingRate}% pending rate)

Provide specific analysis based on these exact percentages:
1. System health assessment using {$userEngagementRate}% engagement and {$reclamationResolutionRate}% resolution rate
2. Key metrics summary highlighting these specific performance indicators
3. Trend analysis based on {$reclamationPendingRate}% pending rate and user metrics
4. Action items specific to these performance levels

Focus analysis on these actual numbers, not generic statements.
Format as JSON: {\"health_assessment\": \"specific assessment\", \"metrics_summary\": [\"specific metrics\"], \"trend_analysis\": [\"specific trends\"], \"action_items\": [\"specific actions\"]}";
    }

    /**
     * @param string $response
     * @return array<string,mixed>
     */
    private function parseAIResponse(string $response): array
    {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
        
        // Fallback: parse text response
        return [
            'raw_response' => $response,
            'insights' => $this->extractInsightsFromText($response)
        ];
    }

    /**
     * @param string $response
     * @return array<string,mixed>
     */
    private function parseSummary(string $response): array
    {
        $parsed = $this->parseAIResponse($response);
        return [
            'health_assessment' => $parsed['health_assessment'] ?? 'Unknown',
            'metrics_summary' => $parsed['metrics_summary'] ?? [],
            'trend_analysis' => $parsed['trend_analysis'] ?? [],
            'action_items' => $parsed['action_items'] ?? []
        ];
    }

    /**
     * @param string $text
     * @return array<string,mixed>
     */
    private function extractInsightsFromText(string $text): array
    {
        // Simple text parsing fallback
        return [
            'summary' => substr($text, 0, 500) . '...',
            'recommendations' => ['Manual review recommended']
        ];
    }

    // Fallback methods when AI is unavailable
    /**
     * @param array<string,mixed> $userData
     * @return array<string,mixed>
     */
    private function getFallbackUserInsights(array $userData): array
    {
        return [
            'observations' => [
                'Total users: ' . $userData['total_users'],
                'Active users: ' . $userData['active_users']
            ],
        ];
    }

    /**
     * @param array<string,mixed> $reclamationData
     * @return array<string,mixed>
     */
    private function getFallbackReclamationInsights(array $reclamationData): array
    {
        return [
            'patterns' => [
                'Total reclamations: ' . $reclamationData['total_reclamations'],
                'Pending: ' . $reclamationData['pending_reclamations'],
                'Resolved: ' . $reclamationData['resolved_reclamations'],
                'Closed: ' . $reclamationData['closed_reclamations'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $stats
     * @return array<string,mixed>
     */
    private function getFallbackSummary(array $stats): array
    {
        $users = is_array($stats['users'] ?? null) ? $stats['users'] : [];
        $reclamations = is_array($stats['reclamations'] ?? null) ? $stats['reclamations'] : [];

        return [
            'health_assessment' => 'AI analysis unavailable',
            'metrics_summary' => [
                'Users: ' . ($users['total_users'] ?? 0) . ' (' . ($users['active_users'] ?? 0) . ' active)',
                'Reclamations: ' . ($reclamations['total_reclamations'] ?? 0) . ' (' . ($reclamations['pending_reclamations'] ?? 0) . ' pending)',
            ],
        ];
    }
}
